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
  tiene_transcripcion?: boolean;
  total_transcripciones?: number;
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
  const [reprogramarTarget, setReprogramarTarget] = useState<Cita | null>(null);
  const [saldoTarget, setSaldoTarget] = useState<Cita | null>(null);
  const [saldoReferencia, setSaldoReferencia] = useState('');
  const [saldoCanal, setSaldoCanal] = useState('whatsapp');
  const [saldoNota, setSaldoNota] = useState('');
  const [nuevoInicio, setNuevoInicio] = useState('');
  const [motivoReprogramacion, setMotivoReprogramacion] = useState('');
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

  const reprogramar = useMutation({
    mutationFn: (payload: { uuid: string; inicio_local: string; motivo?: string }) =>
      api.post(`/admin/citas/${payload.uuid}/reprogramar`, {
        inicio_local: payload.inicio_local,
        zona_horaria: ADMIN_TIMEZONE,
        motivo: payload.motivo || undefined,
      }),
    onSuccess: () => {
      setReprogramarTarget(null);
      setNuevoInicio('');
      setMotivoReprogramacion('');
      api.get<Historial>('/admin/citas/historial', {
        params: { ...filters, q: debouncedQ || undefined, page, per_page: 25, sort_by: sortBy, sort_dir: sortDir },
      }).then((res) => setData(res.data));
    },
  });

  const marcarSaldo = useMutation({
    mutationFn: (payload: { uuid: string; referencia?: string; canal_origen: string; nota?: string }) =>
      api.post(`/admin/citas/${payload.uuid}/marcar-saldo-pagado`, {
        referencia: payload.referencia || undefined,
        canal_origen: payload.canal_origen,
        nota: payload.nota || undefined,
      }),
    onSuccess: () => {
      setSaldoTarget(null);
      setSaldoReferencia('');
      setSaldoCanal('whatsapp');
      setSaldoNota('');
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
      alert('No se pudo descargar la transcripción. Verifica que la cita tenga transcripción y que tengas permiso para verla.');
    } finally {
      setTranscribiendo(null);
    }
  };

  const tieneTranscripcion = (cita: Cita) =>
    cita.tiene_transcripcion === true || Number(cita.total_transcripciones ?? 0) > 0;

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
        <div className="admin-row-actions admin-citas-actions">
          {(r.estado === 'reservada' || r.estado === 'confirmada') && (
            <button className="btn-secondary admin-action-btn" onClick={() => setTargetUuid(r.uuid)}>
              No-show
            </button>
          )}
          {r.estado === 'reservada' && (
            <button
              className="btn-primary admin-action-btn"
              onClick={() => setSaldoTarget(r)}
            >
              Saldo pagado
            </button>
          )}
          {(r.estado === 'reservada' || r.estado === 'confirmada') && (
            <button
              className="btn-secondary admin-action-btn"
              onClick={() => {
                setReprogramarTarget(r);
                setNuevoInicio(toDatetimeLocal(r.inicio_utc));
              }}
            >
              Reprog.
            </button>
          )}
          <button
            className="btn-secondary admin-action-btn"
            title="Descargar transcripción cruda (admin)"
            onClick={() => descargarTranscripcion(r.uuid)}
            disabled={transcribiendo === r.uuid || !tieneTranscripcion(r)}
          >
            {transcribiendo === r.uuid ? 'Descargando…' : tieneTranscripcion(r) ? 'Transcrip.' : 'Sin transcrip.'}
          </button>
        </div>
      ),
    },
  ];

  return (
    <main className="page-content admin-citas-page">
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
          style={{ width: '100%' }}
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

      {reprogramarTarget ? (
        <div className="admin-modal-backdrop" role="presentation">
          <section className="admin-modal" role="dialog" aria-modal="true" aria-labelledby="reprogramar-title">
            <header className="admin-modal-header">
              <h2 id="reprogramar-title">Reprogramar cita</h2>
              <button className="admin-modal-close" type="button" onClick={() => setReprogramarTarget(null)}>×</button>
            </header>
            <div className="admin-modal-body">
              <p className="text-muted">
                Se notificará al cliente por correo para que acepte la nueva fecha o elija otra disponible.
              </p>
              <label>
                Nueva fecha y hora (Chile)
                <input
                  className="input-field"
                  type="datetime-local"
                  value={nuevoInicio}
                  onChange={(e) => setNuevoInicio(e.target.value)}
                />
              </label>
              <label>
                Motivo visible para el cliente
                <textarea
                  className="input-field"
                  rows={3}
                  value={motivoReprogramacion}
                  onChange={(e) => setMotivoReprogramacion(e.target.value)}
                  placeholder="Ej: ajuste extraordinario de agenda"
                />
              </label>
              {reprogramar.isError ? <p className="form-error">No se pudo reprogramar la cita. Revisa la disponibilidad.</p> : null}
            </div>
            <div className="admin-modal-footer">
              <button className="btn-secondary" type="button" onClick={() => setReprogramarTarget(null)}>
                Cancelar
              </button>
              <button
                className="btn-primary"
                type="button"
                disabled={!nuevoInicio || reprogramar.isPending}
                onClick={() => reprogramar.mutate({
                  uuid: reprogramarTarget.uuid,
                  inicio_local: toBackendLocalDateTime(nuevoInicio),
                  motivo: motivoReprogramacion,
                })}
              >
                {reprogramar.isPending ? 'Enviando...' : 'Reprogramar y notificar'}
              </button>
            </div>
          </section>
        </div>
      ) : null}

      {saldoTarget ? (
        <div className="admin-modal-backdrop" role="presentation">
          <section className="admin-modal" role="dialog" aria-modal="true" aria-labelledby="saldo-manual-title">
            <header className="admin-modal-header">
              <h2 id="saldo-manual-title">Marcar saldo pagado</h2>
              <button className="admin-modal-close" type="button" onClick={() => setSaldoTarget(null)}>×</button>
            </header>
            <div className="admin-modal-body">
              <p className="text-muted">
                Usa esta opción si el cliente pagó la diferencia por WhatsApp, correo u otro canal externo.
                La cita quedará confirmada y ya no aparecerá el botón de pagar diferencia en recordatorios.
              </p>
              <label>
                Canal de pago
                <select className="input-field" value={saldoCanal} onChange={(e) => setSaldoCanal(e.target.value)}>
                  <option value="whatsapp">WhatsApp</option>
                  <option value="email">Correo</option>
                  <option value="telefono">Teléfono</option>
                  <option value="otro">Otro</option>
                </select>
              </label>
              <label>
                Referencia del pago
                <input className="input-field" value={saldoReferencia} onChange={(e) => setSaldoReferencia(e.target.value)} placeholder="Ej: transferencia, comprobante, mensaje interno" />
              </label>
              <label>
                Nota interna
                <textarea className="input-field" rows={3} value={saldoNota} onChange={(e) => setSaldoNota(e.target.value)} />
              </label>
              {marcarSaldo.isError ? <p className="form-error">No se pudo marcar el saldo como pagado.</p> : null}
            </div>
            <div className="admin-modal-footer">
              <button className="btn-secondary" type="button" onClick={() => setSaldoTarget(null)}>Cancelar</button>
              <button
                className="btn-primary"
                type="button"
                disabled={marcarSaldo.isPending}
                onClick={() => marcarSaldo.mutate({
                  uuid: saldoTarget.uuid,
                  referencia: saldoReferencia,
                  canal_origen: saldoCanal,
                  nota: saldoNota,
                })}
              >
                {marcarSaldo.isPending ? 'Guardando...' : 'Confirmar saldo pagado'}
              </button>
            </div>
          </section>
        </div>
      ) : null}
    </main>
  );
}

function toDatetimeLocal(iso: string): string {
  const date = new Date(iso);
  const parts = new Intl.DateTimeFormat('sv-SE', {
    timeZone: ADMIN_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '00';
  return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

function toBackendLocalDateTime(value: string): string {
  return value ? `${value.replace('T', ' ')}:00` : '';
}

function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);
  useEffect(() => {
    const handler = setTimeout(() => setDebouncedValue(value), delay);
    return () => clearTimeout(handler);
  }, [value, delay]);
  return debouncedValue;
}
