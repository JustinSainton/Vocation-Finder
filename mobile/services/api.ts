import Constants from 'expo-constants';
import { useAuthStore } from '../stores/authStore';

const BASE_URL =
  Constants.expoConfig?.extra?.apiUrl ?? 'http://localhost:3000';

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
