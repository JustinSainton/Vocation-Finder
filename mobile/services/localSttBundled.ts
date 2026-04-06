/**
 * Wrapper around sttService that provides the localStt API surface.
 * Uses the bundled Whisper model directly (no download system).
 */
import type { AssessmentLocale } from '../constants/assessmentLocale';

let sttMod: any = null;
function getStt() {
  if (!sttMod) {
    try { sttMod = require('./sttService'); } catch { sttMod = null; }
  }
  return sttMod;
}

export function isLocalSttEnabled(): boolean {
  return getStt()?.isSttEnabled() ?? false;
}

export async function warmupLocalStt(_locale: AssessmentLocale): Promise<void> {
  try { getStt()?.warmupStt(); } catch {}
}

export async function transcribeLocalAudio(
  audioUri: string,
  locale: AssessmentLocale
): Promise<{ text: string; locale: AssessmentLocale; confidence: number; engine: string; modelId: string }> {
  const stt = getStt();
  if (!stt) throw new Error('STT not available');

  // Retry up to 3 times with 2s delay (model may still be initializing)
  let lastError: unknown;
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const result = await stt.transcribeAudio(audioUri, locale);
      return {
        text: result.text,
        locale: result.locale,
        confidence: 1.0,
        engine: 'sherpa-onnx',
        modelId: 'whisper-tiny-bundled',
      };
    } catch (err) {
      lastError = err;
      if (attempt < 2) {
        console.warn(`[STT] Attempt ${attempt + 1} failed, retrying in 2s...`);
        await new Promise(resolve => setTimeout(resolve, 2000));
      }
    }
  }
  throw lastError;
}

export async function releaseLocalStt(): Promise<void> {
  try { await getStt()?.releaseStt(); } catch {}
}
