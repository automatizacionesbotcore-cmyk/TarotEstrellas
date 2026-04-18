import { AnimatePresence, motion } from 'framer-motion';
import { createPortal } from 'react-dom';
import { useToastStore } from '../../stores/toastStore';

const ICONS = { success: '✓', error: '✕', info: 'ℹ' };

export function Toaster() {
  const { toasts, removeToast } = useToastStore();

  return createPortal(
    <div className="toaster" aria-live="polite" aria-atomic="false">
      <AnimatePresence>
        {toasts.map((t) => (
          <motion.div
            key={t.id}
            className={`toast toast-${t.type}`}
            initial={{ opacity: 0, y: 28, scale: 0.94 }}
            animate={{ opacity: 1, y: 0,  scale: 1    }}
            exit={{    opacity: 0, y: -14, scale: 0.94 }}
            transition={{ type: 'spring', duration: 0.35, bounce: 0 }}
            role="alert"
          >
            <span className="toast-icon" aria-hidden="true">{ICONS[t.type]}</span>
            <span className="toast-message">{t.message}</span>
            <button
              type="button"
              className="toast-close"
              onClick={() => removeToast(t.id)}
              aria-label="Cerrar"
            >
              ×
            </button>
          </motion.div>
        ))}
      </AnimatePresence>
    </div>,
    document.body,
  );
}
