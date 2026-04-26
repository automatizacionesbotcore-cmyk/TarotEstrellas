import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { useAuthModalStore } from '../../stores/authModalStore';
import { useAuthStore } from '../../stores/authStore';

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
    <main className="page-content">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
      >
        {/* ── Hero del especialista ── */}
        <section className="especialista-hero">
          {especialista.avatar_url ? (
            <img
              className="especialista-hero-avatar"
              src={especialista.avatar_url}
              alt={especialista.nombre}
            />
          ) : (
            <div className="especialista-hero-avatar especialista-hero-avatar-placeholder">
              {especialista.nombre.charAt(0).toUpperCase()}
            </div>
          )}

          <div className="especialista-hero-info">
            <p className="dash-eyebrow">✦ Especialista</p>
            <h1 className="dash-title">{especialista.nombre}</h1>
            {especialista.especialidad && (
              <p className="especialista-hero-especialidad">{especialista.especialidad}</p>
            )}
            {especialista.biografia && (
              <p className="especialista-hero-bio">{especialista.biografia}</p>
            )}

            <div className="cta-row" style={{ marginTop: '1.25rem' }}>
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
          <section style={{ marginTop: '2.5rem' }}>
            <h2 style={{ marginBottom: '1.25rem' }}>Servicios disponibles</h2>
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
      </motion.div>
    </main>
  );
}
