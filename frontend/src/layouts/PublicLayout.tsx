import { AnimatePresence, motion } from 'framer-motion';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { Logo } from '../components/ui/Logo';
import { ThemeToggle } from '../components/ui/ThemeToggle';
import { useAuthModalStore } from '../stores/authModalStore';

const pageVariants = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  exit:    { opacity: 0, y: -10 },
};

export function PublicLayout() {
  const location    = useLocation();
  const openLogin   = useAuthModalStore((s) => s.openLogin);

  return (
    <div className="page-shell">
      <header className="main-header" role="banner">
        <Link to="/" className="logo-link" aria-label="TarotEstrellas — inicio">
          <Logo />
        </Link>
        <nav aria-label="Navegación principal">
          <NavLink to="/servicios" className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>Servicios</NavLink>
          <button type="button" className="btn-secondary" style={{ padding: '0.45rem 1rem' }} onClick={openLogin}>
            Ingresar
          </button>
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
