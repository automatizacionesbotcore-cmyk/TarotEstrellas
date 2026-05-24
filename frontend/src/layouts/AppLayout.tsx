import { AnimatePresence, motion } from 'framer-motion';
import { useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Logo } from '../components/ui/Logo';
import { ThemeToggle } from '../components/ui/ThemeToggle';
import { api } from '../lib/api';
import { useAuthStore } from '../stores/authStore';
import { toast } from '../stores/toastStore';

const pageVariants = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0  },
  exit:    { opacity: 0, y: -10 },
};

export function AppLayout() {
  const user         = useAuthStore((s) => s.user);
  const clearSession = useAuthStore((s) => s.clearSession);
  const location     = useLocation();
  const navigate     = useNavigate();

  const [loggingOut,  setLoggingOut]  = useState(false);
  const [resending,   setResending]   = useState(false);
  const isPaymentFocus = /^\/app\/citas\/[^/]+\/pagar(?:-saldo)?$/.test(location.pathname);
  const isSalaFocus = /^\/app\/sala\//.test(location.pathname);
  const isAdminArea = /^\/app\/admin(?:\/|$)/.test(location.pathname);

  const handleLogout = async () => {
    setLoggingOut(true);
    try {
      await api.post('/auth/logout');
    } catch {
      // token inválido — limpiar de todas formas
    }
    clearSession();
    navigate('/', { replace: true });
    toast.info('Sesión cerrada.');
  };

  const handleResend = async () => {
    setResending(true);
    try {
      await api.post('/auth/resend-verification');
      toast.success('Enlace de verificación enviado. Revisa tu correo.');
    } catch {
      toast.error('No se pudo enviar el enlace. Intenta más tarde.');
    } finally {
      setResending(false);
    }
  };

  return (
    <div className={isPaymentFocus ? 'page-shell page-shell--payment-focus' : isSalaFocus ? 'page-shell page-shell--sala-focus' : isAdminArea ? 'page-shell page-shell--admin' : 'page-shell'}>
      <header className={isPaymentFocus ? 'main-header main-header--payment-focus' : isSalaFocus ? 'main-header main-header--sala-focus' : 'main-header'} role="banner">
        <Link to="/app" className="logo-link" aria-label="TarotEstrellas — inicio">
          <Logo />
        </Link>
        <nav aria-label="Navegación de la app">
          <NavLink to="/app" end className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>
            Dashboard
          </NavLink>
          <NavLink to="/app/mis-consultas" className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>
            Mis consultas
          </NavLink>
          <NavLink to="/app/membresia" className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>
            Membresía
          </NavLink>
          <NavLink to="/app/mi-cuenta" className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>
            Mi cuenta
          </NavLink>
          {useAuthStore((s) => s.isAdmin()) && (
            <NavLink to="/app/admin" className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>
              Administración
            </NavLink>
          )}
          <ThemeToggle />
          <button
            type="button"
            className="btn-secondary nav-logout"
            onClick={handleLogout}
            disabled={loggingOut}
          >
            {loggingOut ? 'Saliendo…' : 'Salir'}
          </button>
        </nav>
      </header>

      {user && !user.email_verified_at ? (
        <div className="verify-banner">
          <span>Tu correo aún no está verificado. Revisa tu bandeja para continuar.</span>
          <button
            type="button"
            className="verify-resend-btn"
            onClick={handleResend}
            disabled={resending}
          >
            {resending ? 'Enviando…' : 'Reenviar enlace'}
          </button>
        </div>
      ) : null}

      <AnimatePresence mode="wait" initial={false}>
        <motion.div
          key={location.pathname}
          variants={pageVariants}
          initial="initial"
          animate="animate"
          exit="exit"
          transition={{ duration: 0.35, ease: 'easeInOut' }}
        >
          <Outlet />
        </motion.div>
      </AnimatePresence>
    </div>
  );
}
