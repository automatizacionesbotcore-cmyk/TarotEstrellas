import { motion } from 'framer-motion';
import { useEffect } from 'react';
import { Link } from 'react-router-dom';

// Deterministic star positions — no random to avoid SSR/hydration issues
const STARS = [
  { left: '8%',  top: '14%', size: 4, delay: 0,   dur: 3.2 },
  { left: '88%', top: '10%', size: 3, delay: 0.8,  dur: 4.1 },
  { left: '74%', top: '62%', size: 5, delay: 1.5,  dur: 3.7 },
  { left: '5%',  top: '70%', size: 3, delay: 0.3,  dur: 4.5 },
  { left: '52%', top: '7%',  size: 4, delay: 2.0,  dur: 3.0 },
  { left: '93%', top: '44%', size: 3, delay: 1.2,  dur: 4.8 },
  { left: '30%', top: '86%', size: 4, delay: 0.6,  dur: 3.4 },
  { left: '64%', top: '28%', size: 3, delay: 1.8,  dur: 5.0 },
];

const INFO_CARDS = [
  {
    icon: '🌙',
    title: 'Sobre Chachita',
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
  visible: { opacity: 1, y: 0  },
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
      'Tarot, astrología y guía espiritual en videollamada con Chachita. Agendamiento simple, recordatorios y seguimiento personalizado.',
    );
  }, []);

  return (
    <main>
      {/* ── HERO ── */}
      <section className="hero hero-full" aria-label="Hero TarotEstrellas">
        {/* Floating star particles */}
        {STARS.map((s, i) => (
          <motion.span
            key={i}
            className="hero-star"
            style={{ left: s.left, top: s.top, width: s.size, height: s.size }}
            animate={{ opacity: [0.15, 1, 0.15], scale: [1, 1.6, 1] }}
            transition={{ duration: s.dur, delay: s.delay, repeat: Infinity, ease: 'easeInOut' }}
          />
        ))}

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
            Historial personal, recordatorios y guía continua con Chachita.
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

      {/* ── ORNAMENTAL DIVIDER ── */}
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
