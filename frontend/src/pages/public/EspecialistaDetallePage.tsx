import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { useAuthModalStore } from '../../stores/authModalStore';
import { useAuthStore } from '../../stores/authStore';
import { DefaultSpecialistAvatar } from '../../components/common/DefaultSpecialistAvatar';

type EspecialistaPublico = {
  id: number;
  uuid: string;
  slug: string;
  nombre: string;
  especialidad: string | null;
  biografia: string | null;
  avatar_url: string | null;
};

type ServicioCard = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_referencial_centavos: number | null;
  moneda: string;
  imagen_url: string | null;
  color_hex: string | null;
};

type DetalleResponse = {
  data: {
    especialista: EspecialistaPublico;
    servicios: ServicioCard[];
  };
};

type ResenaPublica = {
  uuid: string;
  puntuacion: number;
  comentario: string | null;
  cliente_nombre: string;
  created_at: string;
};

type ResenasResponse = {
  data: {
    promedio: number | null;
    total: number;
    resenas: ResenaPublica[];
  };
};

function StarRating({ value }: { value: number }) {
  return (
    <span style={{ color: 'var(--accent)', fontSize: '1rem', letterSpacing: 2 }}>
      {Array.from({ length: 5 }, (_, i) => (i < value ? '★' : '☆')).join('')}
    </span>
  );
}

function formatPrice(centavos: number | null, moneda: string) {
  if (centavos === null) return 'Consultar';
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: moneda,
    maximumFractionDigits: moneda === 'CLP' ? 0 : 2,
  }).format(centavos / 100);
}

export function EspecialistaDetallePage() {
  const { slug = '' } = useParams();
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const openRegister = useAuthModalStore((s) => s.openRegister);

  const { data, isLoading, isError } = useQuery<DetalleResponse['data']>({
    queryKey: ['public', 'especialista', slug],
    queryFn: async () => {
      const r = await api.get<DetalleResponse>(`/public/especialistas/${slug}`);
      return r.data.data;
    },
    enabled: Boolean(slug),
  });

  const { data: resenasData } = useQuery<ResenasResponse['data']>({
    queryKey: ['public', 'especialista', slug, 'resenas'],
    queryFn: async () => {
      const r = await api.get<ResenasResponse>(`/public/especialistas/${slug}/resenas`);
      return r.data.data;
    },
    enabled: Boolean(slug),
  });

  if (isLoading) {
    return (
      <main className="page-content">
        <p className="text-muted">Cargando perfil…</p>
      </main>
    );
  }

  if (isError || !data) {
    return (
      <main className="page-content">
        <p className="form-error">No se encontró el perfil del especialista.</p>
        <Link className="btn-secondary" to="/servicios">Ver servicios</Link>
      </main>
    );
  }

  const { especialista, servicios } = data;

  return (
    <main className="page-content especialista-page">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
      >
        {/* ── Hero del especialista ── */}
        <section className="especialista-hero especialista-hero--featured">
          {especialista.avatar_url ? (
            <img
              className="especialista-hero-avatar"
              src={especialista.avatar_url}
              alt={especialista.nombre}
            />
          ) : (
            <DefaultSpecialistAvatar
              name={especialista.nombre}
              className="especialista-hero-avatar especialista-hero-avatar-placeholder"
            />
          )}

          <div className="especialista-hero-info">
            <p className="dash-eyebrow">Especialista</p>
            <h1 className="dash-title">{especialista.nombre}</h1>
            {especialista.especialidad && (
              <p className="especialista-hero-especialidad">{especialista.especialidad}</p>
            )}
            {especialista.biografia && (
              <p className="especialista-hero-bio">{especialista.biografia}</p>
            )}

            {resenasData && resenasData.total > 0 && (
              <div className="especialista-trust-row">
                {resenasData.promedio !== null && (
                  <span><StarRating value={Math.round(resenasData.promedio)} /> {resenasData.promedio.toFixed(1)}</span>
                )}
                <span>{resenasData.total} {resenasData.total === 1 ? 'reseña' : 'reseñas'}</span>
                <span>{servicios.length} {servicios.length === 1 ? 'servicio' : 'servicios'}</span>
              </div>
            )}

            <div className="cta-row especialista-cta-row">
              {isAuthenticated ? (
                <Link
                  className="btn-primary btn-shimmer"
                  to={`/servicios?especialista=${slug}`}
                >
                  Agendar con {especialista.nombre.split(' ')[0]}
                </Link>
              ) : (
                <button type="button" className="btn-primary btn-shimmer" onClick={openRegister}>
                  Crear cuenta y agendar
                </button>
              )}
              <Link className="btn-secondary" to="/servicios">
                Ver todos los servicios
              </Link>
            </div>
          </div>
        </section>

        {/* ── Servicios disponibles ── */}
        {servicios.length > 0 && (
          <section className="public-section">
            <div className="section-title-row">
              <div>
                <p className="dash-eyebrow">Agenda</p>
                <h2>Servicios disponibles</h2>
              </div>
            </div>
            <div className="especialista-servicios-grid">
              {servicios.map((s) => (
                <Link
                  key={s.id}
                  to={`/servicios/${s.slug}`}
                  className="especialista-servicio-card"
                  style={{ borderTopColor: s.color_hex ?? 'var(--accent)' }}
                >
                  {s.imagen_url && (
                    <img
                      className="especialista-servicio-img"
                      src={s.imagen_url}
                      alt={s.nombre}
                    />
                  )}
                  <div className="especialista-servicio-body">
                    <strong className="especialista-servicio-nombre">{s.nombre}</strong>
                    <p className="especialista-servicio-desc">{s.descripcion}</p>
                    <div className="especialista-servicio-meta">
                      <span>{s.duracion_minutos} min</span>
                      <span>{formatPrice(s.precio_referencial_centavos, s.moneda)}</span>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </section>
        )}
        {/* ── Reseñas ── */}
        {resenasData && resenasData.total > 0 && (
          <section className="public-section">
            <div className="section-title-row">
              <div>
                <p className="dash-eyebrow">Experiencia</p>
                <h2>Reseñas</h2>
              </div>
              {resenasData.promedio !== null && (
                <span className="rating-summary">
                  <StarRating value={Math.round(resenasData.promedio)} />
                  <strong>{resenasData.promedio.toFixed(1)}</strong>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                    ({resenasData.total} {resenasData.total === 1 ? 'reseña' : 'reseñas'})
                  </span>
                </span>
              )}
            </div>
            <div className="resenas-list">
              {resenasData.resenas.map((r) => (
                <div key={r.uuid} className="resena-card">
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem' }}>
                    <StarRating value={r.puntuacion} />
                    <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>{r.created_at}</span>
                  </div>
                  {r.comentario && <p style={{ margin: 0 }}>{r.comentario}</p>}
                  <p style={{ margin: '0.4rem 0 0', fontSize: '0.8rem', color: 'var(--text-muted)' }}>— {r.cliente_nombre}</p>
                </div>
              ))}
            </div>
          </section>
        )}
      </motion.div>
    </main>
  );
}
