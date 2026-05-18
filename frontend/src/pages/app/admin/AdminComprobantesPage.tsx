import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

type Comprobante = {
  id:              number;
  uuid:            string;
  estado_validacion: string;
  monto_centavos?: number;
  moneda?:         string;
  archivo_url?:    string | null;
  creado_en?:      string;
  cliente_email?:  string | null;
  cita_uuid?:      string | null;
  razon_rechazo?:  string | null;
};

type PaginatedComprobantes = {
  data: Comprobante[];
  meta?: { total: number };
};

const ESTADO_LABELS: Record<string, string> = {
  pendiente:            'Pendiente IA',
  aprobado_automatico:  'Aprobado auto',
  rechazado_automatico: 'Rechazado auto',
  revision_requerida:   'Revisión manual',
  aprobado_manual:      'Aprobado',
  rechazado_manual:     'Rechazado',
};

function estadoBadge(estado: string) {
  const label = ESTADO_LABELS[estado] ?? estado;
  const colorClass =
    estado.startsWith('aprobado') ? 'estado-confirmada' :
    estado.startsWith('rechazado') ? 'estado-cancelada' :
    'estado-pendiente';
  return (
    <span className={`service-pill ${colorClass}`} style={{ fontSize: '0.75rem', padding: '2px 10px' }}>
      {label}
    </span>
  );
}

export function AdminComprobantesPage() {
  const queryClient = useQueryClient();
  const [pendingAprobar, setPendingAprobar] = useState<number | null>(null);
  const [pendingRechazar, setPendingRechazar] = useState<{ id: number; razon: string } | null>(null);
  const [filtroEstado, setFiltroEstado] = useState<string>('');

  const listQuery = useQuery({
    queryKey: ['admin', 'comprobantes', 'list'],
    queryFn:  async () => (await api.get<PaginatedComprobantes>('/pagos/comprobantes')).data,
  });

  const aprobarMutation = useMutation({
    mutationFn: (id: number) => api.post(`/admin/comprobantes/${id}/aprobar`),
    onSuccess:  () => { setPendingAprobar(null); queryClient.invalidateQueries({ queryKey: ['admin', 'comprobantes'] }); },
  });

  const rechazarMutation = useMutation({
    mutationFn: ({ id, razon }: { id: number; razon: string }) =>
      api.post(`/admin/comprobantes/${id}/rechazar`, { razon_rechazo: razon }),
    onSuccess:  () => { setPendingRechazar(null); queryClient.invalidateQueries({ queryKey: ['admin', 'comprobantes'] }); },
  });

  const rows = (listQuery.data?.data ?? []).filter((r) =>
    filtroEstado ? r.estado_validacion === filtroEstado : true
  );

  const isPending = (estado: string) =>
    ['pendiente', 'revision_requerida'].includes(estado);

  const columns: Column<Comprobante>[] = [
    { key: 'uuid',    label: 'UUID',    render: (r) => r.uuid?.slice(0, 8) + '…' },
    { key: 'estado',  label: 'Estado',  render: (r) => estadoBadge(r.estado_validacion) },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente_email ?? '—' },
    { key: 'creado',  label: 'Fecha',   render: (r) => r.creado_en ? new Date(r.creado_en).toLocaleDateString('es-CL') : '—' },
    {
      key: 'archivo',
      label: 'Comprobante',
      render: (r) => r.archivo_url
        ? <a href={r.archivo_url} target="_blank" rel="noreferrer" style={{ color: 'var(--accent)' }}>Ver archivo</a>
        : '—',
    },
    {
      key: 'acciones',
      label: 'Acciones',
      render: (r) => isPending(r.estado_validacion) ? (
        <div className="admin-row-actions">
          <button
            className="btn-primary"
            onClick={(e) => { e.stopPropagation(); setPendingAprobar(r.id); }}
          >
            Confirmar
          </button>
          <button
            className="btn-secondary"
            onClick={(e) => { e.stopPropagation(); setPendingRechazar({ id: r.id, razon: '' }); }}
          >
            Rechazar
          </button>
        </div>
      ) : (
        <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>—</span>
      ),
    },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Pagos Chile</p>
          <h1>Comprobantes de transferencia</h1>
          <p className="admin-page-subtitle">
            Revisa el comprobante en tu banco y confirma o rechaza cada transferencia.
          </p>
        </div>
      </header>

      <div className="admin-filter-card admin-filter-pills">
        {['', 'revision_requerida', 'pendiente', 'aprobado_manual', 'rechazado_manual'].map((e) => (
          <button
            key={e}
            type="button"
            className={filtroEstado === e ? 'btn-primary' : 'btn-secondary'}
            onClick={() => setFiltroEstado(e)}
          >
            {e === '' ? 'Todos' : (ESTADO_LABELS[e] ?? e)}
          </button>
        ))}
      </div>

      <AdminTable
        columns={columns}
        rows={rows}
        loading={listQuery.isLoading}
        rowKey={(r) => r.id}
      />

      {/* Modal: confirmar aprobación */}
      {pendingAprobar !== null && (
        <div className="confirm-dialog-backdrop" onClick={() => setPendingAprobar(null)}>
          <div className="confirm-dialog" onClick={(e) => e.stopPropagation()}>
            <h3>Confirmar transferencia</h3>
            <p>¿Ya verificaste el depósito en tu cuenta bancaria?<br />Se notificará al cliente por correo.</p>
            <div className="confirm-dialog-actions">
              <button className="btn-secondary" onClick={() => setPendingAprobar(null)}>Cancelar</button>
              <button
                className="btn-primary"
                disabled={aprobarMutation.isPending}
                onClick={() => aprobarMutation.mutate(pendingAprobar)}
              >
                {aprobarMutation.isPending ? 'Confirmando…' : 'Confirmar transferencia'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal: rechazar con razón */}
      {pendingRechazar !== null && (
        <div className="confirm-dialog-backdrop" onClick={() => setPendingRechazar(null)}>
          <div className="confirm-dialog" onClick={(e) => e.stopPropagation()}>
            <h3>Rechazar comprobante</h3>
            <p className="text-muted">El cliente recibirá un correo con la razón del rechazo y deberá adjuntar un nuevo comprobante.</p>
            <div style={{ marginTop: '1rem' }}>
              <label style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
                <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>Motivo del rechazo *</span>
                <textarea
                  className="input-field"
                  style={{ width: '100%', minHeight: '80px', resize: 'vertical' }}
                  placeholder="Ej: El monto no corresponde, la imagen es ilegible, la transferencia es de otra persona…"
                  value={pendingRechazar.razon}
                  onChange={(e) => setPendingRechazar((p) => p ? { ...p, razon: e.target.value } : null)}
                  maxLength={500}
                />
              </label>
              {rechazarMutation.isError && (
                <p className="form-error">No se pudo rechazar. Intenta de nuevo.</p>
              )}
            </div>
            <div className="confirm-dialog-actions">
              <button className="btn-secondary" onClick={() => setPendingRechazar(null)}>Cancelar</button>
              <button
                className="btn-primary"
                disabled={rechazarMutation.isPending || !pendingRechazar.razon.trim()}
                onClick={() => pendingRechazar.razon.trim() && rechazarMutation.mutate({
                  id:    pendingRechazar.id,
                  razon: pendingRechazar.razon.trim(),
                })}
              >
                {rechazarMutation.isPending ? 'Rechazando…' : 'Rechazar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
