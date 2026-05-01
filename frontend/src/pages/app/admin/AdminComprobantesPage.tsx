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
  aprobado_automatico:  '✅ Aprobado auto',
  rechazado_automatico: '❌ Rechazado auto',
  revision_requerida:   '🔍 Revisión manual',
  aprobado_manual:      '✅ Aprobado',
  rechazado_manual:     '❌ Rechazado',
};

function estadoBadge(estado: string) {
  const label = ESTADO_LABELS[estado] ?? estado;
  const color =
    estado.startsWith('aprobado') ? '#166534' :
    estado.startsWith('rechazado') ? '#7f1d1d' :
    '#44403c';
  return (
    <span style={{
      background: color, color: '#fff',
      borderRadius: '99px', padding: '2px 10px', fontSize: '0.75rem',
    }}>
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
        <>
          <button
            className="btn-primary"
            style={{ marginRight: '.25rem', fontSize: '0.8rem', padding: '0.25rem 0.6rem' }}
            onClick={(e) => { e.stopPropagation(); setPendingAprobar(r.id); }}
          >
            Confirmar ✓
          </button>
          <button
            className="btn-secondary"
            style={{ fontSize: '0.8rem', padding: '0.25rem 0.6rem' }}
            onClick={(e) => { e.stopPropagation(); setPendingRechazar({ id: r.id, razon: '' }); }}
          >
            Rechazar ✗
          </button>
        </>
      ) : (
        <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>—</span>
      ),
    },
  ];

  return (
    <main className="page-content">
      <div style={{ marginBottom: '1.5rem' }}>
        <p className="dash-eyebrow">✦ Pagos Chile</p>
        <h1 className="dash-title" style={{ margin: 0 }}>Comprobantes de transferencia</h1>
        <p className="dash-subtitle" style={{ marginTop: '0.25rem' }}>
          Revisa el comprobante en tu banco y confirma o rechaza cada transferencia.
        </p>
      </div>

      {/* Filtro estado */}
      <div style={{ marginBottom: '1rem', display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
        {['', 'revision_requerida', 'pendiente', 'aprobado_manual', 'rechazado_manual'].map((e) => (
          <button
            key={e}
            type="button"
            className={filtroEstado === e ? 'btn-primary' : 'btn-secondary'}
            style={{ fontSize: '0.8rem', padding: '0.25rem 0.75rem' }}
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
        <div className="modal-overlay" onClick={() => setPendingAprobar(null)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <h3>Confirmar transferencia</h3>
            <p>¿Ya verificaste el depósito en tu cuenta bancaria?<br />Se notificará al cliente por correo.</p>
            <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.25rem', justifyContent: 'flex-end' }}>
              <button className="btn-secondary" onClick={() => setPendingAprobar(null)}>Cancelar</button>
              <button
                className="btn-primary"
                disabled={aprobarMutation.isPending}
                onClick={() => aprobarMutation.mutate(pendingAprobar)}
              >
                {aprobarMutation.isPending ? 'Confirmando…' : '✓ Confirmar transferencia'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal: rechazar con razón */}
      {pendingRechazar !== null && (
        <div className="modal-overlay" onClick={() => setPendingRechazar(null)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <h3>Rechazar comprobante</h3>
            <p>El cliente recibirá un correo con la razón del rechazo y deberá adjuntar un nuevo comprobante.</p>
            <div style={{ marginTop: '1rem' }}>
              <label className="form-label">Motivo del rechazo *</label>
              <textarea
                className="form-input"
                style={{ width: '100%', minHeight: '80px', marginTop: '0.4rem' }}
                placeholder="Ej: El monto no corresponde, la imagen es ilegible, la transferencia es de otra persona…"
                value={pendingRechazar.razon}
                onChange={(e) => setPendingRechazar((p) => p ? { ...p, razon: e.target.value } : null)}
                maxLength={500}
              />
              {rechazarMutation.isError && (
                <p className="form-error">No se pudo rechazar. Intenta de nuevo.</p>
              )}
            </div>
            <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.25rem', justifyContent: 'flex-end' }}>
              <button className="btn-secondary" onClick={() => setPendingRechazar(null)}>Cancelar</button>
              <button
                className="btn-primary"
                disabled={rechazarMutation.isPending || !pendingRechazar.razon.trim()}
                onClick={() => pendingRechazar.razon.trim() && rechazarMutation.mutate({
                  id:    pendingRechazar.id,
                  razon: pendingRechazar.razon.trim(),
                })}
              >
                {rechazarMutation.isPending ? 'Rechazando…' : '✗ Rechazar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
