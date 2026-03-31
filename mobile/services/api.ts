import Constants from 'expo-constants';
import { AssessmentLocale } from '../constants/assessmentLocale';
import { useAuthStore } from '../stores/authStore';

function normalizeApiBaseUrl(url: string): string {
  const trimmed = url.trim().replace(/\/+$/, '');

  if (trimmed.endsWith('/api/v1')) {
    return trimmed;
  }

  if (trimmed.endsWith('/api')) {
    return `${trimmed}/v1`;
  }

  return `${trimmed}/api/v1`;
}

const envApiUrl = process.env.EXPO_PUBLIC_API_URL;
const configApiUrl = Constants.expoConfig?.extra?.apiUrl as string | undefined;
const fallbackApiUrl = 'https://vocation-finder-main-f14jpf.laravel.cloud';

const BASE_URL = normalizeApiBaseUrl(envApiUrl ?? configApiUrl ?? fallbackApiUrl);

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface ApiError {
  message: string;
  status: number;
}

function getAuthHeaders(): Record<string, string> {
  const token = useAuthStore.getState().token;
  if (!token) return {};
  return { Authorization: `Bearer ${token}` };
}

function deriveErrorMessage(
  responseStatusText: string,
  fallbackStatus: number,
  rawBody: string
): string {
  const trimmed = rawBody.trim();
  if (!trimmed) {
    return `Request failed: ${responseStatusText}`;
  }

  try {
    const parsed = JSON.parse(trimmed) as { message?: string; error?: string };
    return parsed.message ?? parsed.error ?? `Request failed: ${responseStatusText}`;
  } catch {
    if (trimmed.startsWith('<!DOCTYPE') || trimmed.startsWith('<html')) {
      return `Server error (${fallbackStatus}).`;
    }

    return trimmed.slice(0, 220);
  }
}

async function request<T>(
  endpoint: string,
  method: HttpMethod = 'GET',
  body?: unknown,
  extraHeaders?: Record<string, string>
): Promise<T> {
  const url = `${BASE_URL}${endpoint}`;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...getAuthHeaders(),
    ...extraHeaders,
  };

  const config: RequestInit = {
    method,
    headers,
  };

  if (body && method !== 'GET') {
    config.body = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(url, config);
  } catch {
    throw {
      message: `Network request failed. Check API URL: ${BASE_URL}`,
      status: 0,
    } as ApiError;
  }

  if (!response.ok) {
    const error: ApiError = {
      message: `Request failed: ${response.statusText}`,
      status: response.status,
    };

    const rawBody = await response.text();
    error.message = deriveErrorMessage(response.statusText, response.status, rawBody);

    throw error;
  }

  // Handle 204 No Content
  if (response.status === 204) {
    return undefined as T;
  }

  return response.json();
}

/**
 * Raw HTTP helpers
 */
export const api = {
  get: <T>(endpoint: string, extraHeaders?: Record<string, string>) =>
    request<T>(endpoint, 'GET', undefined, extraHeaders),

  post: <T>(endpoint: string, body?: unknown, extraHeaders?: Record<string, string>) =>
    request<T>(endpoint, 'POST', body, extraHeaders),

  put: <T>(endpoint: string, body?: unknown, extraHeaders?: Record<string, string>) =>
    request<T>(endpoint, 'PUT', body, extraHeaders),

  patch: <T>(endpoint: string, body?: unknown, extraHeaders?: Record<string, string>) =>
    request<T>(endpoint, 'PATCH', body, extraHeaders),

  delete: <T>(endpoint: string, extraHeaders?: Record<string, string>) =>
    request<T>(endpoint, 'DELETE', undefined, extraHeaders),
};

/**
 * Upload audio file using multipart/form-data
 */
async function uploadAudio<T>(
  endpoint: string,
  audioUri: string,
  fields?: Record<string, string>
): Promise<T> {
  const url = `${BASE_URL}${endpoint}`;
  const formData = new FormData();

  formData.append('audio', {
    uri: audioUri,
    type: 'audio/m4a',
    name: 'recording.m4a',
  } as any);

  if (fields) {
    for (const [key, value] of Object.entries(fields)) {
      formData.append(key, value);
    }
  }

  const headers: Record<string, string> = {
    'Content-Type': 'multipart/form-data',
    ...getAuthHeaders(),
  };

  let response: Response;
  try {
    response = await fetch(url, {
      method: 'POST',
      headers,
      body: formData,
    });
  } catch {
    throw {
      message: `Network request failed. Check API URL: ${BASE_URL}`,
      status: 0,
    } as ApiError;
  }

  if (!response.ok) {
    const error: ApiError = {
      message: `Request failed: ${response.statusText}`,
      status: response.status,
    };

    const rawBody = await response.text();
    error.message = deriveErrorMessage(response.statusText, response.status, rawBody);

    throw error;
  }

  return response.json();
}

// ── Domain types ──────────────────────────────────────────────

export interface Question {
  id: string;
  locale: AssessmentLocale;
  sort_order: number;
  category_name: string;
  category_slug?: string | null;
  question_text: string;
  conversation_prompt: string | null;
  follow_up_prompts: string[];
}

export interface Assessment {
  id: string;
  status: 'in_progress' | 'analyzing' | 'completed' | 'failed';
  mode: 'written' | 'conversation';
  locale: AssessmentLocale;
  speech_locale: AssessmentLocale;
  guest_token: string | null;
  created_at: string;
}

export interface VocationalProfile {
  id: string;
  opening_synthesis: string;
  vocational_orientation: string;
  primary_pathways: string[];
  specific_considerations: string;
  next_steps: string[];
  ministry_integration: string;
  primary_domain: string;
  mode_of_work: string;
  secondary_orientation: string;
  created_at: string;
}

export interface AssessmentResults {
  status: 'analyzing' | 'completed' | 'failed';
  profile: VocationalProfile | null;
  message?: string;
  tier?: 'free';
  locked_sections?: string[];
  upgrade_message?: string;
}

export interface ConversationTurnResponse {
  response: string;
  response_locale: AssessmentLocale;
  response_kind: 'follow_up' | 'next_question' | 'completion';
  current_question_index: number;
  is_follow_up: boolean;
  is_complete: boolean;
  reasoning: string;
}

export interface ConversationAudioUploadResponse {
  audio_path: string;
  audio_disk?: string;
  transcript: string;
  transcript_locale: AssessmentLocale;
  status: 'transcribed';
}

export interface ConversationSpeechResponse {
  audio_url: string;
  mime_type: string;
  provider: string;
  model: string;
  voice: string;
}

// ── Domain API methods ────────────────────────────────────────

export const assessmentApi = {
  /** Fetch the list of assessment questions */
  getQuestions: (locale?: AssessmentLocale) => {
    const query = locale ? `?locale=${encodeURIComponent(locale)}` : '';
    const headers = locale ? { 'X-Locale': locale } : undefined;
    return api.get<{ data: Question[] }>(`/questions${query}`, headers);
  },

  /** Create a new assessment session */
  createAssessment: (
    mode: 'written' | 'conversation',
    locale?: AssessmentLocale,
    speechLocale?: AssessmentLocale
  ) =>
    api.post<Assessment>('/assessments', {
      mode,
      locale,
      speech_locale: speechLocale ?? locale,
    }),

  /** Save an answer for a specific question */
  saveAnswer: (
    assessmentId: string,
    questionId: string,
    responseText: string,
    guestToken?: string,
    responseLocale?: AssessmentLocale
  ) => {
    const headers: Record<string, string> = {};
    if (guestToken) headers['X-Guest-Token'] = guestToken;
    return api.post<{ id: string }>(
      `/assessments/${assessmentId}/answers`,
      {
        question_id: questionId,
        response_text: responseText,
        response_locale: responseLocale,
      },
      headers
    );
  },

  /** Mark the assessment as complete, triggering synthesis */
  completeAssessment: (assessmentId: string, guestToken?: string) =>
    api.post<{ status: string }>(
      `/assessments/${assessmentId}/complete`,
      undefined,
      guestToken ? { 'X-Guest-Token': guestToken } : undefined
    ),

  /** Fetch results (returns 202 while processing, 200 when ready) */
  getResults: async (assessmentId: string, guestToken?: string): Promise<AssessmentResults> => {
    const url = `${BASE_URL}/assessments/${assessmentId}/results`;

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...getAuthHeaders(),
    };
    if (guestToken) headers['X-Guest-Token'] = guestToken;

    const response = await fetch(url, { method: 'GET', headers });

    if (response.status === 202) {
      const data = await response.json().catch(() => ({}));
      return {
        status: 'analyzing',
        profile: null,
        message: typeof data?.message === 'string' ? data.message : undefined,
      };
    }

    if (response.status === 500) {
      const data = await response.json().catch(() => ({}));
      if (data?.status === 'failed') {
        return {
          status: 'failed',
          profile: null,
          message: typeof data?.message === 'string' ? data.message : 'We encountered an issue analyzing your assessment.',
        };
      }
    }

    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw {
        message: data.message ?? `Request failed: ${response.statusText}`,
        status: response.status,
      };
    }

    const data = await response.json();
    return {
      status: 'completed',
      profile: data.data,
      tier: data.tier,
      locked_sections: data.locked_sections,
      upgrade_message: data.upgrade_message,
    };
  },

  /** Email results to the user */
  emailResults: (assessmentId: string, email: string, guestToken?: string) =>
    api.post<{ message: string }>(
      `/assessments/${assessmentId}/results/email`,
      { email },
      guestToken ? { 'X-Guest-Token': guestToken } : undefined
    ),

  // ── Conversation mode ───────────────────────────────────────

  /** Start a conversation session */
  startConversation: (
    assessmentId: string,
    options?: {
      locale?: AssessmentLocale;
      speechLocale?: AssessmentLocale;
      ttsVoiceId?: string;
    }
  ) =>
    api.post<{
      session_id: string;
      current_question_index: number;
      status: string;
      locale: AssessmentLocale;
      speech_locale: AssessmentLocale;
      question: { text: string; locale: AssessmentLocale } | null;
    }>('/conversations/start', {
      assessment_id: assessmentId,
      locale: options?.locale,
      speech_locale: options?.speechLocale ?? options?.locale,
      tts_voice_id: options?.ttsVoiceId,
    }),

  /** Process a conversation turn */
  processTurn: (
    sessionId: string,
    payload: {
      content?: string;
      transcript?: string;
      transcriptLocale?: AssessmentLocale;
      transcriptConfidence?: number | null;
      audioPath?: string;
      durationSeconds?: number;
      clientProcessing?: {
        stt_engine?: string;
        tts_engine?: string;
        app_version?: string;
      };
    }
  ) =>
    api.post<ConversationTurnResponse>(`/conversations/${sessionId}/turn`, {
      content: payload.content,
      transcript: payload.transcript,
      transcript_locale: payload.transcriptLocale,
      transcript_confidence: payload.transcriptConfidence,
      audio_storage_path: payload.audioPath,
      duration_seconds: payload.durationSeconds,
      client_processing: payload.clientProcessing,
    }),

  /** Upload audio for a conversation turn */
  uploadConversationAudio: (
    sessionId: string,
    audioUri: string,
    fields?: {
      locale?: AssessmentLocale;
      speechLocale?: AssessmentLocale;
    }
  ) => {
    const uploadFields: Record<string, string> = {};

    if (fields?.locale) {
      uploadFields.locale = fields.locale;
    }

    if (fields?.speechLocale ?? fields?.locale) {
      uploadFields.speech_locale = fields?.speechLocale ?? fields?.locale ?? '';
    }

    return uploadAudio<ConversationAudioUploadResponse>(
      `/conversations/${sessionId}/audio`,
      audioUri,
      uploadFields
    );
  },

  /** Complete a conversation session */
  completeConversation: (sessionId: string) =>
    api.post<{ status: string }>(`/conversations/${sessionId}/complete`),

  /** Synthesize assistant text into speech audio */
  synthesizeConversationSpeech: (text: string, locale?: AssessmentLocale) =>
    api.post<ConversationSpeechResponse>('/conversations/speech', { text, locale }),
};
