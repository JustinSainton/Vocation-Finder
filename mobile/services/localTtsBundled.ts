/**
 * Wrapper around ttsService that provides the localTts API surface.
 * Uses the bundled Kokoro model directly (no download system).
 */
import type { AssessmentLocale } from '../constants/assessmentLocale';

let ttsMod: any = null;
function getTts() {
  if (!ttsMod) {
    try { ttsMod = require('./ttsService'); } catch { ttsMod = null; }
  }
  return ttsMod;
}

export function isLocalTtsEnabled(): boolean {
  return getTts()?.isTtsEnabled() ?? false;
}

export async function warmupLocalTts(_locale: AssessmentLocale): Promise<void> {
  try { getTts()?.warmupTts(); } catch {}
}

export async function synthesizeLocalSpeech(
  text: string,
  _locale: AssessmentLocale
): Promise<{ uri: string; modelId: string }> {
  const tts = getTts();
  if (!tts) throw new Error('TTS not available');
  const result = await tts.synthesizeSpeech(text);
  return {
    uri: result.uri,
    modelId: 'kokoro-en-bundled',
  };
}

export async function releaseLocalTts(): Promise<void> {
  try { await getTts()?.releaseTts(); } catch {}
}
