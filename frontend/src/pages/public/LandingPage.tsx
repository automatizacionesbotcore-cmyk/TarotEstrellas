import { motion } from 'framer-motion';
import { lazy, Suspense, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuthModalStore } from '../../stores/authModalStore';

const StarField = lazy(() => import('../../components/3d/StarField'));

// Estrellas CSS — más grandes, posiciones fijas para evitar hidratación
const STARS = [
  { left: '6%',  top: '12%', size: 14, delay: 0,    dur: 3.2 },
  { left: '91%', top: '8%',  size: 11, delay: 0.8,  dur: 4.1 },
  { left: '76%', top: '60%', size: 13, delay: 1.5,  dur: 3.7 },
  { left: '4%',  top: '72%', size: 9,  delay: 0.3,  dur: 4.5 },
  { left: '54%', top: '5%',  size: 12, delay: 2.0,  dur: 3.0 },
  { left: '94%', top: '42%', size: 8,  delay: 1.2,  dur: 4.8 },
  { left: '28%', top: '88%', size: 12, delay: 0.6,  dur: 3.4 },
  { left: '66%', top: '26%', size: 9,  delay: 1.8,  dur: 5.0 },
  { left: '42%', top: '70%', size: 10, delay: 2.4,  dur: 3.8 },
  { left: '18%', top: '46%', size: 7,  delay: 0.9,  dur: 4.2 },
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
  const openRegister = useAuthModalStore((s) => s.openRegister);

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
      {/* ── HERO con cielo estrellado 3D ── */}
      <section className="hero hero-full" aria-label="Hero TarotEstrellas">

        {/* Fondo 3D — solo modo oscuro (stars blancas invisibles en claro) */}
        <Suspense fallback={null}>
          <StarField />
        </Suspense>

        {/* Estrellas CSS — visibles en ambos modos (usan --accent) */}
        {STARS.map((s, i) => (
          <motion.span
            key={i}
            className="hero-star"
            style={{ left: s.left, top: s.top, width: s.size, height: s.size }}
            animate={{ opacity: [0.15, 1, 0.15], scale: [1, 1.55, 1] }}
            transition={{ duration: s.dur, delay: s.delay, repeat: Infinity, ease: 'easeInOut' }}
          />
        ))}

        <motion.div
          className="hero-content"
          initial="hidden"
          animate="visible"
          variants={stagger}
        >
          <motion.p className="hero-eyebrow" variants={fadeUp} transition={{ duration: 0.55 }}>
            ✦ Lecturas espirituales online ✦
          </motion.p>

          <motion.h1 className="hero-title" variants={fadeUp} transition={{ duration: 0.7 }}>
            Claridad para tus<br />
            <span className="hero-title-accent">decisiones más importantes</span>
          </motion.h1>

          <motion.p className="hero-tagline" variants={fadeUp} transition={{ duration: 0.65 }}>
            Tarot, astrología y carta astral en videollamada.<br />
            Historial personal, recordatorios y guía continua con Chachita.
          </motion.p>

          <motion.div className="cta-row" variants={fadeUp} transition={{ duration: 0.6 }}>
            <Link className="btn-primary btn-shimmer" to="/servicios">
              Ver servicios
            </Link>
            <button type="button" className="btn-primary btn-shimmer-alt" onClick={openRegister}>
              Crear cuenta gratis
            </button>
          </motion.div>
        </motion.div>
      </section>

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
