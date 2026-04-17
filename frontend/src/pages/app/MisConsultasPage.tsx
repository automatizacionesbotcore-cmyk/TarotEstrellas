import { useQuery } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';

type CitaListItem = {
  id: number;
  estado: string;
  inicio_utc: string;
  fin_utc: string;
  reservada_hasta: string | null;
  moneda: string;
  precio_total_centavos: number;
  precio_final_centavos: number;
  timezone_cliente: string;
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

function formatMoney(valueInCents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(valueInCents / 100);
}

function formatDate(value: string, timezone: string) {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: timezone || undefined,
  }).format(new Date(value));
}

function normalizeEstado(estado: string) {
  const labels: Record<string, string> = {
    pendiente_abono: 'Pendiente de abono',
    reservada: 'Reservada',
    confirmada: 'Confirmada',
    completada: 'Completada',
    cancelada: 'Cancelada',
    expirada: 'Expirada',
  };

  return labels[estado] ?? estado;
}

async function fetchMisConsultas(): Promise<CitasResponse> {
  const response = await api.get('/citas', {
    params: {
      per_page: 20,
    },
  });

  return response.data as CitasResponse;
}

export function MisConsultasPage() {
  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ['mis-consultas'],
    queryFn: fetchMisConsultas,
  });

  const backendMessage = ((error as AxiosError<{ message?: string }>)?.response?.data?.message as string | undefined) ??
    null;

  return (
    <main className="page-content">
      <h1>Mis consultas</h1>
      <p>Aqui puedes ver tus reservas recientes y su estado.</p>

      {isFetching ? <p>Actualizando consultas...</p> : null}
      {isLoading ? <p>Cargando consultas...</p> : null}
      {isError ? <p className="form-error">{backendMessage ?? 'No se pudieron cargar tus consultas.'}</p> : null}

      {!isLoading && !isError && data?.data.length === 0 ? (
        <div className="auth-card">
          <p>Aun no tienes consultas agendadas.</p>
          <Link className="btn-primary" to="/servicios">
            Explorar servicios
          </Link>
        </div>
      ) : null}

      <section className="cards-grid">
        {data?.data.map((cita) => (
          <article key={cita.id} className="service-card">
            <h3>{cita.tipo_consulta?.nombre ?? 'Consulta'}</h3>
            <span className="service-pill">{normalizeEstado(cita.estado)}</span>
            <p>
              Inicio: {cita.inicio_utc ? formatDate(cita.inicio_utc, cita.timezone_cliente) : 'Sin fecha'}
            </p>
            <p>
              Duracion: {cita.tipo_consulta?.duracion_minutos ?? 0} min
            </p>
            <p>
              Abono: {formatMoney(cita.precio_final_centavos, cita.moneda)}
            </p>
            <p>
              Total: {formatMoney(cita.precio_total_centavos, cita.moneda)}
            </p>
            {cita.tema_principal ? <p>Tema: {cita.tema_principal}</p> : null}
            <Link to={cita.tipo_consulta?.slug ? `/servicios/${cita.tipo_consulta.slug}` : '/servicios'}>
              Ver servicio
            </Link>
          </article>
        ))}
      </section>
    </main>
  );
}
