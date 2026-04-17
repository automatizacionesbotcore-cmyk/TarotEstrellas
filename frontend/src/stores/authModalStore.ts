import { create } from 'zustand';

type Mode = 'login' | 'register';

type AuthModalState = {
  isOpen: boolean;
  mode: Mode;
  openLogin: () => void;
  openRegister: () => void;
  switchMode: () => void;
  close: () => void;
};

export const useAuthModalStore = create<AuthModalState>((set) => ({
  isOpen: false,
  mode: 'login',
  openLogin:    () => set({ isOpen: true, mode: 'login' }),
  openRegister: () => set({ isOpen: true, mode: 'register' }),
  switchMode:   () => set((s) => ({ mode: s.mode === 'login' ? 'register' : 'login' })),
  close:        () => set({ isOpen: false }),
}));
