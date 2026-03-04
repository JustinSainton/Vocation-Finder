import Constants from 'expo-constants';
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
const fallbackApiUrl = 'http://127.0.0.1:8000';

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
  body?: unknown
): Promise<T> {
  const url = `${BASE_URL}${endpoint}`;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...getAuthHeaders(),
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
  get: <T>(endpoint: string) => request<T>(endpoint, 'GET'),

  post: <T>(endpoint: string, body?: unknown) =>
    request<T>(endpoint, 'POST', body),

  put: <T>(endpoint: string, body?: unknown) =>
    request<T>(endpoint, 'PUT', body),

  patch: <T>(endpoint: string, body?: unknown) =>
    request<T>(endpoint, 'PATCH', body),

  delete: <T>(endpoint: string) => request<T>(endpoint, 'DELETE'),
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
  sort_order: number;
  category_name: string;
  question_text: string;
  conversation_prompt: string | null;
  follow_up_prompts: string[];
}

export interface Assessment {
  id: string;
  status: 'in_progress' | 'analyzing' | 'completed' | 'failed';
  mode: 'written' | 'conversation';
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
  current_question_index: number;
  is_follow_up: boolean;
  is_complete: boolean;
  reasoning: string;
}

export interface ConversationAudioUploadResponse {
  audio_path: string;
  audio_disk?: string;
  transcript: string;
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
  getQuestions: () => api.get<{ data: Question[] }>('/questions'),

  /** Create a new assessment session */
  createAssessment: (mode: 'written' | 'conversation') =>
    api.post<Assessment>('/assessments', { mode }),

  /** Save an answer for a specific question */
  saveAnswer: (assessmentId: string, questionId: string, responseText: string, guestToken?: string) => {
    const headers: Record<string, string> = {};
    if (guestToken) headers['X-Guest-Token'] = guestToken;
    return api.post<{ id: string }>(`/assessments/${assessmentId}/answers`, {
      question_id: questionId,
      response_text: responseText,
    });
  },

  /** Mark the assessment as complete, triggering synthesis */
  completeAssessment: (assessmentId: string, guestToken?: string) =>
    api.post<{ status: string }>(`/assessments/${assessmentId}/complete`),

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
  emailResults: (assessmentId: string, email: string) =>
    api.post<{ message: string }>(`/assessments/${assessmentId}/results/email`, { email }),

  // ── Conversation mode ───────────────────────────────────────

  /** Start a conversation session */
  startConversation: (assessmentId: string) =>
    api.post<{ session_id: string; current_question_index: number; status: string }>(
      '/conversations/start',
      { assessment_id: assessmentId }
    ),

  /** Process a conversation turn */
  processTurn: (sessionId: string, content: string, audioPath?: string, duration?: number) =>
    api.post<ConversationTurnResponse>(`/conversations/${sessionId}/turn`, {
      content,
      audio_storage_path: audioPath,
      duration_seconds: duration,
    }),

  /** Upload audio for a conversation turn */
  uploadConversationAudio: (sessionId: string, audioUri: string) =>
    uploadAudio<ConversationAudioUploadResponse>(
      `/conversations/${sessionId}/audio`,
      audioUri
    ),

  /** Complete a conversation session */
  completeConversation: (sessionId: string) =>
    api.post<{ status: string }>(`/conversations/${sessionId}/complete`),

  /** Synthesize assistant text into speech audio */
  synthesizeConversationSpeech: (text: string) =>
    api.post<ConversationSpeechResponse>('/conversations/speech', { text }),
};
