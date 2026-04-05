import { Platform } from 'react-native';
import type { TtsEngine, TTSModelType } from 'react-native-sherpa-onnx/tts';
import type { TtsModelMeta } from 'react-native-sherpa-onnx/download';
import {
  AssessmentLocale,
  normalizeAssessmentLocale,
} from '../constants/assessmentLocale';

const LOCAL_TTS_ENABLED = process.env.EXPO_PUBLIC_LOCAL_TTS_ENABLED !== 'false';
const LOCAL_TTS_MODEL_ID = process.env.EXPO_PUBLIC_LOCAL_TTS_MODEL_ID?.trim();
const LOCAL_TTS_MODEL_TYPE = parseModelType(process.env.EXPO_PUBLIC_LOCAL_TTS_MODEL_TYPE);
const LOCAL_TTS_MODEL_IDS: Record<AssessmentLocale, string> = {
  'en-US':
    process.env.EXPO_PUBLIC_LOCAL_TTS_MODEL_ID_EN_US?.trim() ??
    'vits-piper-en_US-lessac-medium-int8',
  'es-419':
    process.env.EXPO_PUBLIC_LOCAL_TTS_MODEL_ID_ES_419?.trim() ??
    'vits-piper-es_MX-ald-medium-int8',
  'pt-BR':
    process.env.EXPO_PUBLIC_LOCAL_TTS_MODEL_ID_PT_BR?.trim() ??
    'vits-piper-pt_BR-cadu-medium-int8',
};

interface InitializedTts {
  engine: TtsEngine;
  modelId: string;
  locale: AssessmentLocale;
}

let initializedTts: InitializedTts | null = null;
let initializePromise: Promise<InitializedTts> | null = null;
let lastOutputPath: string | null = null;
let nativeModulesLoadAttempted = false;
let nativeModulesAvailable = false;

type FsModule = {
  CachesDirectoryPath: string;
  exists(path: string): Promise<boolean>;
  mkdir(path: string): Promise<void>;
  unlink(path: string): Promise<void>;
};

type SherpaCoreModule = {
  fileModelPath(path: string): { type: 'file'; path: string };
};

type SherpaDownloadModule = {
  ModelCategory: {
    Tts: 'tts';
  };
  downloadModelByCategory<T extends TtsModelMeta>(
    category: 'tts',
    id: string
  ): Promise<{ modelId: string; localPath: string }>;
  getLocalModelPathByCategory(category: 'tts', id: string): Promise<string | null>;
  listDownloadedModelsByCategory<T extends TtsModelMeta>(category: 'tts'): Promise<T[]>;
  refreshModelsByCategory<T extends TtsModelMeta>(category: 'tts'): Promise<T[]>;
};

type SherpaTtsModule = {
  createTTS(options: {
    modelPath: { type: 'file'; path: string };
    modelType?: TTSModelType;
    provider?: string;
    numThreads?: number;
  }): Promise<TtsEngine>;
  saveAudioToFile(
    audio: { samples: number[]; sampleRate: number },
    filePath: string
  ): Promise<string>;
};

let fsModule: FsModule | null = null;
let sherpaCoreModule: SherpaCoreModule | null = null;
let sherpaDownloadModule: SherpaDownloadModule | null = null;
let sherpaTtsModule: SherpaTtsModule | null = null;

function tryLoadNativeModules(): boolean {
  if (nativeModulesLoadAttempted) {
    return nativeModulesAvailable;
  }

  nativeModulesLoadAttempted = true;

  try {
    fsModule = require('@dr.pogodin/react-native-fs') as FsModule;
    sherpaCoreModule = require('react-native-sherpa-onnx') as SherpaCoreModule;
    sherpaDownloadModule = require('react-native-sherpa-onnx/download') as SherpaDownloadModule;
    sherpaTtsModule = require('react-native-sherpa-onnx/tts') as SherpaTtsModule;
    nativeModulesAvailable = true;
  } catch {
    fsModule = null;
    sherpaCoreModule = null;
    sherpaDownloadModule = null;
    sherpaTtsModule = null;
    nativeModulesAvailable = false;
  }

  return nativeModulesAvailable;
}

function requireNativeModules(): {
  fs: FsModule;
  core: SherpaCoreModule;
  download: SherpaDownloadModule;
  tts: SherpaTtsModule;
} {
  if (!tryLoadNativeModules() || !fsModule || !sherpaCoreModule || !sherpaDownloadModule || !sherpaTtsModule) {
    throw new Error('Native sherpa modules are unavailable in this app build.');
  }

  return {
    fs: fsModule,
    core: sherpaCoreModule,
    download: sherpaDownloadModule,
    tts: sherpaTtsModule,
  };
}

function getOutputDir(): string {
  return `${requireNativeModules().fs.CachesDirectoryPath}/sherpa-onnx/tts`;
}

function parseModelType(value: string | undefined): TTSModelType | undefined {
  if (!value) return undefined;
  const normalized = value.toLowerCase().trim();
  if (
    normalized === 'auto' ||
    normalized === 'vits' ||
    normalized === 'matcha' ||
    normalized === 'kokoro' ||
    normalized === 'kitten' ||
    normalized === 'pocket' ||
    normalized === 'zipvoice' ||
    normalized === 'supertonic'
  ) {
    return normalized;
  }

  return undefined;
}

function localeMatchesModel(model: TtsModelMeta, locale: AssessmentLocale): boolean {
  const normalizedLocale = normalizeAssessmentLocale(locale);

  return model.languages.some((language) => {
    const normalized = language.toLowerCase();

    if (normalizedLocale === 'es-419') {
      return normalized.startsWith('es');
    }

    if (normalizedLocale === 'pt-BR') {
      return normalized.startsWith('pt');
    }

    return normalized.startsWith('en');
  });
}

function modelTypePriority(type: TtsModelMeta['type']): number {
  switch (type) {
    case 'kokoro':
      return 0;
    case 'vits':
      return 1;
    case 'matcha':
      return 2;
    case 'kitten':
      return 3;
    case 'pocket':
      return 4;
    case 'zipvoice':
      return 5;
    case 'auto':
      return 6;
    default:
      return 7;
  }
}

function modelSizePriority(sizeTier: TtsModelMeta['sizeTier']): number {
  switch (sizeTier) {
    case 'tiny':
      return 0;
    case 'small':
      return 1;
    case 'medium':
      return 2;
    case 'large':
      return 3;
    default:
      return 4;
  }
}

function modelQuantPriority(quantization: TtsModelMeta['quantization']): number {
  switch (quantization) {
    case 'int8':
      return 0;
    case 'int8-quantized':
      return 1;
    case 'fp16':
      return 2;
    default:
      return 3;
  }
}

function chooseBestModel(models: TtsModelMeta[], locale: AssessmentLocale): TtsModelMeta | null {
  if (models.length === 0) {
    return null;
  }

  return [...models].sort((a, b) => {
    const localeDelta =
      Number(localeMatchesModel(b, locale)) - Number(localeMatchesModel(a, locale));
    if (localeDelta !== 0) return localeDelta;

    const typeDelta = modelTypePriority(a.type) - modelTypePriority(b.type);
    if (typeDelta !== 0) return typeDelta;

    const sizeDelta = modelSizePriority(a.sizeTier) - modelSizePriority(b.sizeTier);
    if (sizeDelta !== 0) return sizeDelta;

    const quantDelta = modelQuantPriority(a.quantization) - modelQuantPriority(b.quantization);
    if (quantDelta !== 0) return quantDelta;

    return a.bytes - b.bytes;
  })[0]!;
}

async function pickModelId(locale: AssessmentLocale): Promise<string> {
  if (LOCAL_TTS_MODEL_ID) {
    return LOCAL_TTS_MODEL_ID;
  }

  const normalizedLocale = normalizeAssessmentLocale(locale);
  const localeDefaultModelId = LOCAL_TTS_MODEL_IDS[normalizedLocale];
  const { download } = requireNativeModules();

  const localDefaultPath = await download.getLocalModelPathByCategory(
    download.ModelCategory.Tts,
    localeDefaultModelId
  );
  if (localDefaultPath) {
    return localeDefaultModelId;
  }

  const downloadedModels = await download.listDownloadedModelsByCategory<TtsModelMeta>(
    download.ModelCategory.Tts
  );
  const downloadedExact = downloadedModels.find((model) => model.id === localeDefaultModelId);
  if (downloadedExact) {
    return downloadedExact.id;
  }

  const downloadedChoice = chooseBestModel(downloadedModels, normalizedLocale);
  if (downloadedChoice) {
    return downloadedChoice.id;
  }

  const availableModels = await download.refreshModelsByCategory<TtsModelMeta>(
    download.ModelCategory.Tts
  );
  const availableExact = availableModels.find((model) => model.id === localeDefaultModelId);
  if (availableExact) {
    return availableExact.id;
  }

  const availableChoice = chooseBestModel(availableModels, normalizedLocale);
  if (!availableChoice) {
    throw new Error('No local TTS model available.');
  }

  return availableChoice.id;
}

async function ensureModelPath(modelId: string): Promise<string> {
  const { download } = requireNativeModules();
  const localPath = await download.getLocalModelPathByCategory(download.ModelCategory.Tts, modelId);
  if (localPath) {
    return localPath;
  }

  await download.refreshModelsByCategory<TtsModelMeta>(download.ModelCategory.Tts);
  await download.downloadModelByCategory<TtsModelMeta>(download.ModelCategory.Tts, modelId);

  const downloadedPath = await download.getLocalModelPathByCategory(download.ModelCategory.Tts, modelId);
  if (!downloadedPath) {
    throw new Error(`Failed to prepare local TTS model: ${modelId}`);
  }

  return downloadedPath;
}

async function createEngine(modelPath: string): Promise<TtsEngine> {
  const { core, tts } = requireNativeModules();
  const providers = Platform.OS === 'ios' ? ['coreml', 'cpu'] : ['xnnpack', 'cpu'];
  const modelType = LOCAL_TTS_MODEL_TYPE ?? 'auto';
  let lastError: unknown;

  for (const provider of providers) {
    try {
      return await tts.createTTS({
        modelPath: core.fileModelPath(modelPath),
        modelType,
        provider,
        numThreads: 4,
      });
    } catch (error) {
      lastError = error;
    }
  }

  throw lastError instanceof Error ? lastError : new Error('Local TTS initialization failed.');
}

async function ensureInitialized(locale: AssessmentLocale): Promise<InitializedTts> {
  const normalizedLocale = normalizeAssessmentLocale(locale);

  if (initializedTts && initializedTts.locale === normalizedLocale) {
    return initializedTts;
  }

  if (initializePromise) {
    return initializePromise;
  }

  initializePromise = (async () => {
    if (initializedTts && initializedTts.locale !== normalizedLocale) {
      try {
        await initializedTts.engine.destroy();
      } catch {
        // best effort cleanup
      }
      initializedTts = null;
    }

    const modelId = await pickModelId(normalizedLocale);
    const modelPath = await ensureModelPath(modelId);
    const engine = await createEngine(modelPath);

    const initialized = {
      engine,
      modelId,
      locale: normalizedLocale,
    };

    initializedTts = initialized;
    return initialized;
  })();

  try {
    return await initializePromise;
  } finally {
    initializePromise = null;
  }
}

function toFileUri(path: string): string {
  return path.startsWith('file://') ? path : `file://${path}`;
}

function toFilePath(path: string): string {
  return path.startsWith('file://') ? path.replace('file://', '') : path;
}

async function ensureOutputDirectory(): Promise<void> {
  const { fs } = requireNativeModules();
  const outputDir = getOutputDir();

  if (await fs.exists(outputDir)) {
    return;
  }

  await fs.mkdir(outputDir);
}

export function isLocalTtsEnabled(): boolean {
  return LOCAL_TTS_ENABLED && tryLoadNativeModules();
}

export async function warmupLocalTts(locale: AssessmentLocale): Promise<void> {
  if (!LOCAL_TTS_ENABLED) {
    return;
  }

  await ensureInitialized(locale);
}

export async function synthesizeLocalSpeech(
  text: string,
  locale: AssessmentLocale
): Promise<{ uri: string; modelId: string }> {
  const content = text.trim();
  if (!isLocalTtsEnabled()) {
    throw new Error('Local TTS is disabled.');
  }

  if (!content) {
    throw new Error('Cannot synthesize empty text.');
  }

  const initialized = await ensureInitialized(locale);
  const generatedAudio = await initialized.engine.generateSpeech(content, {
    sid: 0,
    speed: 1.0,
  });

  await ensureOutputDirectory();
  const outputPath = `${getOutputDir()}/speech-${Date.now()}.wav`;
  const savedPath = await requireNativeModules().tts.saveAudioToFile(generatedAudio, outputPath);
  const normalizedSavedPath = toFilePath(savedPath);

  if (lastOutputPath && lastOutputPath !== normalizedSavedPath) {
    try {
      const previousExists = await requireNativeModules().fs.exists(lastOutputPath);
      if (previousExists) {
        await requireNativeModules().fs.unlink(lastOutputPath);
      }
    } catch {
      // cleanup is best effort
    }
  }

  lastOutputPath = normalizedSavedPath;

  return {
    uri: toFileUri(normalizedSavedPath),
    modelId: initialized.modelId,
  };
}

export async function releaseLocalTts(): Promise<void> {
  const engine = initializedTts?.engine;
  initializedTts = null;
  initializePromise = null;

  if (engine) {
    try {
      await engine.destroy();
    } catch {
      // best effort cleanup
    }
  }
}
