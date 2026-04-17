/**
 * Modal de autenticación con efecto de carta de tarot.
 * Entrada: la carta "cae" desde arriba con perspectiva 3D (rotateX).
 * Cambio de modo login↔registro: flip de mazo (rotateY).
 * Sin Three.js — CSS perspective + Framer Motion puro.
 */
import { AnimatePresence, motion } from 'framer-motion';
import { createPortal } from 'react-dom';
import { useEffect, useRef } from 'react';
import { useAuthModalStore } from '../../stores/authModalStore';
import { useAuthStore } from '../../stores/authStore';
import { LoginForm } from '../forms/LoginForm';
import { RegisterForm } from '../forms/RegisterForm';
import { ForgotPasswordForm } from '../forms/ForgotPasswordForm';

// Dorso decorativo de la carta — aparece en el header del modal
function CardDorso() {
  return (
    <div className="auth-modal-dorso" aria-hidden="true">
      <svg className="auth-modal-dorso-star" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12,2 L13.5,8.3 L19.1,4.9 L15.7,10.5 L22,12 L15.7,13.5 L19.1,19.1 L13.5,15.7 L12,22 L10.5,15.7 L4.9,19.1 L8.3,13.5 L2,12 L8.3,10.5 L4.9,4.9 L10.5,8.3 Z" />
      </svg>
      <div className="auth-modal-dorso-ring" />
    </div>
  );
}

// Variantes de la carta completa (entrada y salida)
const cardVariants = {
  hidden: {
    opacity: 0,
    rotateX: -22,
    y: -56,
    scale: 0.90,
  },
  visible: {
    opacity: 1,
    rotateX: 0,
    y: 0,
    scale: 1,
  },
  exit: {
    opacity: 0,
    rotateX: 18,
    y: 56,
    scale: 0.90,
  },
};

// Variantes del flip entre formularios (login ↔ register)
const flipVariants = {
  enter: (dir: number) => ({ opacity: 0, rotateY: dir * 90 }),
  center: { opacity: 1, rotateY: 0 },
  exit:   (dir: number) => ({ opacity: 0, rotateY: dir * -90 }),
};

export function AuthCardModal() {
  const { isOpen, mode, switchMode, setMode, close } = useAuthModalStore();
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  // Cierra el modal al autenticarse
  useEffect(() => {
    if (isAuthenticated && isOpen) close();
  }, [isAuthenticated, isOpen, close]);

  // Bloquea scroll del body mientras el modal está abierto
  useEffect(() => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  // Cerrar con Escape
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') close(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [isOpen, close]);

  // Foco inicial al abrir
  const cardRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (isOpen) setTimeout(() => cardRef.current?.focus(), 60);
  }, [isOpen]);

  // Dirección del flip según transición entre modos
  const flipDir = useRef(1);
  const handleSwitch = () => {
    flipDir.current = mode === 'login' ? 1 : -1;
    switchMode();
  };
  const goToForgot = () => { flipDir.current = 1; setMode('forgot'); };
  const backToLogin = () => { flipDir.current = -1; setMode('login'); };

  return createPortal(
    <AnimatePresence>
      {isOpen ? (
        <motion.div
          className="auth-modal-backdrop"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.22 }}
          onClick={close}
          aria-modal="true"
          role="dialog"
          aria-label={mode === 'login' ? 'Iniciar sesión' : 'Crear cuenta'}
        >
          {/* Perspectiva 3D — contenedor sin clip */}
          <div className="auth-modal-perspective">
            <motion.div
              ref={cardRef}
              className="auth-modal-card"
              variants={cardVariants}
              initial="hidden"
              animate="visible"
              exit="exit"
              transition={{ type: 'spring', stiffness: 270, damping: 28 }}
              style={{ transformPerspective: 1100 }}
              onClick={(e) => e.stopPropagation()}
              tabIndex={-1}
            >
              {/* Encabezado con dorso de carta tarot */}
              <div className="auth-modal-header">
                <CardDorso />
                <button
                  type="button"
                  className="auth-modal-close"
                  onClick={close}
                  aria-label="Cerrar"
                >
                  ✕
                </button>
              </div>

              {/* Cuerpo — flip entre Login y Register */}
              <div className="auth-modal-body">
                <AnimatePresence mode="wait" custom={flipDir.current}>
                  <motion.div
                    key={mode}
                    custom={flipDir.current}
                    variants={flipVariants}
                    initial="enter"
                    animate="center"
                    exit="exit"
                    transition={{ duration: 0.28, ease: 'easeInOut' }}
                    style={{ transformPerspective: 900 }}
                  >
                    {mode === 'login' ? (
                      <LoginForm onSwitchMode={handleSwitch} onForgotPassword={goToForgot} />
                    ) : mode === 'register' ? (
                      <RegisterForm onSwitchMode={handleSwitch} onSuccess={handleSwitch} />
                    ) : (
                      <ForgotPasswordForm onBackToLogin={backToLogin} />
                    )}
                  </motion.div>
                </AnimatePresence>
              </div>
            </motion.div>
          </div>
        </motion.div>
      ) : null}
    </AnimatePresence>,
    document.body,
  );
}
