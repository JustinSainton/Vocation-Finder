import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { authApi } from '../services/auth';

interface User {
  id: string;
  email: string;
  name?: string;
}

interface AuthState {
  token: string | null;
  user: User | null;
  isGuest: boolean;
  isLoading: boolean;
  error: string | null;

  setAuth: (token: string, user: User) => void;
  setGuest: () => void;

  login: (email: string, password: string) => Promise<void>;
  register: (
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
    guest_token?: string
  ) => Promise<void>;
  socialLogin: (provider: string, token: string) => Promise<void>;
  checkAuth: () => Promise<void>;
  logout: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      isGuest: false,
      isLoading: false,
      error: null,

      setAuth: (token, user) =>
        set({ token, user, isGuest: false, error: null }),

      setGuest: () =>
        set({ token: null, user: null, isGuest: true, error: null }),

      login: async (email, password) => {
        set({ isLoading: true, error: null });
        try {
          const data = await authApi.login(email, password);
          set({
            token: data.token,
            user: data.user,
            isGuest: false,
            isLoading: false,
          });
        } catch (err: any) {
          set({
            isLoading: false,
            error: err?.message ?? 'Login failed',
          });
          throw err;
        }
      },

      register: async (name, email, password, password_confirmation, guest_token?) => {
        set({ isLoading: true, error: null });
        try {
          const data = await authApi.register(
            name,
            email,
            password,
            password_confirmation,
            guest_token
          );
          set({
            token: data.token,
            user: data.user,
            isGuest: false,
            isLoading: false,
          });
        } catch (err: any) {
          set({
            isLoading: false,
            error: err?.message ?? 'Registration failed',
          });
          throw err;
        }
      },

      socialLogin: async (provider, token) => {
        set({ isLoading: true, error: null });
        try {
          const data = await authApi.socialLogin(provider, token);
          set({
            token: data.token,
            user: data.user,
            isGuest: false,
            isLoading: false,
          });
        } catch (err: any) {
          set({
            isLoading: false,
            error: err?.message ?? 'Social login failed',
          });
          throw err;
        }
      },

      checkAuth: async () => {
        const { token } = get();
        if (!token) return;

        set({ isLoading: true });
        try {
          const user = await authApi.me();
          set({ user, isLoading: false });
        } catch {
          // Token expired or invalid
          set({ token: null, user: null, isGuest: false, isLoading: false });
        }
      },

      logout: async () => {
        try {
          await authApi.logout();
        } catch {
          // Continue logout even if API call fails
        }
        set({ token: null, user: null, isGuest: false, error: null });
      },

      clearError: () => set({ error: null }),
    }),
    {
      name: 'auth-storage',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        isGuest: state.isGuest,
      }),
    }
  )
);
