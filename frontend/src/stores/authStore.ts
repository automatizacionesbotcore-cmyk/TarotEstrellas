import { create } from 'zustand';
import { api } from '../lib/api';

export type User = {
  uuid?: string;
  email: string;
  nombre?: string | null;
  email_verified_at?: string | null;
  roles?: string[];
  perfil_completo?: boolean;
};

type AuthState = {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  setSession: (token: string, user: User) => void;
  clearSession: () => void;
  refreshUser: () => Promise<void>;
  isAdmin: () => boolean;
};

const TOKEN_KEY = 'tarotestrellas-token';
const USER_KEY  = 'tarotestrellas-user';

type BackendRole = string | { nombre: string };

function normalizeRoles(roles: unknown): string[] {
  if (!Array.isArray(roles)) return [];
  return roles.map((r: BackendRole) => (typeof r === 'string' ? r : r.nombre));
}

function normalizeUser(raw: Record<string, unknown>): User {
  const profile = raw.profile as Record<string, unknown> | undefined;
  return {
    uuid: (raw.uuid as string) ?? undefined,
    email: raw.email as string,
    nombre: (profile?.nombre as string) ?? (raw.name as string) ?? null,
    email_verified_at: (raw.email_verified_at as string) ?? null,
    roles: normalizeRoles(raw.roles),
    perfil_completo: Boolean(raw.perfil_completo),
  };
}

function readStoredUser(): User | null {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

export const useAuthStore = create<AuthState>((set, get) => ({
  token: localStorage.getItem(TOKEN_KEY),
  user: readStoredUser(),
  isAuthenticated: Boolean(localStorage.getItem(TOKEN_KEY)),

  setSession: (token, rawUser) => {
    const user = normalizeUser(rawUser as unknown as Record<string, unknown>);
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    set({ token, user, isAuthenticated: true });
  },

  clearSession: () => {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    set({ token: null, user: null, isAuthenticated: false });
  },

  refreshUser: async () => {
    const token = get().token;
    if (!token) return;
    try {
      const response = await api.get('/user');
      const user = normalizeUser(response.data as Record<string, unknown>);
      localStorage.setItem(USER_KEY, JSON.stringify(user));
      set({ user });
    } catch {
      // si el token está caducado el interceptor 401 hará clearSession + redirect
    }
  },

  isAdmin: () => {
    const user = get().user;
    return Array.isArray(user?.roles) &&
      (user.roles.includes('admin_especialista') || user.roles.includes('super_admin'));
  },
}));
