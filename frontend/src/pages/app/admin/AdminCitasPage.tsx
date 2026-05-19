import { useState, useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminFilters, type FilterValues } from '../../../components/admin/AdminFilters';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

type Cita = {
  uuid:           string;
  estado:         string;
  codigo_referencia?: string;
  inicio_utc:     string;
  zona_horaria_cliente?: string | null;
  cliente_email?: string | null;
  cliente?:       { email: string; name?: string } | null;
  tipo_consulta?: { nombre: string } | null;
};

type Meta = {
  current_page: number;
  last_page:    number;
  total:        number;
};

type Historial = {
  data: Cita[];
  meta: Meta;
};

const ESTADOS = [
  { value: 'reservada',              label: 'Reservada'              },
  { value: 'confirmada',             label: 'Confirmada'             },
  { value: 'en_curso',               label: 'En curso'               },
  { value: 'finalizada',             label: 'Finalizada'             },
  { value: 'completada',             label: 'Completada (legacy)'    },
  { value: 'cancelada_cliente',      label: 'Cancelada cliente'      },
  { value: 'cancelada_chachita',     label: 'Cancelada especialista' },
  { value: 'no_show',                label: 'No show'                },
  { value: 'expirada',               label: 'Expirada'               },
];

type SortField = 'inicio_utc' | 'estado' | 'codigo_referencia';
const ADMIN_TIMEZONE = 'America/Santiago';

function formatDateInZone(iso: string, timeZone = ADMIN_TIMEZONE): string {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone,
  }).format(new Date(iso));
}

function formatAdminClientDate(cita: Cita): string {
  const adminDate = formatDateInZone(cita.inicio_utc, ADMIN_TIMEZONE);
  const clientTimezone = cita.zona_horaria_cliente || ADMIN_TIMEZONE;

  if (clientTimezone === ADMIN_TIMEZONE) {
    return `${adminDate} Chile`;
  }

  return `${adminDate} Chile · ${formatDateInZone(cita.inicio_utc, clientTimezone)} cliente`;
}

export function AdminCitasPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<FilterValues>({});
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [sortBy, setSortBy] = useState<SortField>('inicio_utc');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const [data, setData] = useState<Historial | null>(null);
  const [loading, setLoading] = useState(false);
  const [targetUuid, setTargetUuid] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);
  const [transcribiendo, setTranscribiendo] = useState<string | null>(null);

  const debouncedQ = useDebounce(q, 300);

  useEffect(() => {
    setPage(1); // Reset a página 1 cuando cambia búsqueda
  }, [debouncedQ, filters, sortBy, sortDir]);

  useEffect(() => {
    let canceled = false;
    setLoading(true);

    const params: Record<string, unknown> = {
      ...filters,
      q: debouncedQ || undefined,
      page,
      per_page: 25,
      sort_by: sortBy,
      sort_dir: sortDir,
    };

    api.get<Historial>('/admin/citas/historial', { params })
      .then((res) => { if (!canceled) setData(res.data); })
      .catch(() => { if (!canceled) setData(null); })
      .finally(() => { if (!canceled) setLoading(false); });

    return () => { canceled = true; };
  }, [debouncedQ, filters, page, sortBy, sortDir]);

  const noShow = useMutation({
    mutationFn: (uuid: string) => api.post(`/admin/citas/${uuid}/no-show`),
    onSuccess: () => {
      setTargetUuid(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'citas'] });
      // Re-fetch manual
      api.get<Historial>('/admin/citas/historial', {
        params: { ...filters, q: debouncedQ || undefined, page, per_page: 25, sort_by: sortBy, sort_dir: sortDir },
      }).then((res) => setData(res.data));
    },
  });

  const exportCsv = async () => {
    setExporting(true);
    try {
      const res = await api.get('/admin/citas/historial/export', {
        params: { ...filters, q: debouncedQ || undefined },
        responseType: 'blob',
      });
      const url = URL.createObjectURL(res.data as Blob);
      const a   = document.createElement('a');
      a.href    = url;
      a.download = `citas-historial-${new Date().toISOString().slice(0,10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } finally {
      setExporting(false);
    }
  };

  const descargarTranscripcion = async (uuid: string) => {
    setTranscribiendo(uuid);
    try {
      const res = await api.get(`/admin/citas/${uuid}/transcripcion/descargar`, { responseType: 'blob' });
      const url = URL.createObjectURL(res.data);
      const a = document.createElement('a');
      a.href = url;
      a.download = `transcripcion-${uuid.slice(0, 8)}.txt`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      alert('No hay transcripción disponible para esta cita.');
    } finally {
      setTranscribiendo(null);
    }
  };

  const toggleSort = (field: SortField) => {
    if (sortBy === field) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortBy(field);
      setSortDir('desc');
    }
  };

  const rows = data?.data ?? [];
  const meta = data?.meta;

  const columns: Column<Cita>[] = [
    {
      key: 'codigo_referencia',
      label: 'Código',
      sortable: true,
      render: (r) => r.codigo_referencia || `${r.uuid.slice(0, 8)}…`,
    },
    {
      key: 'estado',
      label: 'Estado',
      sortable: true,
      render: (r) => <span className={`service-pill estado-${r.estado}`}>{r.estado}</span>,
    },
    {
      key: 'tipo',
      label: 'Tipo',
      render: (r) => r.tipo_consulta?.nombre ?? '—',
    },
    {
      key: 'inicio_utc',
      label: 'Inicio',
      sortable: true,
      render: (r) => formatAdminClientDate(r),
    },
    {
      key: 'cliente',
      label: 'Cliente',
      render: (r) => r.cliente?.email ?? r.cliente_email ?? '—',
    },
    {
      key: 'acciones',
      label: 'Acciones',
      render: (r) => (
        <div className="admin-row-actions">
          {(r.estado === 'reservada' || r.estado === 'confirmada') && (
            <button className="btn-secondary" style={{ fontSize: '0.8rem', padding: '0.25rem 0.6rem' }} onClick={() => setTargetUuid(r.uuid)}>
              No-show
            </button>
          )}
          <button
            className="btn-secondary"
            style={{ fontSize: '0.8rem', padding: '0.25rem 0.6rem' }}
            title="Descargar transcripción cruda (admin)"
            onClick={() => descargarTranscripcion(r.uuid)}
            disabled={transcribiendo === r.uuid}
          >
            {transcribiendo === r.uuid ? 'Descargando…' : 'Transcripción'}
          </button>
        </div>
      ),
    },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <h1>Historial de citas</h1>
        <button className="btn-secondary" onClick={exportCsv} disabled={exporting}>
          {exporting ? 'Exportando…' : 'Exportar CSV'}
        </button>
      </header>

      {/* Buscador */}
      <div style={{ marginBottom: '1rem' }}>
        <input
          type="search"
          className="input-field"
          placeholder="Buscar por UUID, código, email o nombre de cliente…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          style={{ width: '100%', maxWidth: '500px' }}
        />
      </div>

      <AdminFilters values={filters} onChange={setFilters} estados={ESTADOS} />

      <AdminTable
        columns={columns}
        rows={rows}
        loading={loading}
        emptyLabel="No se encontraron citas con esos filtros."
        rowKey={(r) => r.uuid}
        searchable={false}
        pageSizeOptions={[]}
        sort={{ sortBy, sortDir, onSort: (field) => toggleSort(field as SortField) }}
        pagination={meta ? {
          currentPage: meta.current_page,
          lastPage: meta.last_page,
          total: meta.total,
          onPageChange: setPage,
        } : undefined}
      />

      <ConfirmDialog
        open={!!targetUuid}
        title="Marcar no-show"
        message="Esto cancelará la cita y generará reembolsos pendientes por los pagos completados. ¿Continuar?"
        loading={noShow.isPending}
        onConfirm={() => targetUuid && noShow.mutate(targetUuid)}
        onCancel={()  => setTargetUuid(null)}
      />
    </main>
  );
}

function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);
  useEffect(() => {
    const handler = setTimeout(() => setDebouncedValue(value), delay);
    return () => clearTimeout(handler);
  }, [value, delay]);
  return debouncedValue;
}
