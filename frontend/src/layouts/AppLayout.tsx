import { AnimatePresence, motion } from 'framer-motion';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { Logo } from '../components/ui/Logo';
import { ThemeToggle } from '../components/ui/ThemeToggle';
import { useAuthStore } from '../stores/authStore';

const pageVariants = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  exit:    { opacity: 0, y: -10 },
};

export function AppLayout() {
  const user = useAuthStore((state) => state.user);
  const location = useLocation();

  return (
    <div className="page-shell">
      <header className="main-header" role="banner">
        <Link to="/app" className="logo-link" aria-label="TarotEstrellas — inicio">
          <Logo />
        </Link>
        <nav aria-label="Navegación de la app">
          <Link to="/app">Dashboard</Link>
          <Link to="/app/mis-consultas">Mis consultas</Link>
          <Link to="/app/mi-cuenta">Mi cuenta</Link>
          <ThemeToggle />
        </nav>
      </header>

      {user && !user.email_verified_at ? (
        <div className="verify-banner">
          Tu correo aún no está verificado. Revisa tu bandeja para continuar.
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
