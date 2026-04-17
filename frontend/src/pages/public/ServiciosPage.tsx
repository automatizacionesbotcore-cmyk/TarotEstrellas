import { motion } from 'framer-motion';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import type { AxiosError } from 'axios';
import { api } from '../../lib/api';

type TipoConsulta = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  categoria: 'tarot' | 'astrologia' | 'otros';
  duracion_minutos: number;
  requiere_datos_natales: boolean;
  moneda: string;
  precio_centavos: number | null;
};

type TiposResponse = {
  currency: string;
  data: TipoConsulta[];
};

type TipoConsultaFilters = {
  search: string;
  categoria: string;
  duracion: string;
  requiere_datos_natales: string;
  precio_min: string;
  precio_max: string;
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
    },
  });

  return response.data as TiposResponse;
}

function formatPrice(value: number | null, currency: string) {
  if (value === null) {
    return 'Consultar precio';
  }

  const amount = value / 100;

  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(amount);
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
    document.title = 'Catalogo de Servicios | TarotEstrellas';

    const description = 'Explora servicios de tarot, astrologia y guia espiritual con filtros por categoria, duracion y precio.';
    let meta = document.querySelector('meta[name="description"]');

    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }

    meta.setAttribute('content', description);
  }, []);

  const updateFilter = (key: keyof TipoConsultaFilters, value: string) => {
    const next = new URLSearchParams(searchParams);

    if (!value) {
      next.delete(key);
    } else {
      next.set(key, value);
    }

    setSearchParams(next, { replace: true });
  };

  const clearFilters = () => {
    setSearchParams(new URLSearchParams(), { replace: true });
  };

  return (
    <main className="page-content">
      <h1>Catalogo de Servicios</h1>

      <section className="filters-card" aria-label="Filtros del catalogo">
        <div className="filters-grid">
          <label>
            Buscar servicio
            <input
              type="search"
              value={filters.search}
              onChange={(event) => updateFilter('search', event.target.value)}
              placeholder="Ej: tarot, carta astral, runas"
            />
          </label>

          <label>
            Categoria
            <select value={filters.categoria} onChange={(event) => updateFilter('categoria', event.target.value)}>
              <option value="">Todas</option>
              <option value="tarot">Tarot y mancias</option>
              <option value="astrologia">Astrologia</option>
              <option value="otros">Otros</option>
            </select>
          </label>

          <label>
            Duracion
            <select value={filters.duracion} onChange={(event) => updateFilter('duracion', event.target.value)}>
              <option value="">Cualquiera</option>
              <option value="30">Hasta 30 min</option>
              <option value="60">60 min</option>
              <option value="90">90 min</option>
              <option value="120">120 min o mas</option>
            </select>
          </label>

          <label>
            Datos natales
            <select
              value={filters.requiere_datos_natales}
              onChange={(event) => updateFilter('requiere_datos_natales', event.target.value)}
            >
              <option value="">Indistinto</option>
              <option value="true">Requiere datos natales</option>
              <option value="false">No requiere datos natales</option>
            </select>
          </label>

          <label>
            Precio minimo (centavos)
            <input
              type="number"
              min={0}
              value={filters.precio_min}
              onChange={(event) => updateFilter('precio_min', event.target.value)}
              placeholder="0"
            />
          </label>

          <label>
            Precio maximo (centavos)
            <input
              type="number"
              min={0}
              value={filters.precio_max}
              onChange={(event) => updateFilter('precio_max', event.target.value)}
              placeholder="100000"
            />
          </label>
        </div>

        <div className="filters-actions">
          <button className="btn-secondary" type="button" onClick={clearFilters}>
            Limpiar filtros
          </button>
          {isFetching ? <span>Actualizando resultados...</span> : null}
        </div>
      </section>

      {isLoading ? <p>Cargando servicios...</p> : null}
      {errorText ? <p className="form-error">{errorText}</p> : null}

      <section className="cards-grid">
        {data?.data.map((tipo, index) => (
          <motion.article
            key={tipo.id}
            className="service-card"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05, duration: 0.35 }}
          >
            <h3>{tipo.nombre}</h3>
            <p>{tipo.descripcion}</p>
            <span className="service-pill">{tipo.categoria}</span>
            <strong>{formatPrice(tipo.precio_centavos, tipo.moneda)}</strong>
            <span>{tipo.duracion_minutos} min</span>
            <Link to={`/servicios/${tipo.slug}`}>Ver detalle</Link>
          </motion.article>
        ))}
      </section>

      {!isLoading && data?.data.length === 0 ? <p>No se encontraron servicios con los filtros actuales.</p> : null}
    </main>
  );
}
