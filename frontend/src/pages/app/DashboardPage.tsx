import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';

type CitaListItem = {
  id: number;
  estado: string;
  inicio_utc: string;
  tipo_consulta: { nombre: string; duracion_minutos: number } | null;
  timezone_cliente: string;
};

type CitasResponse = {
  data: CitaListItem[];
  meta: { total: number; current_page: number; per_page: number; last_page: number };
};

const ESTADO_LABELS: Record<string, string> = {
  pendiente_abono: 'Pendiente de abono',
  reservada:       'Reservada',
  confirmada:      'Confirmada',
  completada:      'Completada',
  cancelada:       'Cancelada',
  expirada:        'Expirada',
};

const UPCOMING_ESTADOS = new Set(['reservada', 'confirmada', 'pendiente_abono']);

function formatDate(value: string, timezone: string) {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: timezone || undefined,
  }).format(new Date(value));
}

const stagger = {
  hidden:  {},
  visible: { transition: { staggerChildren: 0.1 } },
};

const fadeUp = {
  hidden:  { opacity: 0, y: 22 },
  visible: { opacity: 1, y: 0  },
};

export function DashboardPage() {
  const user   = useAuthStore((s) => s.user);
  const nombre = user?.nombre ?? user?.email?.split('@')[0] ?? 'Viajera';

  const { data } = useQuery<CitasResponse, AxiosError>({
    queryKey: ['mis-consultas-dashboard'],
    queryFn:  async () => {
      const res = await api.get('/citas', { params: { per_page: 5 } });
      return res.data as CitasResponse;
    },
  });

  const upcoming = data?.data.filter((c) => UPCOMING_ESTADOS.has(c.estado)) ?? [];
  const total    = data?.meta.total ?? 0;

  return (
    <main className="page-content">
      <motion.div initial="hidden" animate="visible" variants={stagger}>

        {/* Saludo */}
        <motion.div className="dash-greeting" variants={fadeUp} transition={{ duration: 0.5 }}>
          <p className="dash-eyebrow">✦ Bienvenida de regreso</p>
          <h1 className="dash-title">
            Hola, <span className="dash-name">{nombre}</span>
          </h1>
          <p className="dash-subtitle">El universo tiene algo para ti hoy.</p>
        </motion.div>

        {/* Stats */}
        <motion.div className="dash-stats" variants={fadeUp} transition={{ duration: 0.45 }}>
          <div className="stat-card">
            <span className="stat-value">{total}</span>
            <span className="stat-label">Consultas totales</span>
          </div>
          <div className="stat-card">
            <span className="stat-value">{upcoming.length}</span>
            <span className="stat-label">Próximas citas</span>
          </div>
        </motion.div>

        {/* Acciones rápidas */}
        <motion.div className="dash-actions" variants={fadeUp} transition={{ duration: 0.45 }}>
          <Link className="btn-primary btn-shimmer" to="/servicios">
            Agendar consulta
          </Link>
          <Link className="btn-secondary" to="/app/mis-consultas">
            Ver mis consultas
          </Link>
        </motion.div>

        {/* Próximas citas o estado vacío */}
        {upcoming.length > 0 ? (
          <motion.section variants={fadeUp} transition={{ duration: 0.45 }}>
            <h2 className="dash-section-title">Próximas consultas</h2>
            <div className="cards-grid">
              {upcoming.map((cita) => (
                <article key={cita.id} className="service-card">
                  <h3>{cita.tipo_consulta?.nombre ?? 'Consulta'}</h3>
                  <span className={`service-pill estado-${cita.estado}`}>
                    {ESTADO_LABELS[cita.estado] ?? cita.estado}
                  </span>
                  <p className="card-detail">
                    {cita.inicio_utc
                      ? formatDate(cita.inicio_utc, cita.timezone_cliente)
                      : 'Sin fecha'}
                  </p>
                  {cita.tipo_consulta?.duracion_minutos ? (
                    <p className="card-detail">{cita.tipo_consulta.duracion_minutos} min</p>
                  ) : null}
                  <Link to="/app/mis-consultas" className="card-link">Ver detalle →</Link>
                </article>
              ))}
            </div>
          </motion.section>
        ) : (
          <motion.div className="dash-empty" variants={fadeUp} transition={{ duration: 0.45 }}>
            <span className="dash-empty-icon">🔮</span>
            <p>Aún no tienes consultas agendadas.</p>
            <Link className="btn-primary" to="/servicios">Explorar servicios</Link>
          </motion.div>
        )}
      </motion.div>
    </main>
  );
}
