import { create } from 'zustand';

type Mode = 'login' | 'register' | 'forgot';

type AuthModalState = {
  isOpen: boolean;
  mode: Mode;
  openLogin:        () => void;
  openRegister:     () => void;
  openForgot:       () => void;
  switchMode:       () => void;
  setMode:          (mode: Mode) => void;
  close:            () => void;
};

export const useAuthModalStore = create<AuthModalState>((set) => ({
  isOpen: false,
  mode: 'login',
  openLogin:    () => set({ isOpen: true, mode: 'login' }),
  openRegister: () => set({ isOpen: true, mode: 'register' }),
  openForgot:   () => set({ isOpen: true, mode: 'forgot' }),
  switchMode:   () => set((s) => ({ mode: s.mode === 'login' ? 'register' : 'login' })),
  setMode:      (mode) => set({ mode }),
  close:        () => set({ isOpen: false }),
}));
