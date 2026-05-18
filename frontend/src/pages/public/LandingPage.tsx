import { motion, AnimatePresence } from 'framer-motion';
import { lazy, Suspense, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowRight,
  CalendarCheck,
  ChevronDown,
  Clock,
  CreditCard,
  Lock,
  Moon,
  Shield,
  Sparkles,
  Star,
  Video,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
    icon: Moon,
    title: 'Lecturas con contexto',
    body: 'Más de 20 años guiando procesos personales con tarot, astrología natal y lectura de runas.',
  },
  {
    icon: Sparkles,
    title: 'Seguimiento continuo',
    body: 'Tu historial queda guardado para revisar avances, acuerdos y próximos pasos cuando lo necesites.',
  },
  {
    icon: Shield,
    title: 'Privacidad total',
    body: 'Tus datos, lecturas y grabaciones son confidenciales, con acceso privado desde tu cuenta.',
  },
] satisfies { icon: LucideIcon; title: string; body: string }[];

const TRUST_POINTS = [
  { icon: Video, label: 'Consulta por videollamada' },
  { icon: Lock, label: 'Espacio privado y confidencial' },
  { icon: CalendarCheck, label: 'Agenda simple con recordatorios' },
] satisfies { icon: LucideIcon; label: string }[];

const FLOW_STEPS = [
  {
    icon: Star,
    title: 'Elige tu lectura',
    body: 'Compara servicios, duración y foco de la consulta antes de reservar.',
  },
  {
    icon: CalendarCheck,
    title: 'Reserva tu horario',
    body: 'Selecciona una disponibilidad y confirma con un abono inicial.',
  },
  {
    icon: Video,
    title: 'Conéctate y revisa',
    body: 'Realiza la sesión online y conserva tu historial para seguimiento.',
  },
] satisfies { icon: LucideIcon; title: string; body: string }[];

type FeaturedServicio = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_moneda: number | null;
  precio_referencial_centavos?: number | null;
  moneda: string;
};

type Especialista = {
  id: number;
  slug: string;
  nombre: string;
  especialidad: string;
  bio: string | null;
  avatar_url: string | null;
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
    a: 'Aceptamos PayPal y transferencia bancaria para clientes en Chile. Al reservar se cobra un abono del 20% para asegurar tu hora, y el saldo restante antes de la sesión.',
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

function formatPrice(centavos: number | null | undefined, moneda: string) {
  if (centavos === null || centavos === undefined || Number.isNaN(Number(centavos))) return null;
  const monedaSafe = moneda || 'CLP';
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: monedaSafe,
    maximumFractionDigits: monedaSafe === 'CLP' ? 0 : 2,
  }).format(Number(centavos) / 100);
}

export function LandingPage() {
  const openRegister = useAuthModalStore((s) => s.openRegister);
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const { data: featuredData } = useQuery<{ data: FeaturedServicio[] }>({
    queryKey: ['tipos-consulta', 'featured'],
    queryFn: async () => (await api.get('/public/tipos-consulta', { params: { moneda: 'CLP' } })).data,
    staleTime: 5 * 60 * 1000,
  });

  const { data: especialistasData } = useQuery<{ data: Especialista[] }>({
    queryKey: ['especialistas', 'landing'],
    queryFn: async () => (await api.get('/public/especialistas')).data,
    staleTime: 10 * 60 * 1000,
  });

  const featuredServices = featuredData?.data.slice(0, 3) ?? [];
  const especialistas = especialistasData?.data ?? [];

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
    <main className="landing-page">
      <section className="hero hero-full landing-hero" aria-label="TarotEstrellas">

        <Suspense fallback={null}>
          <StarField />
        </Suspense>

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
          className="landing-hero-grid"
          initial="hidden"
          animate="visible"
          variants={stagger}
        >
          <div className="hero-content">
            <motion.p className="hero-eyebrow" variants={fadeUp} transition={{ duration: 0.55 }}>
              Lecturas espirituales online
            </motion.p>

            <motion.h1 className="hero-title" variants={fadeUp} transition={{ duration: 0.7 }}>
              TarotEstrellas
              <span className="hero-title-accent">claridad para decidir con calma</span>
            </motion.h1>

            <motion.p className="hero-tagline" variants={fadeUp} transition={{ duration: 0.65 }}>
              Tarot, astrología y guía espiritual por videollamada, con agenda simple,
              acompañamiento humano y seguimiento privado desde tu cuenta.
            </motion.p>

            <motion.div className="cta-row" variants={fadeUp} transition={{ duration: 0.6 }}>
              <Link className="btn-primary btn-shimmer" to="/servicios">
                Ver servicios <ArrowRight size={17} strokeWidth={2.1} aria-hidden="true" />
              </Link>
              <button type="button" className="btn-primary btn-shimmer-alt" onClick={openRegister}>
                Crear cuenta gratis
              </button>
            </motion.div>

            <motion.ul className="landing-trust-list" variants={fadeUp} transition={{ duration: 0.55 }}>
              {TRUST_POINTS.map(({ icon: Icon, label }) => (
                <li key={label}>
                  <Icon size={16} strokeWidth={2.1} aria-hidden="true" />
                  <span>{label}</span>
                </li>
              ))}
            </motion.ul>
          </div>

          <motion.aside className="landing-hero-panel" variants={fadeUp} transition={{ duration: 0.7 }} aria-label="Resumen de experiencia">
            <div className="hero-panel-card">
              <p className="hero-panel-kicker">Próxima lectura</p>
              <h2>Consulta espiritual online</h2>
              <div className="hero-panel-meta">
                <span><Clock size={15} aria-hidden="true" /> 45 a 60 min</span>
                <span><CreditCard size={15} aria-hidden="true" /> Abono 20%</span>
              </div>
              <div className="hero-panel-divider" />
              <ul className="hero-panel-list">
                <li><span /> Agenda disponible desde el catálogo</li>
                <li><span /> Sesión privada por videollamada</li>
                <li><span /> Historial para seguimiento personal</li>
              </ul>
            </div>
          </motion.aside>
        </motion.div>
      </section>

      <motion.section
        className="landing-section landing-intro"
        aria-label="Por qué TarotEstrellas"
        initial="hidden"
        whileInView="visible"
        viewport={{ once: true, margin: '-70px' }}
        variants={stagger}
      >
        <motion.div className="landing-section-header" variants={fadeUp} transition={{ duration: 0.5 }}>
          <p className="section-kicker">Acompañamiento claro y reservado</p>
          <h2 className="section-heading">Una lectura espiritual que termina con próximos pasos</h2>
          <p className="section-sub">
            La experiencia está pensada para que encuentres orientación, reserves sin fricción
            y puedas volver a lo conversado cuando necesites continuidad.
          </p>
        </motion.div>

        <motion.div className="grid-section landing-info-grid" variants={stagger}>
          {INFO_CARDS.map(({ icon: Icon, title, body }) => (
            <motion.article
              key={title}
              className="info-card landing-info-card"
              variants={fadeUp}
              transition={{ duration: 0.5 }}
            >
              <span className="info-card-icon" aria-hidden="true"><Icon size={22} strokeWidth={2} /></span>
              <h3>{title}</h3>
              <p>{body}</p>
            </motion.article>
          ))}
        </motion.div>
      </motion.section>

      <section className="landing-section" aria-label="Servicios destacados">
        <motion.div
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-60px' }}
          variants={stagger}
        >
          <motion.div className="landing-section-header split" variants={fadeUp} transition={{ duration: 0.5 }}>
            <div>
              <p className="section-kicker">Servicios destacados</p>
              <h2 className="section-heading">Elige la consulta que conversa con tu momento</h2>
            </div>
            <Link to="/servicios" className="btn-secondary landing-section-action">
              Ver catálogo <ArrowRight size={16} aria-hidden="true" />
            </Link>
          </motion.div>

          <motion.div className="cards-grid" variants={stagger}>
            {featuredServices.map((s, i) => {
              const centavos = (s.precio_moneda ?? s.precio_referencial_centavos) ?? null;
              const precio = formatPrice(centavos, s.moneda);
              return (
                <motion.article
                  key={s.slug}
                  className="service-card"
                  variants={fadeUp}
                  custom={i}
                  transition={{ duration: 0.5 }}
                  whileHover={{ y: -4, transition: { duration: 0.25 } }}
                >
                  <span className="service-pill">Consulta online</span>
                  <h3>{s.nombre}</h3>
                  <p className="service-card-desc">{s.descripcion}</p>
                  <div className="service-card-meta">
                    <span><Clock size={15} aria-hidden="true" /> {s.duracion_minutos} min</span>
                    {precio && <strong>{precio}</strong>}
                  </div>
                  <Link
                    to={`/servicios/${s.slug}`}
                    className="btn-secondary"
                  >
                    Ver detalle <ArrowRight size={16} aria-hidden="true" />
                  </Link>
                </motion.article>
              );
            })}
          </motion.div>

          {featuredServices.length === 0 && (
            <motion.div className="landing-empty-state" variants={fadeUp} transition={{ duration: 0.5 }}>
              <p>El catálogo se está cargando. Puedes ir directo a servicios para ver todas las opciones disponibles.</p>
              <Link to="/servicios" className="btn-primary btn-shimmer">
                Ver servicios <ArrowRight size={16} aria-hidden="true" />
              </Link>
            </motion.div>
          )}
        </motion.div>
      </section>

      <section className="landing-section" aria-label="Cómo funciona">
        <motion.div
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-60px' }}
          variants={stagger}
        >
          <motion.div className="landing-section-header" variants={fadeUp} transition={{ duration: 0.5 }}>
            <p className="section-kicker">Cómo funciona</p>
            <h2 className="section-heading">De la inquietud a una sesión reservada en tres pasos</h2>
          </motion.div>

          <motion.div className="landing-flow" variants={stagger}>
            {FLOW_STEPS.map(({ icon: Icon, title, body }, index) => (
              <motion.article className="flow-step" key={title} variants={fadeUp} transition={{ duration: 0.5 }}>
                <span className="flow-step-number">{String(index + 1).padStart(2, '0')}</span>
                <span className="flow-step-icon" aria-hidden="true"><Icon size={22} strokeWidth={2} /></span>
                <h3>{title}</h3>
                <p>{body}</p>
              </motion.article>
            ))}
          </motion.div>
        </motion.div>
      </section>

      {especialistas.length > 0 && (
        <section className="landing-section" aria-label="Nuestros especialistas">
          <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: '-60px' }}
            variants={stagger}
          >
            <motion.div className="landing-section-header" variants={fadeUp} transition={{ duration: 0.5 }}>
              <p className="section-kicker">Especialistas</p>
              <h2 className="section-heading">Acompañamiento con experiencia y criterio humano</h2>
              <p className="section-sub">
                Conoce a quienes te acompañarán en tu proceso y revisa el enfoque de cada perfil.
              </p>
            </motion.div>

            <motion.div className="especialista-cards landing-specialist-grid" variants={stagger}>
              {especialistas.map((e) => (
                <motion.article
                  key={e.slug}
                  className="especialista-card"
                  variants={fadeUp}
                  transition={{ duration: 0.5 }}
                  whileHover={{ y: -4, transition: { duration: 0.22 } }}
                >
                  <div className="especialista-card-avatar">
                    {e.avatar_url
                      ? <img src={e.avatar_url} alt={e.nombre} />
                      : <span>{e.nombre.charAt(0).toUpperCase()}</span>}
                  </div>
                  <p className="especialista-card-nombre">{e.nombre}</p>
                  <p className="especialista-card-especialidad">{e.especialidad}</p>
                  {e.bio && <p className="especialista-card-bio">{e.bio}</p>}
                  <Link
                    to={`/especialistas/${e.slug}`}
                    className="btn-secondary"
                  >
                    Ver perfil <ArrowRight size={16} aria-hidden="true" />
                  </Link>
                </motion.article>
              ))}
            </motion.div>
          </motion.div>
        </section>
      )}

      <section className="landing-section" aria-label="Preguntas frecuentes">
        <motion.div
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-60px' }}
          variants={stagger}
        >
          <motion.div className="landing-section-header" variants={fadeUp} transition={{ duration: 0.5 }}>
            <p className="section-kicker">Preguntas frecuentes</p>
            <h2 className="section-heading">Lo esencial antes de reservar</h2>
          </motion.div>

          <motion.div className="faq-list" variants={stagger}>
            {FAQS.map((faq, i) => (
              <motion.div key={i} className="faq-item" variants={fadeUp} transition={{ duration: 0.45 }}>
                <button
                  className="faq-trigger"
                  aria-expanded={openFaq === i}
                  onClick={() => setOpenFaq(openFaq === i ? null : i)}
                >
                  <span>{faq.q}</span>
                  <ChevronDown className={`faq-chevron${openFaq === i ? ' open' : ''}`} size={18} aria-hidden="true" />
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

      <motion.section
        className="landing-section landing-cta"
        aria-label="Llamada a la acción"
        initial={{ opacity: 0, y: 30 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: '-60px' }}
        transition={{ duration: 0.6 }}
      >
        <p className="section-kicker">Empieza cuando estés lista</p>
        <h2 className="section-heading">Agenda una lectura con claridad desde el primer paso</h2>
        <p className="section-sub">Crea tu cuenta gratis, revisa servicios y reserva el horario que mejor calce contigo.</p>
        <div className="cta-row" style={{ justifyContent: 'center' }}>
          <button type="button" className="btn-primary btn-shimmer" onClick={openRegister}>
            Comenzar ahora
          </button>
          <Link to="/servicios" className="btn-secondary">
            Explorar servicios <ArrowRight size={16} aria-hidden="true" />
          </Link>
        </div>
      </motion.section>
    </main>
  );
}
