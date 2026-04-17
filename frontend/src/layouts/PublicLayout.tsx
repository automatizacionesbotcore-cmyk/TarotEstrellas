import { AnimatePresence, motion } from 'framer-motion';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { Logo } from '../components/ui/Logo';
import { ThemeToggle } from '../components/ui/ThemeToggle';

const pageVariants = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  exit:    { opacity: 0, y: -10 },
};

export function PublicLayout() {
  const location = useLocation();

  return (
    <div className="page-shell">
      <header className="main-header" role="banner">
        <Link to="/" className="logo-link" aria-label="TarotEstrellas — inicio">
          <Logo />
        </Link>
        <nav aria-label="Navegación principal">
          <Link to="/servicios">Servicios</Link>
          <Link to="/auth/login">Ingresar</Link>
          <ThemeToggle />
        </nav>
      </header>

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
