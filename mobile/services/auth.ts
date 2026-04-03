import { api } from './api';

interface UserOrganization {
  id: string;
  name: string;
  slug: string;
  role: 'admin' | 'mentor' | 'member';
}

interface UserData {
  id: string;
  email: string;
  name: string;
  role: 'individual' | 'admin' | 'org_admin';
  organizations: UserOrganization[];
}

interface AuthResponse {
  token: string;
  user: UserData;
}

export const authApi = {
  register: (
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
    guest_token?: string
  ) =>
    api.post<AuthResponse>('/auth/register', {
      name,
      email,
      password,
      password_confirmation,
      guest_token,
    }),

  login: (email: string, password: string) =>
    api.post<AuthResponse>('/auth/login', { email, password }),

  logout: () => api.post<void>('/auth/logout'),

  socialLogin: (provider: string, token: string) =>
    api.post<AuthResponse>(`/auth/social/${provider}`, { token }),

  forgotPassword: (email: string) =>
    api.post<{ message: string }>('/auth/forgot-password', { email }),

  me: () =>
    api.get<{ user: UserData }>('/auth/me'),
};
