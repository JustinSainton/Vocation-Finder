import Constants from 'expo-constants';
import { useAuthStore } from '../stores/authStore';

const BASE_URL =
  Constants.expoConfig?.extra?.apiUrl ?? 'http://localhost:8000/api/v1';

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

  const response = await fetch(url, config);

  if (!response.ok) {
    const error: ApiError = {
      message: `Request failed: ${response.statusText}`,
      status: response.status,
    };

    try {
      const data = await response.json();
      error.message = data.message ?? data.error ?? error.message;
    } catch {
      // keep default message
    }

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
      return { status: 'analyzing', profile: null };
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

  /** Complete a conversation session */
  completeConversation: (sessionId: string) =>
    api.post<{ status: string }>(`/conversations/${sessionId}/complete`),
};
