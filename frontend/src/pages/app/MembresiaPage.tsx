import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { api } from '../../lib/api';

type Membresia = {
  activa: boolean;
  nombre: string | null;
  descripcion: string | null;
  consultas_restantes: number;
  consultas_totales: number;
  fecha_vencimiento: string | null;
  fecha_inicio: string | null;
};

const NO_MEMBRESIA: Membresia = {
  activa: false,
  nombre: null,
  descripcion: null,
  consultas_restantes: 0,
  consultas_totales: 0,
  fecha_vencimiento: null,
  fecha_inicio: null,
};

const fetchMembresia = (): Promise<Membresia> =>
  api.get('/membresia')
    .then((r) => (r.data as { data: Membresia }).data)
    .catch((err) => {
      if (err?.response?.status === 404) return NO_MEMBRESIA;
      throw err;
    });

function fmtDate(iso: string) {
  return new Intl.DateTimeFormat('es-CL', { dateStyle: 'long' }).format(new Date(iso));
}

const stagger = {
  hidden:  {},
  visible: { transition: { staggerChildren: 0.1 } },
};

const fadeUp = {
  hidden:  { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0  },
};

export function MembresiaPage() {
  const { data, isLoading, isError } = useQuery<Membresia>({
    queryKey: ['membresia'],
    queryFn:  fetchMembresia,
  });

  useEffect(() => { document.title = 'Mi Membresía | TarotEstrellas'; }, []);

  return (
    <main className="page-content">
      <motion.div initial="hidden" animate="visible" variants={stagger}>

        <motion.div variants={fadeUp} transition={{ duration: 0.5 }}>
          <p className="dash-eyebrow">✦ Tu plan espiritual</p>
          <h1 className="dash-title">Mi Membresía</h1>
        </motion.div>

        {isLoading ? (
          <motion.div className="membresia-card skeleton-card" variants={fadeUp} transition={{ duration: 0.4 }}>
            <div className="skeleton-line skeleton-title" />
            <div className="skeleton-line skeleton-pill" />
            <div className="skeleton-line" />
          </motion.div>
        ) : isError ? (
          <motion.p className="form-error" variants={fadeUp} transition={{ duration: 0.4 }}>
            No se pudo cargar tu membresía.
          </motion.p>
        ) : data?.activa ? (
          <motion.div className="membresia-card" variants={fadeUp} transition={{ duration: 0.45 }}>
            <div className="membresia-header">
              <motion.span
                className="membresia-icon-big"
                animate={{ rotate: [0, 12, -12, 0] }}
                transition={{ duration: 4, repeat: Infinity, ease: 'easeInOut' }}
              >
                ✦
              </motion.span>
              <div>
                <h2 className="membresia-title">{data.nombre ?? 'Membresía activa'}</h2>
                <span className="service-pill estado-confirmada">Activa</span>
              </div>
            </div>

            {data.descripcion && (
              <p className="membresia-desc">{data.descripcion}</p>
            )}

            {/* Progreso de consultas */}
            <div className="membresia-progress-wrap">
              <div className="membresia-progress-labels">
                <span>Consultas restantes</span>
                <strong>{data.consultas_restantes} / {data.consultas_totales}</strong>
              </div>
              <div className="membresia-progress-bar" role="progressbar"
                aria-valuenow={data.consultas_restantes}
                aria-valuemax={data.consultas_totales}>
                <motion.div
                  className="membresia-progress-fill"
                  initial={{ width: 0 }}
                  animate={{ width: `${(data.consultas_restantes / Math.max(data.consultas_totales, 1)) * 100}%` }}
                  transition={{ duration: 0.8, ease: 'easeOut' }}
                />
              </div>
            </div>

            <dl className="membresia-dl">
              {data.fecha_inicio && (
                <>
                  <dt>Fecha de inicio</dt>
                  <dd>{fmtDate(data.fecha_inicio)}</dd>
                </>
              )}
              {data.fecha_vencimiento && (
                <>
                  <dt>Vence el</dt>
                  <dd>{fmtDate(data.fecha_vencimiento)}</dd>
                </>
              )}
            </dl>

            <div className="wizard-actions" style={{ marginTop: '1.5rem' }}>
              <Link className="btn-primary btn-shimmer" to="/servicios">
                Usar una consulta →
              </Link>
            </div>
          </motion.div>
        ) : (
          <motion.div className="membresia-empty" variants={fadeUp} transition={{ duration: 0.45 }}>
            <motion.span
              className="membresia-icon-big"
              animate={{ opacity: [0.5, 1, 0.5] }}
              transition={{ duration: 3, repeat: Infinity }}
            >
              🌙
            </motion.span>
            <h2>Sin membresía activa</h2>
            <p className="dash-subtitle">
              Con una membresía puedes acceder a consultas sin pagar por separado. Explora nuestros paquetes disponibles.
            </p>

            <div className="membresia-beneficios">
              <div className="membresia-beneficio">
                <span>✦</span>
                <span>12 consultas al precio de 10</span>
              </div>
              <div className="membresia-beneficio">
                <span>✦</span>
                <span>Sin abono — acceso directo a tu sala</span>
              </div>
              <div className="membresia-beneficio">
                <span>✦</span>
                <span>Válida por 12 meses desde la compra</span>
              </div>
            </div>

            <Link className="btn-primary btn-shimmer" to="/servicios" style={{ marginTop: '1.5rem' }}>
              Ver paquetes y membresías
            </Link>
          </motion.div>
        )}

      </motion.div>
    </main>
  );
}
