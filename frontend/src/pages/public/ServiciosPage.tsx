import { motion } from 'framer-motion';
import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense, useEffect, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

const FloatingCard = lazy(() => import('../../components/3d/FloatingCard'));
import type { AxiosError } from 'axios';
import { api } from '../../lib/api';

const MONEDAS = ['CLP', 'USD', 'MXN', 'EUR'] as const;
type Moneda = typeof MONEDAS[number];

type TipoConsulta = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  requiere_datos_natales: boolean;
  moneda: string;
  precio_moneda: number | null;
  precio_referencial_centavos: number | null;
  color_hex: string | null;
  imagen_url: string | null;
};

type TiposResponse = {
  data: TipoConsulta[];
};

type TipoConsultaFilters = {
  search: string;
  categoria: string;
  duracion: string;
  requiere_datos_natales: string;
  precio_min: string;
  precio_max: string;
  moneda: Moneda;
};

// Variantes exactas del spec (sección 6.6.3)
const cardVariants = {
  hidden: { opacity: 0, y: 30 },
  visible: (i: number) => ({
    opacity: 1,
    y: 0,
    transition: { delay: i * 0.1, duration: 0.5 },
  }),
};

const gridVariants = {
  hidden: {},
  visible: { transition: { staggerChildren: 0.1 } },
};

async function fetchTipos(filters: TipoConsultaFilters): Promise<TiposResponse> {
  const response = await api.get('/public/tipos-consulta', {
    params: {
      search: filters.search || undefined,
      categoria: filters.categoria || undefined,
      duracion: filters.duracion || undefined,
      requiere_datos_natales: filters.requiere_datos_natales || undefined,
      precio_min: filters.precio_min || undefined,
      precio_max: filters.precio_max || undefined,
      moneda: filters.moneda,
    },
  });
  return response.data as TiposResponse;
}

function formatPrice(value: number | null | undefined, currency: string) {
  if (value == null || isNaN(value)) return 'Consultar precio';
  const amount = value / 100;
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(amount);
}

function getDisplayPrice(tipo: TipoConsulta, moneda: Moneda) {
  if (tipo.precio_moneda !== undefined && tipo.precio_moneda !== null) {
    return formatPrice(tipo.precio_moneda, moneda);
  }
  return formatPrice(tipo.precio_referencial_centavos, tipo.moneda);
}

export function ServiciosPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  const filters = useMemo(
    () => ({
      search: searchParams.get('search') ?? '',
      categoria: searchParams.get('categoria') ?? '',
      duracion: searchParams.get('duracion') ?? '',
      requiere_datos_natales: searchParams.get('requiere_datos_natales') ?? '',
      precio_min: searchParams.get('precio_min') ?? '',
      precio_max: searchParams.get('precio_max') ?? '',
      moneda: (searchParams.get('moneda') ?? 'CLP') as Moneda,
    }),
    [searchParams],
  );

  const { data, isLoading, isFetching, isError, error } = useQuery({
    queryKey: ['tipos-consulta', filters],
    queryFn: () => fetchTipos(filters),
  });

  const errorText = isError
    ? (((error as AxiosError<{ message?: string }>)?.response?.data?.message as string | undefined) ??
      'No se pudieron cargar los servicios. Intenta nuevamente en unos segundos.')
    : null;

  useEffect(() => {
    document.title = 'Catálogo de Servicios | TarotEstrellas';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'Explora servicios de tarot, astrología y guía espiritual con filtros por categoría, duración y precio.');
  }, []);

  const updateFilter = (key: keyof TipoConsultaFilters, value: string) => {
    const next = new URLSearchParams(searchParams);
    if (!value) next.delete(key);
    else next.set(key, value);
    setSearchParams(next, { replace: true });
  };

  const clearFilters = () => setSearchParams(new URLSearchParams(), { replace: true });

  return (
    <main className="page-content catalog-page">
      <motion.div
        className="catalog-hero"
        initial={{ opacity: 0, y: 22 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
      >
        <div>
          <p className="dash-eyebrow">Lecturas y guía espiritual</p>
          <h1 className="dash-title">Catálogo de servicios</h1>
          <p className="dash-subtitle">Encuentra una sesión clara, cercana y alineada con lo que necesitas resolver hoy.</p>
        </div>
        <div className="catalog-hero-summary" aria-label="Resumen del catálogo">
          <strong>{data?.data.length ?? 0}</strong>
          <span>{data?.data.length === 1 ? 'servicio visible' : 'servicios visibles'}</span>
        </div>
      </motion.div>

      <Suspense fallback={<div className="canvas-loading" aria-hidden="true" />}>
        <FloatingCard />
      </Suspense>

      <section className="filters-card catalog-filters" aria-label="Filtros del catálogo">
        <header className="filters-header">
          <div>
            <h2>Filtrar servicios</h2>
            <p>Afina por categoría, moneda, duración o precio.</p>
          </div>
          <button className="btn-secondary" type="button" onClick={clearFilters}>
            Limpiar filtros
          </button>
        </header>
        <div className="filters-grid">
          <label>
            Buscar servicio
            <input
              type="search"
              value={filters.search}
              onChange={(e) => updateFilter('search', e.target.value)}
              placeholder="Ej: tarot, carta astral, runas"
            />
          </label>

          <label>
            Categoría
            <select value={filters.categoria} onChange={(e) => updateFilter('categoria', e.target.value)}>
              <option value="">Todas</option>
              <option value="tarot">Tarot y mancias</option>
              <option value="astrologia">Astrología</option>
              <option value="otros">Otros</option>
            </select>
          </label>

          <label>
            Moneda
            <select value={filters.moneda} onChange={(e) => updateFilter('moneda', e.target.value)}>
              {MONEDAS.map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
          </label>

          <label>
            Duración
            <select value={filters.duracion} onChange={(e) => updateFilter('duracion', e.target.value)}>
              <option value="">Cualquiera</option>
              <option value="30">Hasta 30 min</option>
              <option value="60">60 min</option>
              <option value="90">90 min</option>
              <option value="120">120 min o más</option>
            </select>
          </label>

          <label>
            Datos natales
            <select
              value={filters.requiere_datos_natales}
              onChange={(e) => updateFilter('requiere_datos_natales', e.target.value)}
            >
              <option value="">Indistinto</option>
              <option value="true">Requiere datos natales</option>
              <option value="false">No requiere datos natales</option>
            </select>
          </label>

          <label>
            Precio mínimo
            <input
              type="number"
              min={0}
              value={filters.precio_min}
              onChange={(e) => updateFilter('precio_min', e.target.value)}
              placeholder="0"
            />
          </label>

          <label>
            Precio máximo
            <input
              type="number"
              min={0}
              value={filters.precio_max}
              onChange={(e) => updateFilter('precio_max', e.target.value)}
              placeholder="100000"
            />
          </label>
        </div>

        <div className="filters-actions">
          {isFetching ? <span>Actualizando...</span> : null}
        </div>
      </section>

      {isLoading ? (
        <section className="cards-grid catalog-results">
          {[1, 2, 3].map((n) => (
            <article key={n} className="service-card skeleton-card">
              <div className="skeleton-line skeleton-title" />
              <div className="skeleton-line" />
              <div className="skeleton-line skeleton-short" />
              <div className="skeleton-line skeleton-pill" />
            </article>
          ))}
        </section>
      ) : null}
      {errorText ? <p className="form-error">{errorText}</p> : null}

      {/* Cards con whileInView + stagger según spec 6.6.3 */}
      <motion.section
        className="cards-grid catalog-results"
        aria-label="Servicios disponibles"
        initial="hidden"
        whileInView="visible"
        viewport={{ once: true, margin: '-60px' }}
        variants={gridVariants}
      >
        {data?.data.map((tipo, index) => (
          <motion.article
            key={tipo.id}
            className="service-card"
            custom={index}
            variants={cardVariants}
            whileHover={{ y: -4, transition: { duration: 0.3, ease: 'easeOut' } }}
          >
            <div className="service-card-topline">
              <span>{tipo.duracion_minutos} min</span>
              {tipo.requiere_datos_natales ? <span>Datos natales</span> : <span>Sin datos natales</span>}
            </div>
            <h3>{tipo.nombre}</h3>
            <p className="service-card-desc">{tipo.descripcion}</p>
            <div className="service-card-pills">
              <span className="service-pill primera-consulta-pill">10% primera consulta</span>
            </div>
            <strong className="service-card-price">{getDisplayPrice(tipo, filters.moneda)}</strong>
            <Link to={`/servicios/${tipo.slug}`} className="btn-secondary service-card-action">
              Ver detalle
            </Link>
          </motion.article>
        ))}
      </motion.section>

      {!isLoading && data?.data.length === 0 ? (
        <div className="dash-empty compact-empty">
          <p>No se encontraron servicios con los filtros actuales.</p>
          <button className="btn-secondary" type="button" onClick={clearFilters}>Quitar filtros</button>
        </div>
      ) : null}
    </main>
  );
}
