import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';

export function NotFoundPage() {
  return (
    <main className="page-content not-found-page">
      <motion.div
        className="not-found-content"
        initial={{ opacity: 0, y: 30 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.55, ease: 'easeOut' }}
      >
        <p className="not-found-code">404</p>
        <h1 className="not-found-title">Página no encontrada</h1>
        <p className="not-found-sub">
          Las estrellas no logran guiarte a esta dirección.<br />
          Quizás el camino cambió o el enlace es incorrecto.
        </p>
        <div className="dash-actions" style={{ justifyContent: 'center' }}>
          <Link className="btn-primary btn-shimmer" to="/">Volver al inicio</Link>
          <Link className="btn-secondary" to="/servicios">Ver servicios</Link>
        </div>
      </motion.div>
    </main>
  );
}
