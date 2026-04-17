import { create } from 'zustand';

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
    localStorage.setItem(STORAGE_KEY, nextTheme);
    set({ theme: nextTheme });
  },
  initialize: () => {
    const stored = localStorage.getItem(STORAGE_KEY) as Theme | null;
    const preferredDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = stored ?? (preferredDark ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', theme);
    set({ theme });
  },
}));
