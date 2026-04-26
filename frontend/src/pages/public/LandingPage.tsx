import { motion, AnimatePresence } from 'framer-motion';
import { lazy, Suspense, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../lib/api';
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

type FeaturedServicio = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_moneda: number | null;
  moneda: string;
};

const FAQS = [
  {
    q: '¿Cómo funciona una consulta por videollamada?',
    a: 'Eliges el servicio, agendas una fecha disponible y realizas tu consulta desde cualquier lugar con conexión a internet. La sesión queda grabada y puedes revisarla cuando quieras.',
  },
  {
    q: '¿Necesito experiencia previa con el tarot o la astrología?',
    a: 'No. Las lecturas están diseñadas para cualquier persona, sin importar su nivel de conocimiento. Nuestra especialista guía la sesión de forma clara y accesible.',
  },
  {
    q: '¿Mis datos y lecturas son privados?',
    a: 'Sí. Toda la información que compartes es estrictamente confidencial. Las grabaciones solo son accesibles para ti y se eliminan automáticamente a los 90 días.',
  },
  {
    q: '¿Cómo se realiza el pago?',
    a: 'Aceptamos tarjeta de crédito/débito (Stripe) y transferencia bancaria para clientes en Chile. Al reservar se cobra un abono del 20% para asegurar tu hora, y el saldo restante antes de la sesión.',
  },
  {
    q: '¿Puedo reagendar o cancelar mi cita?',
    a: 'Sí, puedes reagendar hasta 24 horas antes sin costo. Las cancelaciones dentro de las 24 horas tienen una política de reembolso parcial. Consulta nuestra política completa para más detalles.',
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

function formatPrice(centavos: number | null, moneda: string) {
  if (centavos === null) return null;
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: moneda,
    maximumFractionDigits: moneda === 'CLP' ? 0 : 2,
  }).format(centavos / 100);
}

export function LandingPage() {
  const openRegister = useAuthModalStore((s) => s.openRegister);
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const { data: featuredData } = useQuery<{ data: FeaturedServicio[] }>({
    queryKey: ['tipos-consulta', 'featured'],
    queryFn: async () => (await api.get('/public/tipos-consulta', { params: { moneda: 'CLP' } })).data,
    staleTime: 5 * 60 * 1000,
  });

  const featuredServices = featuredData?.data.slice(0, 3) ?? [];

  useEffect(() => {
    document.title = 'TarotEstrellas | Lecturas espirituales online';

    const setMeta = (name: string, content: string, prop = false) => {
      const attr = prop ? 'property' : 'name';
      let el = document.querySelector(`meta[${attr}="${name}"]`);
      if (!el) { el = document.createElement('meta'); el.setAttribute(attr, name); document.head.appendChild(el); }
      el.setAttribute('content', content);
    };

    const desc = 'Tarot, astrología y guía espiritual en videollamada con nuestra especialista. Agendamiento simple, recordatorios y seguimiento personalizado.';
    setMeta('description', desc);
    setMeta('og:title', 'TarotEstrellas | Lecturas espirituales online', true);
    setMeta('og:description', desc, true);
    setMeta('og:type', 'website', true);
    setMeta('og:url', window.location.href, true);
    setMeta('twitter:card', 'summary_large_image');
    setMeta('twitter:title', 'TarotEstrellas | Lecturas espirituales online');
    setMeta('twitter:description', desc);
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
            animate={{ opacity: [0.45, 1, 0.45], scale: [1, 1.55, 1] }}
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
            Historial personal, recordatorios y guía continua con nuestra especialista.
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

      <p className="section-ornament" aria-hidden="true">——✦——</p>

      {/* ── SERVICIOS DESTACADOS ── */}
      <section className="landing-section" aria-label="Servicios destacados">
        <motion.div
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-60px' }}
          variants={stagger}
        >
          <motion.h2 className="section-heading" variants={fadeUp} transition={{ duration: 0.5 }}>
            Servicios más consultados
          </motion.h2>
          <motion.p className="section-sub" variants={fadeUp} transition={{ duration: 0.5 }}>
            Elige el que mejor resuene con tu momento actual.
          </motion.p>

          <motion.div className="cards-grid" variants={stagger}>
            {featuredServices.map((s, i) => {
              const precio = formatPrice(s.precio_moneda, s.moneda);
              return (
                <motion.article
                  key={s.slug}
                  className="service-card"
                  variants={fadeUp}
                  custom={i}
                  transition={{ duration: 0.5 }}
                  whileHover={{ y: -4, transition: { duration: 0.25 } }}
                >
                  <h3>{s.nombre}</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', flex: 1 }}>{s.descripcion}</p>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                    <span>⏱ {s.duracion_minutos} min</span>
                    {precio && <strong style={{ color: 'var(--accent)' }}>{precio}</strong>}
                  </div>
                  <Link
                    to={`/servicios/${s.slug}`}
                    className="btn-secondary"
                    style={{ marginTop: 'auto', textAlign: 'center', justifyContent: 'center' }}
                  >
                    Ver detalle
                  </Link>
                </motion.article>
              );
            })}
          </motion.div>

          <motion.div
            variants={fadeUp}
            transition={{ duration: 0.5 }}
            style={{ textAlign: 'center', marginTop: '1.8rem' }}
          >
            <Link to="/servicios" className="btn-primary btn-shimmer">
              Ver todos los servicios
            </Link>
          </motion.div>
        </motion.div>
      </section>

      <p className="section-ornament" aria-hidden="true">——✦——</p>

      {/* ── FAQs ── */}
      <section className="landing-section" aria-label="Preguntas frecuentes">
        <motion.div
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-60px' }}
          variants={stagger}
        >
          <motion.h2 className="section-heading" variants={fadeUp} transition={{ duration: 0.5 }}>
            Preguntas frecuentes
          </motion.h2>
          <motion.p className="section-sub" variants={fadeUp} transition={{ duration: 0.5 }}>
            Todo lo que necesitas saber antes de tu primera consulta.
          </motion.p>

          <motion.div className="faq-list" variants={stagger}>
            {FAQS.map((faq, i) => (
              <motion.div key={i} className="faq-item" variants={fadeUp} transition={{ duration: 0.45 }}>
                <button
                  className="faq-trigger"
                  aria-expanded={openFaq === i}
                  onClick={() => setOpenFaq(openFaq === i ? null : i)}
                >
                  <span>{faq.q}</span>
                  <span className={`faq-chevron${openFaq === i ? ' open' : ''}`} aria-hidden="true">▾</span>
                </button>
                <AnimatePresence initial={false}>
                  {openFaq === i && (
                    <motion.div
                      className="faq-body"
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      transition={{ duration: 0.28, ease: 'easeInOut' }}
                    >
                      <p>{faq.a}</p>
                    </motion.div>
                  )}
                </AnimatePresence>
              </motion.div>
            ))}
          </motion.div>
        </motion.div>
      </section>

      <p className="section-ornament" aria-hidden="true">——✦——</p>

      {/* ── CTA FINAL ── */}
      <motion.section
        className="landing-section landing-cta"
        aria-label="Llamada a la acción"
        initial={{ opacity: 0, y: 30 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: '-60px' }}
        transition={{ duration: 0.6 }}
      >
        <h2 className="section-heading">¿Lista para tu primera lectura?</h2>
        <p className="section-sub">Crea tu cuenta gratis y agenda cuando quieras.</p>
        <div className="cta-row" style={{ justifyContent: 'center' }}>
          <button type="button" className="btn-primary btn-shimmer" onClick={openRegister}>
            Comenzar ahora
          </button>
          <Link to="/servicios" className="btn-secondary">
            Explorar servicios
          </Link>
        </div>
      </motion.section>
    </main>
  );
}
