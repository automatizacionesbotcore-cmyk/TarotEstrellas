import { create } from 'zustand';
import { storageGet, storageSet } from '../lib/safeStorage';

type Theme = 'dark' | 'light';

type ThemeState = {
  theme: Theme;
  toggleTheme: () => void;
  initialize: () => void;
};

const STORAGE_KEY = 'tarotestrellas-theme';

export const useThemeStore = create<ThemeState>((set, get) => ({
  theme: 'dark',
  toggleTheme: () => {
    const nextTheme = get().theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nextTheme);
    storageSet('local', STORAGE_KEY, nextTheme);
    set({ theme: nextTheme });
  },
  initialize: () => {
    const stored = storageGet('local', STORAGE_KEY) as Theme | null;
    const preferredDark = typeof window !== 'undefined'
      ? window.matchMedia('(prefers-color-scheme: dark)').matches
      : true;
    const theme = stored ?? (preferredDark ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', theme);
    set({ theme });
  },
}));
