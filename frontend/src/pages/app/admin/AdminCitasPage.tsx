import { useState, useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminFilters, type FilterValues } from '../../../components/admin/AdminFilters';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Cita = {
  uuid:           string;
  estado:         string;
  codigo_referencia?: string;
  inicio_utc:     string;
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

      {loading ? (
        <p className="admin-table-empty">Cargando…</p>
      ) : rows.length === 0 ? (
        <p className="admin-table-empty">No se encontraron citas con esos filtros.</p>
      ) : (
        <>
          <table className="admin-table">
            <thead>
              <tr>
                <SortableHeader field="codigo_referencia" label="Código" sortBy={sortBy} sortDir={sortDir} onToggle={toggleSort} />
                <SortableHeader field="estado" label="Estado" sortBy={sortBy} sortDir={sortDir} onToggle={toggleSort} />
                <th>Tipo</th>
                <SortableHeader field="inicio_utc" label="Inicio" sortBy={sortBy} sortDir={sortDir} onToggle={toggleSort} />
                <th>Cliente</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.uuid}>
                  <td>{r.codigo_referencia || r.uuid.slice(0, 8) + '…'}</td>
                  <td><span className={`service-pill estado-${r.estado}`}>{r.estado}</span></td>
                  <td>{r.tipo_consulta?.nombre ?? '—'}</td>
                  <td>{new Date(r.inicio_utc).toLocaleString('es-CL')}</td>
                  <td>{r.cliente?.email ?? r.cliente_email ?? '—'}</td>
                  <td>
                    <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
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
                        {transcribiendo === r.uuid ? '⏳' : '📝'}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {meta && (
            <nav style={{ display: 'flex', gap: '0.75rem', justifyContent: 'center', alignItems: 'center', marginTop: '1rem' }}>
              <button className="btn-secondary" disabled={page <= 1} onClick={() => setPage(1)}>«</button>
              <button className="btn-secondary" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>‹</button>
              <span className="text-muted">Página {meta.current_page} de {meta.last_page} · {meta.total} citas</span>
              <button className="btn-secondary" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>›</button>
              <button className="btn-secondary" disabled={page >= meta.last_page} onClick={() => setPage(meta.last_page)}>»</button>
            </nav>
          )}
        </>
      )}

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

function SortableHeader({
  field,
  label,
  sortBy,
  sortDir,
  onToggle,
}: {
  field: SortField;
  label: string;
  sortBy: SortField;
  sortDir: 'asc' | 'desc';
  onToggle: (f: SortField) => void;
}) {
  const isActive = sortBy === field;
  return (
    <th
      onClick={() => onToggle(field)}
      style={{ cursor: 'pointer', userSelect: 'none' }}
      title={`Ordenar por ${label}`}
    >
      {label} {isActive && (sortDir === 'asc' ? '▲' : '▼')}
    </th>
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

