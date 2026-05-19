import { motion } from 'framer-motion';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';

type CitaListItem = {
  id: number;
  uuid: string;
  estado: string;
  inicio_utc: string;
  fin_utc: string;
  reservada_hasta: string | null;
  moneda: string;
  precio_total_centavos: number;
  precio_final_centavos: number;
  zona_horaria_cliente?: string;
  timezone_cliente?: string;
  tema_principal: string | null;
  tipo_consulta: {
    id: number;
    slug: string;
    nombre: string;
    duracion_minutos: number;
  } | null;
};

type CitasResponse = {
  data: CitaListItem[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

const ESTADO_LABELS: Record<string, string> = {
  pendiente_abono:        'Pendiente de abono',
  reservada:              'Reservada',
  confirmada:             'Confirmada',
  en_curso:               'En curso',
  finalizada:             'Finalizada',
  completada:             'Completada',
  cancelada_cliente:      'Cancelada',
  cancelada_especialista: 'Cancelada',
  cancelada:              'Cancelada',
  no_show:                'No se presentó',
  expirada:               'Expirada',
  reagendada:             'Reagendada',
};

function formatMoney(valueInCents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', {
    style:                'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(valueInCents / 100);
}

function formatDate(value: string, timezone: string) {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone:  timezone || 'America/Santiago',
  }).format(new Date(value));
}

async function fetchMisConsultas(page: number): Promise<CitasResponse> {
  const response = await api.get('/citas', { params: { per_page: 9, page } });
  return response.data as CitasResponse;
}

const stagger = {
  hidden:  {},
  visible: { transition: { staggerChildren: 0.09 } },
};

const fadeUp = {
  hidden:  { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0  },
};

export function MisConsultasPage() {
  const [page, setPage] = useState(1);

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ['mis-consultas', page],
    queryFn:  () => fetchMisConsultas(page),
  });

  const lastPage  = data?.meta?.last_page ?? 1;
  const totalItems = data?.meta?.total ?? 0;

  const backendMessage =
    ((error as AxiosError<{ message?: string }>)?.response?.data?.message as string | undefined) ?? null;

  return (
    <main className="page-content consultas-page">
      <motion.div initial="hidden" animate="visible" variants={stagger}>

        <motion.div className="consultas-header" variants={fadeUp} transition={{ duration: 0.5 }}>
          <div>
            <p className="dash-eyebrow">Tu historial</p>
            <h1 className="dash-title">Mis consultas</h1>
            <p className="dash-subtitle">
              {isFetching && !isLoading ? 'Actualizando…' : 'Todas tus reservas, pagos y sesiones en un solo lugar.'}
            </p>
          </div>
          <Link className="btn-primary btn-shimmer" to="/servicios">Agendar consulta</Link>
        </motion.div>

        {isLoading ? (
          <motion.div className="cards-grid" variants={stagger}>
            {[1, 2, 3].map((n) => (
              <motion.div key={n} className="service-card skeleton-card" variants={fadeUp} transition={{ duration: 0.4 }}>
                <div className="skeleton-line skeleton-title" />
                <div className="skeleton-line skeleton-pill" />
                <div className="skeleton-line" />
                <div className="skeleton-line skeleton-short" />
              </motion.div>
            ))}
          </motion.div>
        ) : isError ? (
          <motion.p className="form-error" variants={fadeUp} transition={{ duration: 0.4 }}>
            {backendMessage ?? 'No se pudieron cargar tus consultas.'}
          </motion.p>
        ) : data?.data.length === 0 ? (
          <motion.div className="dash-empty" variants={fadeUp} transition={{ duration: 0.45 }}>
            <p>Aún no tienes consultas agendadas.</p>
            <Link className="btn-primary" to="/servicios">Explorar servicios</Link>
          </motion.div>
        ) : (
          <motion.section
            className="cards-grid"
            variants={stagger}
            style={{ marginTop: '1.2rem' }}
          >
            {data?.data.map((cita) => (
              <motion.article
                key={cita.id}
                className="service-card"
                variants={fadeUp}
                transition={{ duration: 0.4 }}
              >
                <h3>{cita.tipo_consulta?.nombre ?? 'Consulta'}</h3>

                <span className={`service-pill estado-${cita.estado}`}>
                  {ESTADO_LABELS[cita.estado] ?? cita.estado}
                </span>

                <p className="card-detail">
                  {cita.inicio_utc ? formatDate(cita.inicio_utc, cita.zona_horaria_cliente ?? cita.timezone_cliente ?? 'America/Santiago') : 'Sin fecha'}
                </p>

                {cita.tipo_consulta?.duracion_minutos ? (
                  <p className="card-detail">{cita.tipo_consulta.duracion_minutos} min</p>
                ) : null}

                <div className="cita-money">
                  <span>Abono: <strong>{formatMoney(cita.precio_final_centavos, cita.moneda)}</strong></span>
                  <span>Total: <strong>{formatMoney(cita.precio_total_centavos, cita.moneda)}</strong></span>
                </div>

                {cita.tema_principal ? (
                  <p className="card-detail cita-tema">{cita.tema_principal}</p>
                ) : null}

                {cita.estado === 'pendiente_abono' && (
                  <Link
                    to={`/app/citas/${cita.uuid}/pagar`}
                    className="btn-primary btn-shimmer"
                    style={{ textAlign: 'center', justifyContent: 'center' }}
                  >
                    Pagar ahora
                  </Link>
                )}
                {cita.estado === 'reservada' && (
                  <Link
                    to={`/app/citas/${cita.uuid}/pagar-saldo`}
                    className="btn-primary btn-shimmer"
                    style={{ textAlign: 'center', justifyContent: 'center' }}
                  >
                    Pagar saldo
                  </Link>
                )}
                {cita.estado === 'confirmada' && (
                  <Link
                    to={`/app/sala/${cita.uuid}`}
                    className="btn-primary btn-shimmer"
                    style={{ textAlign: 'center', justifyContent: 'center' }}
                  >
                    Entrar a sala
                  </Link>
                )}
                {cita.estado !== 'pendiente_abono' && cita.estado !== 'reservada' && cita.estado !== 'confirmada' && (
                  <Link to={`/app/citas/${cita.uuid}`} className="card-link">
                    Ver detalle
                  </Link>
                )}
              </motion.article>
            ))}
          </motion.section>
        )}

        {/* Paginación */}
        {!isLoading && !isError && lastPage > 1 ? (
          <motion.div
            className="pagination"
            variants={fadeUp}
            transition={{ duration: 0.4 }}
          >
            <button
              className="btn-secondary page-btn"
              type="button"
              disabled={page <= 1 || isFetching}
              onClick={() => setPage((p) => p - 1)}
            >
              Anterior
            </button>
            <span className="page-info">
              {page} / {lastPage}
              {totalItems > 0 ? <span className="page-total"> ({totalItems} consultas)</span> : null}
            </span>
            <button
              className="btn-secondary page-btn"
              type="button"
              disabled={page >= lastPage || isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              Siguiente
            </button>
          </motion.div>
        ) : null}

      </motion.div>
    </main>
  );
}
