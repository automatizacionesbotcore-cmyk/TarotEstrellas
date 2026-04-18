import { create } from 'zustand';

export type ToastType = 'success' | 'error' | 'info';

export type Toast = {
  id:      string;
  type:    ToastType;
  message: string;
};

type ToastState = {
  toasts:      Toast[];
  addToast:    (type: ToastType, message: string) => void;
  removeToast: (id: string) => void;
};

export const useToastStore = create<ToastState>((set) => ({
  toasts: [],
  addToast: (type, message) => {
    const id = crypto.randomUUID();
    set((s) => ({ toasts: [...s.toasts, { id, type, message }] }));
    setTimeout(() => {
      set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) }));
    }, 4200);
  },
  removeToast: (id) =>
    set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
}));

export const toast = {
  success: (message: string) => useToastStore.getState().addToast('success', message),
  error:   (message: string) => useToastStore.getState().addToast('error',   message),
  info:    (message: string) => useToastStore.getState().addToast('info',    message),
};
