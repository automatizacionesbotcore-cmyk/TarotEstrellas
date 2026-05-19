import { useEffect, useState } from 'react';
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
  archivo_tipo?:   string | null;
  archivo_nombre?: string | null;
  archivo_preview_url?: string | null;
  creado_en?:      string;
  cliente_email?:  string | null;
  cliente_nombre?: string | null;
  cliente_telefono?: string | null;
  cliente_pais?:   string | null;
  cita_uuid?:      string | null;
  cita_fecha?:     string | null;
  cita_estado?:    string | null;
  codigo_referencia?: string | null;
  servicio_nombre?: string | null;
  especialista_nombre?: string | null;
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
  const [preview, setPreview] = useState<{ row: Comprobante; url: string; mime: string } | null>(null);
  const [previewLoadingId, setPreviewLoadingId] = useState<number | null>(null);
  const [previewError, setPreviewError] = useState('');

  useEffect(() => () => {
    if (preview?.url) URL.revokeObjectURL(preview.url);
  }, [preview?.url]);

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

  const clienteDisplay = (r: Comprobante) =>
    r.cliente_nombre || r.cliente_email || '—';

  const formatDate = (value?: string | null, withTime = false) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('es-CL', {
      dateStyle: 'short',
      timeStyle: withTime ? 'short' : undefined,
    });
  };

  const openPreview = async (row: Comprobante) => {
    if (!row.archivo_preview_url) return;
    setPreviewLoadingId(row.id);
    setPreviewError('');
    try {
      const response = await api.get(row.archivo_preview_url, { responseType: 'blob' });
      const mime = response.headers['content-type'] || row.archivo_tipo || response.data.type || 'application/octet-stream';
      const objectUrl = URL.createObjectURL(response.data);
      setPreview((current) => {
        if (current?.url) URL.revokeObjectURL(current.url);
        return { row, url: objectUrl, mime };
      });
    } catch {
      setPreviewError('No se pudo cargar la vista previa del comprobante.');
    } finally {
      setPreviewLoadingId(null);
    }
  };

  const closePreview = () => {
    setPreview((current) => {
      if (current?.url) URL.revokeObjectURL(current.url);
      return null;
    });
  };

  const columns: Column<Comprobante>[] = [
    { key: 'uuid',    label: 'UUID',    render: (r) => r.uuid?.slice(0, 8) + '…' },
    { key: 'estado',  label: 'Estado',  render: (r) => estadoBadge(r.estado_validacion) },
    {
      key: 'cliente',
      label: 'Cliente',
      render: (r) => (
        <div className="comprobante-client-cell">
          <strong>{clienteDisplay(r)}</strong>
          {r.cliente_email && r.cliente_nombre ? <span>{r.cliente_email}</span> : null}
          {r.cliente_telefono ? <span>{r.cliente_telefono}</span> : null}
        </div>
      ),
    },
    { key: 'creado',  label: 'Fecha',   render: (r) => formatDate(r.cita_fecha ?? r.creado_en, true) },
    {
      key: 'archivo',
      label: 'Comprobante',
      render: (r) => r.archivo_preview_url
        ? (
          <button
            type="button"
            className="link-button comprobante-preview-link"
            disabled={previewLoadingId === r.id}
            onClick={(e) => { e.stopPropagation(); openPreview(r); }}
          >
            {previewLoadingId === r.id ? 'Cargando…' : 'Vista previa'}
          </button>
        )
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
        getSearchText={(r) => [
          r.uuid,
          r.estado_validacion,
          r.cliente_nombre,
          r.cliente_email,
          r.cliente_telefono,
          r.servicio_nombre,
          r.especialista_nombre,
          r.codigo_referencia,
        ].filter(Boolean).join(' ')}
      />

      {previewError ? <p className="form-error">{previewError}</p> : null}

      {preview !== null && (
        <div className="admin-modal-backdrop" onClick={closePreview}>
          <div className="admin-modal comprobante-preview-modal" onClick={(e) => e.stopPropagation()}>
            <header className="admin-modal-header">
              <div>
                <p className="dash-eyebrow">Comprobante</p>
                <h2>{clienteDisplay(preview.row)}</h2>
              </div>
              <button type="button" className="admin-modal-close" onClick={closePreview} aria-label="Cerrar vista previa">
                ×
              </button>
            </header>

            <div className="comprobante-preview-layout">
              <section className="comprobante-preview-frame">
                {preview.mime.startsWith('image/') ? (
                  <img src={preview.url} alt={`Comprobante de ${clienteDisplay(preview.row)}`} />
                ) : preview.mime === 'application/pdf' ? (
                  <iframe src={preview.url} title="Vista previa del comprobante" />
                ) : (
                  <div className="dash-empty">
                    <p>Este tipo de archivo no tiene vista previa integrada.</p>
                    <a className="btn-secondary" href={preview.url} download={preview.row.archivo_nombre ?? 'comprobante'}>
                      Descargar archivo
                    </a>
                  </div>
                )}
              </section>

              <aside className="comprobante-preview-details">
                <h3>Detalles del cliente</h3>
                <dl>
                  <dt>Nombre</dt><dd>{preview.row.cliente_nombre ?? '—'}</dd>
                  <dt>Correo</dt><dd>{preview.row.cliente_email ?? '—'}</dd>
                  <dt>Teléfono</dt><dd>{preview.row.cliente_telefono ?? '—'}</dd>
                  <dt>País</dt><dd>{preview.row.cliente_pais ?? '—'}</dd>
                </dl>

                <h3>Detalles de la cita</h3>
                <dl>
                  <dt>Servicio</dt><dd>{preview.row.servicio_nombre ?? '—'}</dd>
                  <dt>Especialista</dt><dd>{preview.row.especialista_nombre ?? '—'}</dd>
                  <dt>Fecha</dt><dd>{formatDate(preview.row.cita_fecha, true)}</dd>
                  <dt>Referencia</dt><dd>{preview.row.codigo_referencia ?? '—'}</dd>
                  <dt>Estado</dt><dd>{estadoBadge(preview.row.estado_validacion)}</dd>
                </dl>
              </aside>
            </div>
          </div>
        </div>
      )}

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
