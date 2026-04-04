import { CachesDirectoryPath, mkdir, exists } from '@dr.pogodin/react-native-fs';

// Lazy-load native module to prevent startup crash if models aren't bundled
let _createTTS: any = null;
let _saveAudioToFile: any = null;
function getTtsModule() {
  if (!_createTTS) {
    try {
      const mod = require('react-native-sherpa-onnx/tts');
      _createTTS = mod.createTTS;
      _saveAudioToFile = mod.saveAudioToFile;
    } catch {
      return null;
    }
  }
  return { createTTS: _createTTS, saveAudioToFile: _saveAudioToFile };
}

let engine: any = null;
let initPromise: Promise<any> | null = null;
let outputCounter = 0;

// Cache of pre-generated question audio URIs keyed by text hash
const pregenCache = new Map<string, string>();
let pregenInProgress = false;

function textHash(text: string): string {
  let hash = 0;
  for (let i = 0; i < text.length; i++) {
    hash = ((hash << 5) - hash + text.charCodeAt(i)) | 0;
  }
  return hash.toString(36);
}

async function getEngine(): Promise<any> {
  const mod = getTtsModule();
  if (!mod) throw new Error('TTS native module not available');
  if (engine) return engine;
  if (initPromise) return initPromise;

  console.log('[TTS] Initializing Kokoro TTS engine...');
  initPromise = mod.createTTS({
    modelPath: { type: 'asset', path: 'models/kokoro-en' },
    modelType: 'kokoro',
    numThreads: 4,
    provider: 'coreml',
  }).catch((coremlErr: any) => {
    console.warn('[TTS] CoreML init failed, falling back to CPU:', coremlErr);
    return mod.createTTS({
      modelPath: { type: 'asset', path: 'models/kokoro-en' },
      modelType: 'kokoro',
      numThreads: 4,
      provider: 'cpu',
    });
  });

  try {
    engine = await initPromise;
    console.log('[TTS] Kokoro TTS engine initialized successfully.');
    return engine;
  } catch (err) {
    console.error('[TTS] Failed to initialize Kokoro TTS engine:', err);
    throw err;
  } finally {
    initPromise = null;
  }
}

/** Eagerly initialize the TTS engine so first synthesis is fast. */
export function warmupTts(): void {
  void getEngine().catch(() => {});
}

export function isTtsEnabled(): boolean {
  return true;
}

async function generateAndSave(text: string): Promise<string> {
  const tts = await getEngine();

  // sid 4 = af_heart (warmer, more expressive voice)
  // speed 1.0 = natural pace
  const audio = await tts.generateSpeech(text, {
    sid: 4,
    speed: 1.0,
  });

  const outputDir = `${CachesDirectoryPath}/sherpa-tts`;
  await mkdir(outputDir).catch(() => {});

  outputCounter += 1;
  const outputPath = `${outputDir}/speech-${Date.now()}-${outputCounter}.wav`;
  const mod = getTtsModule();
  if (!mod) throw new Error('TTS native module not available');
  const savedPath = await mod.saveAudioToFile(audio, outputPath);

  return savedPath.startsWith('file://') ? savedPath : `file://${savedPath}`;
}

/**
 * Pre-generate TTS audio for a list of question texts.
 * Call this early (e.g., on session init) so audio is ready when needed.
 */
export async function pregenerateQuestions(texts: string[]): Promise<void> {
  if (pregenInProgress) return;
  pregenInProgress = true;

  console.log(`[TTS] Pre-generating ${texts.length} questions...`);
  try {
    for (let i = 0; i < texts.length; i++) {
      const text = texts[i].trim();
      if (!text) continue;

      const key = textHash(text);
      if (pregenCache.has(key)) continue;

      try {
        const uri = await generateAndSave(text);
        pregenCache.set(key, uri);
        console.log(`[TTS] Pre-generated question ${i + 1}/${texts.length}`);
      } catch (err) {
        console.warn(`[TTS] Failed to pre-generate question ${i + 1}:`, err);
        break; // Don't block on errors, remaining will be generated on-demand
      }
    }
  } finally {
    pregenInProgress = false;
  }
  console.log(`[TTS] Pre-generation complete: ${pregenCache.size} cached`);
}

/**
 * Get a pre-generated audio URI for text, or null if not cached.
 */
export function getPregenerated(text: string): string | null {
  return pregenCache.get(textHash(text.trim())) ?? null;
}

export async function synthesizeSpeech(text: string): Promise<{ uri: string }> {
  const trimmed = text.trim();
  if (!trimmed) {
    throw new Error('Cannot synthesize empty text.');
  }

  // Check pre-generated cache first
  const cached = pregenCache.get(textHash(trimmed));
  if (cached) {
    console.log('[TTS] Using pre-generated audio');
    return { uri: cached };
  }

  console.log('[TTS] Synthesizing on-demand:', trimmed.slice(0, 50), '...');
  const uri = await generateAndSave(trimmed);
  return { uri };
}

export async function releaseTts(): Promise<void> {
  const e = engine;
  engine = null;
  initPromise = null;
  pregenCache.clear();
  if (e) {
    try {
      await e.destroy();
    } catch {
      // best effort
    }
  }
}
