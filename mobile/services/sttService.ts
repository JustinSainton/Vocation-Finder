import type { AssessmentLocale } from '../constants/assessmentLocale';

// Lazy-load native module to prevent startup crash if models aren't bundled
let _createSTT: any = null;
function getCreateSTT() {
  if (!_createSTT) {
    try {
      _createSTT = require('react-native-sherpa-onnx/stt').createSTT;
    } catch {
      return null;
    }
  }
  return _createSTT;
}

let engine: any = null;
let initPromise: Promise<any> | null = null;

async function getEngine(): Promise<any> {
  const createSTT = getCreateSTT();
  if (!createSTT) throw new Error('STT native module not available');
  if (engine) return engine;
  if (initPromise) return initPromise;

  console.log('[STT] Initializing Whisper STT engine...');
  initPromise = createSTT({
    modelPath: { type: 'asset', path: 'models/whisper-tiny' },
    modelType: 'whisper',
    preferInt8: true,
    numThreads: 2,
    provider: 'coreml',
  }).catch((coremlErr) => {
    console.warn('[STT] CoreML init failed, falling back to CPU:', coremlErr);
    return createSTT({
      modelPath: { type: 'asset', path: 'models/whisper-tiny' },
      modelType: 'whisper',
      preferInt8: true,
      numThreads: 2,
      provider: 'cpu',
    });
  });

  try {
    engine = await initPromise;
    console.log('[STT] Whisper STT engine initialized successfully.');
    return engine;
  } catch (err) {
    console.error('[STT] Failed to initialize Whisper STT engine:', err);
    throw err;
  } finally {
    initPromise = null;
  }
}

/** Eagerly initialize the STT engine so first transcription is fast. */
export function warmupStt(): void {
  try {
    void getEngine().catch(() => {});
  } catch {
    // Native module may not be available (e.g., Expo Go or missing models)
  }
}

export function isSttEnabled(): boolean {
  try {
    // Check if the native module is actually available
    require('react-native-sherpa-onnx/stt');
    return true;
  } catch {
    return false;
  }
}

export async function transcribeAudio(
  audioUri: string,
  _locale: AssessmentLocale
): Promise<{ text: string; locale: AssessmentLocale }> {
  const stt = await getEngine();

  // Native Whisper expects a raw file path, not a file:// URI.
  // expo-audio's audioRecorder.uri returns file:///path on iOS.
  const filePath = audioUri.startsWith('file://') ? audioUri.replace('file://', '') : audioUri;

  // Attempt transcription with one retry on transient failure
  let lastError: unknown;
  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      const result: SttRecognitionResult = await stt.transcribeFile(filePath);
      const text = (result.text ?? '').trim();

      if (!text) {
        throw new Error('No speech detected in audio.');
      }

      return { text, locale: _locale };
    } catch (err) {
      lastError = err;
      // Only retry on engine errors, not on "no speech" which will fail again
      const msg = err instanceof Error ? err.message : String(err);
      if (msg.includes('No speech detected')) {
        throw err;
      }
      console.warn(`[STT] Attempt ${attempt + 1} failed, ${attempt < 1 ? 'retrying...' : 'giving up'}:`, err);
    }
  }

  throw lastError;
}

export async function releaseStt(): Promise<void> {
  const e = engine;
  engine = null;
  initPromise = null;
  if (e) {
    try {
      await e.destroy();
    } catch {
      // best effort
    }
  }
}
