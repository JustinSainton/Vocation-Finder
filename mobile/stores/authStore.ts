import { create } from 'zustand';

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

  setAuth: (token: string, user: User) => void;
  setGuest: () => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  token: null,
  user: null,
  isGuest: false,
  isLoading: false,

  setAuth: (token, user) =>
    set({ token, user, isGuest: false }),

  setGuest: () =>
    set({ token: null, user: null, isGuest: true }),

  logout: () =>
    set({ token: null, user: null, isGuest: false }),
}));
