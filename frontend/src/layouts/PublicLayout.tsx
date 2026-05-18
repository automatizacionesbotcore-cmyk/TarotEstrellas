import { AnimatePresence, motion } from 'framer-motion';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { Logo } from '../components/ui/Logo';
import { ThemeToggle } from '../components/ui/ThemeToggle';
import { useAuthModalStore } from '../stores/authModalStore';
import { useAuthStore } from '../stores/authStore';

const pageVariants = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  exit:    { opacity: 0, y: -10 },
};

export function PublicLayout() {
  const location        = useLocation();
  const openLogin       = useAuthModalStore((s) => s.openLogin);
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  return (
    <div className="page-shell">
      <header className="main-header" role="banner">
        <Link to="/" className="logo-link" aria-label="TarotEstrellas — inicio">
          <Logo />
        </Link>
        <nav aria-label="Navegación principal">
          <NavLink to="/servicios" className={({ isActive }) => isActive ? 'nav-link active' : 'nav-link'}>Servicios</NavLink>
          {isAuthenticated ? (
            <Link to="/app" className="btn-secondary" style={{ padding: '0.45rem 1rem' }}>
              Mi panel
            </Link>
          ) : (
            <button type="button" className="btn-secondary" style={{ padding: '0.45rem 1rem' }} onClick={openLogin}>
              Ingresar
            </button>
          )}
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

      <footer className="site-footer" role="contentinfo">
        <div className="footer-inner">
          <div className="footer-brand">
            <Logo />
            <p>Lecturas espirituales online con acompañamiento personalizado.</p>
          </div>

          <nav className="footer-links" aria-label="Enlaces del sitio">
            <div>
              <h4>Servicios</h4>
              <Link to="/servicios">Ver catálogo</Link>
              <Link to="/servicios?categoria=tarot">Tarot</Link>
              <Link to="/servicios?categoria=astrologia">Astrología</Link>
            </div>
            <div>
              <h4>Cuenta</h4>
              {isAuthenticated ? (
                <Link to="/app">Mi panel</Link>
              ) : (
                <button type="button" onClick={openLogin}>Iniciar sesión</button>
              )}
            </div>
            <div>
              <h4>Legal</h4>
              <Link to="/legal/terminos">Términos y condiciones</Link>
              <Link to="/legal/privacidad">Política de privacidad</Link>
              <Link to="/legal/cookies">Política de cookies</Link>
              <Link to="/legal/reembolsos">Política de reembolsos</Link>
            </div>
          </nav>
        </div>

        <div className="footer-bottom">
          <p>
            © {new Date().getFullYear()} TarotEstrellas · Todos los derechos reservados · Desarrollado por{' '}
            <a href="https://automatizatech.cl" target="_blank" rel="noreferrer">automatizatech.cl</a>
          </p>
        </div>
      </footer>
    </div>
  );
}
