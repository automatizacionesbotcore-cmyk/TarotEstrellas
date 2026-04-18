import { useState, useMemo, useEffect } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import { api } from '../../lib/api';

// ── Types ─────────────────────────────────────────────────────────────────────
type CitaDetalle = {
  id: number;
  uuid: string;
  estado: string;
  inicio_utc: string;
  fin_utc: string;
  moneda: string;
  precio_total_centavos: number;
  precio_final_centavos: number;
  timezone_cliente: string;
  tema_principal: string | null;
  pregunta_especifica: string | null;
  tipo_consulta: { nombre: string; duracion_minutos: number; slug: string } | null;
};

type Grabacion = {
  url_firmada: string;
  expires_at: string;
  duracion_segundos: number;
};

type Segmento = { timestamp: number; texto: string };
type Transcripcion = {
  estado: 'pendiente' | 'procesando' | 'completada' | 'error';
  segmentos: Segmento[];
};

type ResumenIA = {
  estado: 'pendiente' | 'procesando' | 'completada' | 'error';
  resumen_corto: string | null;
  temas: string[];
  emocion_predominante: string | null;
};

type Tab = 'info' | 'grabacion' | 'transcripcion' | 'resumen';

const ESTADO_LABELS: Record<string, string> = {
  pendiente_abono: 'Pendiente de abono',
  reservada:       'Reservada',
  confirmada:      'Confirmada',
  completada:      'Completada',
  cancelada:       'Cancelada',
  expirada:        'Expirada',
};

// ── API ───────────────────────────────────────────────────────────────────────
const fetchCita        = (id: string) => api.get(`/citas/${id}`).then((r) => (r.data as { data: CitaDetalle }).data);
const fetchGrabacion   = (id: string) => api.get(`/citas/${id}/grabacion`).then((r) => (r.data as { data: Grabacion }).data);
const fetchTranscripcion = (id: string) => api.get(`/citas/${id}/transcripcion`).then((r) => (r.data as { data: Transcripcion }).data);
const fetchResumen     = (id: string) => api.get(`/citas/${id}/resumen`).then((r) => (r.data as { data: ResumenIA }).data);

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtDate(iso: string, tz: string) {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'full', timeStyle: 'short', timeZone: tz || undefined,
  }).format(new Date(iso));
}

function fmtMoney(cents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency', currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(cents / 100);
}

function fmtTimestamp(seconds: number) {
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = (seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
}

// ── Processing indicator ──────────────────────────────────────────────────────
function Processing({ label }: { label: string }) {
  return (
    <div className="processing-box">
      <motion.span
        className="processing-star"
        animate={{ rotate: 360 }}
        transition={{ duration: 3, repeat: Infinity, ease: 'linear' }}
      >
        ✦
      </motion.span>
      <p>{label}</p>
    </div>
  );
}

// ── Tab: Info ─────────────────────────────────────────────────────────────────
function TabInfo({ cita }: { cita: CitaDetalle }) {
  return (
    <div className="detalle-info">
      <dl className="detalle-dl">
        <dt>Estado</dt>
        <dd><span className={`service-pill estado-${cita.estado}`}>{ESTADO_LABELS[cita.estado] ?? cita.estado}</span></dd>
        <dt>Fecha</dt>
        <dd>{fmtDate(cita.inicio_utc, cita.timezone_cliente)}</dd>
        <dt>Duración</dt>
        <dd>{cita.tipo_consulta?.duracion_minutos} min</dd>
        <dt>Abono pagado</dt>
        <dd>{fmtMoney(cita.precio_final_centavos, cita.moneda)}</dd>
        <dt>Total</dt>
        <dd>{fmtMoney(cita.precio_total_centavos, cita.moneda)}</dd>
        {cita.tema_principal && <><dt>Tema</dt><dd>{cita.tema_principal}</dd></>}
        {cita.pregunta_especifica && <><dt>Pregunta</dt><dd>{cita.pregunta_especifica}</dd></>}
      </dl>
    </div>
  );
}

// ── Tab: Grabación ────────────────────────────────────────────────────────────
function TabGrabacion({ id }: { id: string }) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['grabacion', id],
    queryFn: () => fetchGrabacion(id),
    retry: false,
  });

  if (isLoading) return <Processing label="Cargando grabación…" />;
  if (isError || !data)
    return <p className="detalle-empty">La grabación aún no está disponible.</p>;

  return (
    <div className="grabacion-wrap">
      <video
        src={data.url_firmada}
        controls
        className="grabacion-player"
        controlsList="nodownload"
      />
      <p className="detalle-muted">
        Disponible por 90 días · Vence:{' '}
        {new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium' }).format(new Date(data.expires_at))}
      </p>
    </div>
  );
}

// ── Tab: Transcripción ────────────────────────────────────────────────────────
function TabTranscripcion({ id }: { id: string }) {
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['transcripcion', id],
    queryFn: () => fetchTranscripcion(id),
    refetchInterval: (q) => {
      const estado = q.state.data?.estado;
      return estado === 'procesando' || estado === 'pendiente' ? 8000 : false;
    },
  });

  const filtered = useMemo(() => {
    if (!data?.segmentos) return [];
    const q = search.toLowerCase().trim();
    if (!q) return data.segmentos;
    return data.segmentos.filter((s) => s.texto.toLowerCase().includes(q));
  }, [data?.segmentos, search]);

  if (isLoading) return <Processing label="Cargando transcripción…" />;

  if (!data || data.estado === 'pendiente')
    return <Processing label="La transcripción aún no ha comenzado. Se generará automáticamente al finalizar la sesión." />;

  if (data.estado === 'procesando')
    return <Processing label="Transcribiendo la sesión… esto puede tomar unos minutos." />;

  if (data.estado === 'error')
    return <p className="form-error">Hubo un error al generar la transcripción. Contacta soporte.</p>;

  return (
    <div className="transcripcion-wrap">
      <div className="transcripcion-search">
        <input
          type="search"
          placeholder="Buscar en la transcripción…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        {search && <span className="transcripcion-count">{filtered.length} resultado{filtered.length !== 1 ? 's' : ''}</span>}
      </div>

      <div className="transcripcion-list">
        {filtered.length === 0 ? (
          <p className="detalle-empty">No se encontraron coincidencias.</p>
        ) : (
          filtered.map((seg, i) => (
            <div key={i} className="transcripcion-seg">
              <span className="seg-timestamp">{fmtTimestamp(seg.timestamp)}</span>
              <p className="seg-texto">
                {search
                  ? highlightText(seg.texto, search)
                  : seg.texto}
              </p>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

function highlightText(text: string, query: string) {
  const parts = text.split(new RegExp(`(${query})`, 'gi'));
  return parts.map((part, i) =>
    part.toLowerCase() === query.toLowerCase()
      ? <mark key={i} className="seg-highlight">{part}</mark>
      : part,
  );
}

// ── Tab: Resumen IA ───────────────────────────────────────────────────────────
function TabResumen({ id }: { id: string }) {
  const { data, isLoading } = useQuery({
    queryKey: ['resumen', id],
    queryFn: () => fetchResumen(id),
    refetchInterval: (q) => {
      const estado = q.state.data?.estado;
      return estado === 'procesando' || estado === 'pendiente' ? 8000 : false;
    },
  });

  if (isLoading) return <Processing label="Cargando resumen…" />;

  if (!data || data.estado === 'pendiente')
    return <Processing label="El resumen se generará automáticamente al completar la transcripción." />;

  if (data.estado === 'procesando')
    return <Processing label="Generando resumen con IA… un momento." />;

  if (data.estado === 'error')
    return <p className="form-error">No se pudo generar el resumen. Contacta soporte.</p>;

  return (
    <div className="resumen-wrap">
      {data.emocion_predominante && (
        <div className="resumen-emocion">
          <span className="resumen-label">Emoción predominante</span>
          <span className="emocion-chip">{data.emocion_predominante}</span>
        </div>
      )}

      {data.temas.length > 0 && (
        <div className="resumen-temas">
          <span className="resumen-label">Temas detectados</span>
          <div className="temas-chips">
            {data.temas.map((tema) => (
              <span key={tema} className="tema-chip">{tema}</span>
            ))}
          </div>
        </div>
      )}

      {data.resumen_corto && (
        <div className="resumen-texto">
          <span className="resumen-label">Resumen de la sesión</span>
          <p>{data.resumen_corto}</p>
        </div>
      )}
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────
const TABS: { key: Tab; label: string }[] = [
  { key: 'info',          label: 'Información' },
  { key: 'grabacion',     label: 'Grabación' },
  { key: 'transcripcion', label: 'Transcripción' },
  { key: 'resumen',       label: 'Resumen IA' },
];

export function DetalleCitaPage() {
  const { id = '' } = useParams<{ id: string }>();
  const [activeTab, setActiveTab] = useState<Tab>('info');

  const { data: cita, isLoading, isError } = useQuery({
    queryKey: ['cita', id],
    queryFn: () => fetchCita(id),
    enabled: Boolean(id),
  });

  useEffect(() => {
    document.title = cita
      ? `${cita.tipo_consulta?.nombre ?? 'Consulta'} | TarotEstrellas`
      : 'Detalle de consulta | TarotEstrellas';
  }, [cita]);

  if (isLoading) return <main className="page-content"><Processing label="Cargando consulta…" /></main>;
  if (isError || !cita) return (
    <main className="page-content">
      <p className="form-error">No se encontró la consulta.</p>
      <Link className="btn-secondary" to="/app/mis-consultas">Volver</Link>
    </main>
  );

  const showPostTabs = cita.estado === 'completada';

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
        <p className="dash-eyebrow">✦ Detalle de consulta</p>
        <h1 className="dash-title">{cita.tipo_consulta?.nombre ?? 'Consulta'}</h1>
        <p className="dash-subtitle">{fmtDate(cita.inicio_utc, cita.timezone_cliente)}</p>
      </motion.div>

      {/* Tabs */}
      <div className="detalle-tabs" role="tablist">
        {TABS.map(({ key, label }) => {
          const disabled = !showPostTabs && key !== 'info';
          return (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={activeTab === key}
              aria-disabled={disabled}
              className={[
                'detalle-tab',
                activeTab === key ? 'active' : '',
                disabled ? 'disabled' : '',
              ].join(' ').trim()}
              onClick={() => !disabled && setActiveTab(key)}
            >
              {label}
              {disabled && <span className="tab-lock" aria-label="Solo disponible al completar">🔒</span>}
            </button>
          );
        })}
      </div>

      {/* Tab content */}
      <AnimatePresence mode="wait">
        <motion.div
          key={activeTab}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.22 }}
          className="detalle-tab-content"
        >
          {activeTab === 'info'          && <TabInfo cita={cita} />}
          {activeTab === 'grabacion'     && <TabGrabacion id={id} />}
          {activeTab === 'transcripcion' && <TabTranscripcion id={id} />}
          {activeTab === 'resumen'       && <TabResumen id={id} />}
        </motion.div>
      </AnimatePresence>

      <div style={{ marginTop: '1.5rem' }}>
        <Link className="btn-secondary" to="/app/mis-consultas">← Volver a mis consultas</Link>
      </div>
    </main>
  );
}
