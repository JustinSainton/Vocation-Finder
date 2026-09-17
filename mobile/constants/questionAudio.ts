import type { AssessmentLocale } from './assessmentLocale';

type AudioAsset = number; // Metro static require returns a number

const EN = 'en-US' as const;

// Pre-generated beta question audio (Kokoro af_bella @ 0.9x speed).
// These are bundled at build time for instant playback — no on-device generation needed.
let QUESTION_AUDIO: Record<number, Record<string, AudioAsset>> | null = null;

try {
  QUESTION_AUDIO = {
    1: { [EN]: require('../assets/audio/questions/q01_en-US.wav') },
    2: { [EN]: require('../assets/audio/questions/q02_en-US.wav') },
    3: { [EN]: require('../assets/audio/questions/q03_en-US.wav') },
    4: { [EN]: require('../assets/audio/questions/q04_en-US.wav') },
    5: { [EN]: require('../assets/audio/questions/q05_en-US.wav') },
  };
} catch {
  QUESTION_AUDIO = null;
}

/**
 * Get the bundled audio asset for a question prompt.
 * @param sortOrder 1-indexed question number (1-5 for beta)
 * @param locale Assessment locale
 * @returns Metro asset number, or null if not available
 */
export function getQuestionAudio(sortOrder: number, locale: AssessmentLocale): AudioAsset | null {
  return QUESTION_AUDIO?.[sortOrder]?.[locale] ?? null;
}
