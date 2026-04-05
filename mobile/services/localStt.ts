import type { ModelMetaBase } from 'react-native-sherpa-onnx/download';
import type { SttEngine, STTModelType, SttRecognitionResult } from 'react-native-sherpa-onnx/stt';
import {
  AssessmentLocale,
  getSpeechRecognitionLanguage,
  normalizeAssessmentLocale,
} from '../constants/assessmentLocale';

const LOCAL_STT_ENABLED = process.env.EXPO_PUBLIC_LOCAL_STT_ENABLED !== 'false';
const LOCAL_STT_MODEL_ID = process.env.EXPO_PUBLIC_LOCAL_STT_MODEL_ID?.trim();
const LOCAL_STT_MODEL_TYPE = parseModelType(process.env.EXPO_PUBLIC_LOCAL_STT_MODEL_TYPE);
const DEFAULT_STT_MODEL_ID = 'sherpa-onnx-whisper-tiny';

interface InitializedStt {
  engine: SttEngine;
  locale: AssessmentLocale;
  modelId: string;
}

type FsModule = {
  exists(path: string): Promise<boolean>;
};

type SherpaCoreModule = {
  fileModelPath(path: string): { type: 'file'; path: string };
};

type SherpaDownloadModule = {
  ModelCategory: {
    Stt: 'stt';
  };
  downloadModelByCategory<T extends ModelMetaBase>(
    category: 'stt',
    id: string
  ): Promise<{ modelId: string; localPath: string }>;
  getLocalModelPathByCategory(category: 'stt', id: string): Promise<string | null>;
  listDownloadedModelsByCategory<T extends ModelMetaBase>(category: 'stt'): Promise<T[]>;
  refreshModelsByCategory<T extends ModelMetaBase>(category: 'stt'): Promise<T[]>;
};

type SherpaSttModule = {
  createSTT(options: {
    modelPath: { type: 'file'; path: string };
    modelType?: STTModelType;
    provider?: string;
    numThreads?: number;
    modelOptions?: {
      whisper?: {
        language?: string;
        task?: 'transcribe' | 'translate';
      };
    };
  }): Promise<SttEngine>;
};

type SherpaAudioModule = {
  decodeAudioFileToFloatSamples(
    inputPath: string,
    targetSampleRateHz?: number
  ): Promise<{ samples: number[]; sampleRate: number }>;
};

let initializedStt: InitializedStt | null = null;
let initializePromise: Promise<InitializedStt> | null = null;
let nativeModulesLoadAttempted = false;
let nativeModulesAvailable = false;

let fsModule: FsModule | null = null;
let sherpaCoreModule: SherpaCoreModule | null = null;
let sherpaDownloadModule: SherpaDownloadModule | null = null;
let sherpaSttModule: SherpaSttModule | null = null;
let sherpaAudioModule: SherpaAudioModule | null = null;

function parseModelType(value: string | undefined): STTModelType | undefined {
  if (!value) return undefined;

  const normalized = value.toLowerCase().trim();
  if (
    normalized === 'whisper' ||
    normalized === 'moonshine' ||
    normalized === 'sense_voice' ||
    normalized === 'canary' ||
    normalized === 'funasr_nano' ||
    normalized === 'qwen3_asr' ||
    normalized === 'auto'
  ) {
    return normalized;
  }

  return undefined;
}

function tryLoadNativeModules(): boolean {
  if (nativeModulesLoadAttempted) {
    return nativeModulesAvailable;
  }

  nativeModulesLoadAttempted = true;

  try {
    fsModule = require('@dr.pogodin/react-native-fs') as FsModule;
    sherpaCoreModule = require('react-native-sherpa-onnx') as SherpaCoreModule;
    sherpaDownloadModule = require('react-native-sherpa-onnx/download') as SherpaDownloadModule;
    sherpaSttModule = require('react-native-sherpa-onnx/stt') as SherpaSttModule;
    sherpaAudioModule = require('react-native-sherpa-onnx/audio') as SherpaAudioModule;
    nativeModulesAvailable = true;
  } catch {
    fsModule = null;
    sherpaCoreModule = null;
    sherpaDownloadModule = null;
    sherpaSttModule = null;
    sherpaAudioModule = null;
    nativeModulesAvailable = false;
  }

  return nativeModulesAvailable;
}

function requireNativeModules(): {
  fs: FsModule;
  core: SherpaCoreModule;
  download: SherpaDownloadModule;
  stt: SherpaSttModule;
  audio: SherpaAudioModule;
} {
  if (
    !tryLoadNativeModules() ||
    !fsModule ||
    !sherpaCoreModule ||
    !sherpaDownloadModule ||
    !sherpaSttModule ||
    !sherpaAudioModule
  ) {
    throw new Error('Native sherpa STT modules are unavailable in this app build.');
  }

  return {
    fs: fsModule,
    core: sherpaCoreModule,
    download: sherpaDownloadModule,
    stt: sherpaSttModule,
    audio: sherpaAudioModule,
  };
}

function toFilePath(path: string): string {
  return path.startsWith('file://') ? path.replace('file://', '') : path;
}

async function chooseModelId(): Promise<string> {
  if (LOCAL_STT_MODEL_ID) {
    return LOCAL_STT_MODEL_ID;
  }

  const { download } = requireNativeModules();
  const downloadedModels = await download.listDownloadedModelsByCategory<ModelMetaBase>(
    download.ModelCategory.Stt
  );
  const downloadedMatch = downloadedModels.find((model) => model.id === DEFAULT_STT_MODEL_ID);
  if (downloadedMatch) {
    return downloadedMatch.id;
  }

  const availableModels = await download.refreshModelsByCategory<ModelMetaBase>(
    download.ModelCategory.Stt
  );
  const exactMatch = availableModels.find((model) => model.id === DEFAULT_STT_MODEL_ID);
  if (exactMatch) {
    return exactMatch.id;
  }

  const whisperTinyFallback = availableModels.find((model) =>
    model.id.toLowerCase().includes('whisper-tiny')
  );
  if (whisperTinyFallback) {
    return whisperTinyFallback.id;
  }

  const firstModel = availableModels[0];
  if (!firstModel) {
    throw new Error('No local STT model available.');
  }

  return firstModel.id;
}

async function ensureModelPath(modelId: string): Promise<string> {
  const { download } = requireNativeModules();
  const localPath = await download.getLocalModelPathByCategory(download.ModelCategory.Stt, modelId);
  if (localPath) {
    return localPath;
  }

  await download.refreshModelsByCategory<ModelMetaBase>(download.ModelCategory.Stt);
  await download.downloadModelByCategory<ModelMetaBase>(download.ModelCategory.Stt, modelId);

  const downloadedPath = await download.getLocalModelPathByCategory(download.ModelCategory.Stt, modelId);
  if (!downloadedPath) {
    throw new Error(`Failed to prepare local STT model: ${modelId}`);
  }

  return downloadedPath;
}

async function createEngine(modelPath: string, locale: AssessmentLocale): Promise<SttEngine> {
  const { core, stt } = requireNativeModules();

  return stt.createSTT({
    modelPath: core.fileModelPath(modelPath),
    modelType: LOCAL_STT_MODEL_TYPE ?? 'whisper',
    provider: 'cpu',
    numThreads: 4,
    modelOptions: {
      whisper: {
        language: getSpeechRecognitionLanguage(locale),
        task: 'transcribe',
      },
    },
  });
}

async function ensureInitialized(locale: AssessmentLocale): Promise<InitializedStt> {
  const normalizedLocale = normalizeAssessmentLocale(locale);

  if (initializedStt && initializedStt.locale === normalizedLocale) {
    return initializedStt;
  }

  if (initializePromise) {
    return initializePromise;
  }

  initializePromise = (async () => {
    if (initializedStt && initializedStt.locale !== normalizedLocale) {
      try {
        await initializedStt.engine.destroy();
      } catch {
        // best effort cleanup
      }
      initializedStt = null;
    }

    const modelId = await chooseModelId();
    const modelPath = await ensureModelPath(modelId);
    const engine = await createEngine(modelPath, normalizedLocale);

    const initialized = {
      engine,
      locale: normalizedLocale,
      modelId,
    };

    initializedStt = initialized;
    return initialized;
  })();

  try {
    return await initializePromise;
  } finally {
    initializePromise = null;
  }
}

function normalizeTranscriptionLocale(result: SttRecognitionResult, fallback: AssessmentLocale): AssessmentLocale {
  if (result.lang?.trim()) {
    return normalizeAssessmentLocale(result.lang);
  }

  return normalizeAssessmentLocale(fallback);
}

export function isLocalSttEnabled(): boolean {
  return LOCAL_STT_ENABLED && tryLoadNativeModules();
}

export async function warmupLocalStt(locale: AssessmentLocale): Promise<void> {
  if (!LOCAL_STT_ENABLED) {
    return;
  }

  await ensureInitialized(locale);
}

export async function transcribeLocalAudio(
  audioUri: string,
  locale: AssessmentLocale
): Promise<{
  text: string;
  locale: AssessmentLocale;
  confidence: number | null;
  engine: string;
  modelId: string;
}> {
  if (!isLocalSttEnabled()) {
    throw new Error('Local STT is disabled.');
  }

  const filePath = toFilePath(audioUri);
  const { fs, audio } = requireNativeModules();
  const fileExists = await fs.exists(filePath);
  if (!fileExists) {
    throw new Error(`Recorded audio not found at ${filePath}`);
  }

  const initialized = await ensureInitialized(locale);
  let result: SttRecognitionResult | null = null;

  try {
    const decoded = await audio.decodeAudioFileToFloatSamples(filePath, 16000);
    result = await initialized.engine.transcribeSamples(decoded.samples, decoded.sampleRate);
  } catch {
    result = await initialized.engine.transcribeFile(filePath);
  }

  const text = result.text?.trim() ?? '';
  if (!text) {
    throw new Error('No speech detected in local transcription.');
  }

  return {
    text,
    locale: normalizeTranscriptionLocale(result, locale),
    confidence: null,
    engine: 'sherpa-onnx',
    modelId: initialized.modelId,
  };
}

export async function releaseLocalStt(): Promise<void> {
  const engine = initializedStt?.engine;
  initializedStt = null;
  initializePromise = null;

  if (engine) {
    try {
      await engine.destroy();
    } catch {
      // best effort cleanup
    }
  }
}
