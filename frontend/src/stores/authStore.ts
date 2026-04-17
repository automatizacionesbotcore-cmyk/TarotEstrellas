import { create } from 'zustand';

type User = {
  uuid?: string;
  email: string;
  nombre?: string | null;
  email_verified_at?: string | null;
  roles?: string[];
};

type AuthState = {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  setSession: (token: string, user: User) => void;
  clearSession: () => void;
};

const TOKEN_KEY = 'tarotestrellas-token';

export const useAuthStore = create<AuthState>((set) => ({
  token: localStorage.getItem(TOKEN_KEY),
  user: null,
  isAuthenticated: Boolean(localStorage.getItem(TOKEN_KEY)),
  setSession: (token, user) => {
    localStorage.setItem(TOKEN_KEY, token);
    set({ token, user, isAuthenticated: true });
  },
  clearSession: () => {
    localStorage.removeItem(TOKEN_KEY);
    set({ token: null, user: null, isAuthenticated: false });
  },
}));
