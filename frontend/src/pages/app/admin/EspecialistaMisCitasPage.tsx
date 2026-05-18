import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

type MiCita = {
  uuid: string;
  estado: string;
  inicio_utc: string;
  duracion_minutos: number;
  servicio: string | null;
  cliente_nombre: string | null;
};

const ESTADO_LABELS: Record<string, string> = {
  reservada: 'Reservada',
  confirmada: 'Confirmada',
  finalizada: 'Finalizada',
};

function fmtDate(iso: string) {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'America/Santiago',
  }).format(new Date(iso));
}

export function EspecialistaMisCitasPage() {
  const { data, isLoading, isError } = useQuery<{ data: MiCita[] }>({
    queryKey: ['especialista', 'mis-citas'],
    queryFn: async () => (await api.get('/especialista/mis-citas')).data,
    staleTime: 60_000,
  });

  const citas = data?.data ?? [];
  const proximas = citas.filter((c) => c.estado !== 'finalizada');
  const pasadas = citas.filter((c) => c.estado === 'finalizada');
  const proximasColumns: Column<MiCita>[] = [
    { key: 'inicio_utc', label: 'Fecha', render: (c) => fmtDate(c.inicio_utc) },
    { key: 'servicio', label: 'Servicio', render: (c) => c.servicio ?? '—' },
    { key: 'cliente_nombre', label: 'Cliente', render: (c) => c.cliente_nombre ?? '—' },
    { key: 'duracion_minutos', label: 'Duración', render: (c) => `${c.duracion_minutos} min` },
    {
      key: 'estado',
      label: 'Estado',
      render: (c) => (
        <span className={`service-pill estado-${c.estado}`}>
          {ESTADO_LABELS[c.estado] ?? c.estado}
        </span>
      ),
    },
  ];
  const historialColumns: Column<MiCita>[] = proximasColumns.filter((column) => column.key !== 'estado');

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
        <p className="dash-eyebrow">✦ Mi agenda</p>
        <h1 className="dash-title">Mis citas</h1>
      </motion.div>

      {isLoading && <p className="text-muted">Cargando citas…</p>}
      {isError && <p className="form-error">No se pudieron cargar las citas.</p>}

      {!isLoading && !isError && (
        <>
          <section style={{ marginTop: '1.5rem' }}>
            <h2 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Próximas</h2>
            {proximas.length === 0 ? (
              <p className="text-muted">No tienes citas próximas.</p>
            ) : (
              <AdminTable
                columns={proximasColumns}
                rows={proximas}
                rowKey={(c) => c.uuid}
                searchable
                searchPlaceholder="Buscar cita"
                getSearchText={(c) => `${c.servicio ?? ''} ${c.cliente_nombre ?? ''} ${c.estado}`}
                pageSize={5}
                pageSizeOptions={[5, 10, 25]}
              />
            )}
          </section>

          {pasadas.length > 0 && (
            <section style={{ marginTop: '2rem' }}>
              <h2 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Historial</h2>
              <AdminTable
                columns={historialColumns}
                rows={pasadas}
                rowKey={(c) => c.uuid}
                searchable
                searchPlaceholder="Buscar en historial"
                getSearchText={(c) => `${c.servicio ?? ''} ${c.cliente_nombre ?? ''}`}
                pageSize={5}
                pageSizeOptions={[5, 10, 25]}
              />
            </section>
          )}
        </>
      )}

      <div style={{ marginTop: '1.5rem' }}>
        <Link className="btn-secondary" to="/app/dashboard">← Volver al dashboard</Link>
      </div>
    </main>
  );
}
