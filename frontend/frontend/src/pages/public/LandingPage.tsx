import { motion } from 'framer-motion';
import { lazy, Suspense, useEffect } from 'react';
import { Link } from 'react-router-dom';

// Lazy — carga solo cuando el componente entra al DOM (spec: lazy loading)
const StarField = lazy(() => import('../../components/3d/StarField'));

const INFO_CARDS = [
  {
    icon: '🌙',
    title: 'Sobre nuestra especialista',
    body: 'Más de 20 años guiando procesos personales con enfoque humano y práctico. Especialista en tarot, astrología natal y lectura de runas.',
  },
  {
    icon: '✨',
    title: 'Cómo funciona',
    body: 'Elige tu servicio, agenda una fecha y realiza tu consulta por videollamada. Tu historial queda guardado para seguimiento continuo.',
  },
  {
    icon: '🔮',
    title: 'Privacidad total',
    body: 'Tus datos y lecturas son completamente confidenciales. Te acompañamos con respeto, sin juicios y con absoluta discreción.',
  },
];

const fadeUp = {
  hidden:  { opacity: 0, y: 36 },
  visible: { opacity: 1, y: 0 },
};

const stagger = {
  hidden:  {},
  visible: { transition: { staggerChildren: 0.14 } },
};

export function LandingPage() {
  useEffect(() => {
    document.title = 'TarotEstrellas | Lecturas espirituales online';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }
    meta.setAttribute(
      'content',
      'Tarot, astrología y guía espiritual en videollamada con nuestra especialista. Agendamiento simple, recordatorios y seguimiento personalizado.',
    );
  }, []);

  return (
    <main>
      {/* ── HERO con cielo estrellado 3D ── */}
      <section className="hero hero-full" aria-label="Hero TarotEstrellas">
        {/* Fondo 3D (lazy, no bloquea render inicial) */}
        <Suspense fallback={null}>
          <StarField />
        </Suspense>

        <motion.div
          className="hero-content"
          initial="hidden"
          animate="visible"
          variants={stagger}
        >
          <motion.p
            className="hero-eyebrow"
            variants={fadeUp}
            transition={{ duration: 0.55 }}
          >
            ✦ Lecturas espirituales online ✦
          </motion.p>

          <motion.h1
            className="hero-title"
            variants={fadeUp}
            transition={{ duration: 0.7 }}
          >
            Claridad para tus<br />
            <span className="hero-title-accent">decisiones más importantes</span>
          </motion.h1>

          <motion.p
            className="hero-tagline"
            variants={fadeUp}
            transition={{ duration: 0.65 }}
          >
            Tarot, astrología y carta astral en videollamada.<br />
            Historial personal, recordatorios y guía continua con nuestra especialista.
          </motion.p>

          <motion.div
            className="cta-row"
            variants={fadeUp}
            transition={{ duration: 0.6 }}
          >
            <Link className="btn-primary btn-shimmer" to="/servicios">
              Ver servicios
            </Link>
            <Link className="btn-secondary" to="/auth/register">
              Crear cuenta gratis
            </Link>
          </motion.div>
        </motion.div>
      </section>

      {/* ── DIVIDER ── */}
      <p className="section-ornament" aria-hidden="true">——✦——</p>

      {/* ── INFO CARDS ── */}
      <motion.section
        className="grid-section"
        aria-label="Por qué TarotEstrellas"
        initial="hidden"
        whileInView="visible"
        viewport={{ once: true, margin: '-70px' }}
        variants={stagger}
      >
        {INFO_CARDS.map((card) => (
          <motion.article
            key={card.title}
            className="info-card"
            variants={fadeUp}
            transition={{ duration: 0.5 }}
          >
            <span className="info-card-icon" aria-hidden="true">{card.icon}</span>
            <h3>{card.title}</h3>
            <p>{card.body}</p>
          </motion.article>
        ))}
      </motion.section>
    </main>
  );
}
