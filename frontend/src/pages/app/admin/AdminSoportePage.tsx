import { useEffect, useState } from 'react';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import {
  downloadSupportAttachment,
  getAdminSupportTicket,
  listAdminSupportTickets,
  SUPPORT_STATUS_LABELS,
  SUPPORT_TYPES,
  updateAdminSupportTicket,
  type SupportTicket,
} from '../../../lib/supportApi';

const ESTADOS = [
  { value: 'todos', label: 'Todos' },
  { value: 'nuevo', label: 'Nuevo' },
  { value: 'en_revision', label: 'En revisión' },
  { value: 'esperando_usuario', label: 'Esperando usuario' },
  { value: 'resuelto', label: 'Resuelto' },
  { value: 'cerrado', label: 'Cerrado' },
];

const PRIORIDADES = ['baja', 'normal', 'alta', 'urgente'];

export function AdminSoportePage() {
  const [filters, setFilters] = useState({ q: '', estado: 'todos', tipo_error: 'todos', page: 1, per_page: 15 });
  const [items, setItems] = useState<SupportTicket[]>([]);
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null);
  const [selected, setSelected] = useState<SupportTicket | null>(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [estado, setEstado] = useState('en_revision');
  const [prioridad, setPrioridad] = useState('normal');
  const [respuesta, setRespuesta] = useState('');
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    let canceled = false;
    setLoading(true);
    setError(null);
    listAdminSupportTickets(filters)
      .then((res) => {
        if (canceled) return;
        setItems(res.data);
        setMeta(res.meta);
      })
      .catch((err) => !canceled && setError(err?.response?.data?.message ?? 'No se pudieron cargar los tickets.'))
      .finally(() => !canceled && setLoading(false));
    return () => {
      canceled = true;
    };
  }, [filters]);

  const openTicket = async (ticket: SupportTicket) => {
    setError(null);
    const detail = await getAdminSupportTicket(ticket.uuid);
    setSelected(detail);
    setEstado(detail.estado);
    setPrioridad(detail.prioridad);
    setRespuesta('');
    setVisible(true);
  };

  const saveTicket = async () => {
    if (!selected) return;
    setSaving(true);
    setError(null);
    try {
      const res = await updateAdminSupportTicket(selected.uuid, {
        estado,
        prioridad,
        respuesta: respuesta.trim() || undefined,
        visible_para_cliente: visible,
      });
      setSelected(res.data);
      setRespuesta('');
      const refreshed = await listAdminSupportTickets(filters);
      setItems(refreshed.data);
      setMeta(refreshed.meta);
    } catch (err: any) {
      setError(err?.response?.data?.message ?? 'No se pudo actualizar el ticket.');
    } finally {
      setSaving(false);
    }
  };

  const typeLabel = (value: string) => SUPPORT_TYPES.find((t) => t.value === value)?.label ?? value;

  const columns: Column<SupportTicket>[] = [
    { key: 'codigo', label: 'Código', render: (t) => <strong>{t.codigo}</strong> },
    { key: 'estado', label: 'Estado', render: (t) => <span className={`badge estado-${t.estado}`}>{SUPPORT_STATUS_LABELS[t.estado] ?? t.estado}</span> },
    { key: 'tipo', label: 'Tipo', render: (t) => typeLabel(t.tipo_error) },
    {
      key: 'cliente',
      label: 'Usuario',
      render: (t) => (
        <div className="admin-cell-stack">
          <strong className="admin-cell-title">{t.nombre || t.cliente?.name || 'Sin nombre'}</strong>
          <span className="admin-cell-muted">{t.email}</span>
        </div>
      ),
    },
    { key: 'asunto', label: 'Asunto', render: (t) => t.asunto },
    { key: 'fecha', label: 'Último cambio', render: (t) => t.ultimo_cambio_at ? new Date(t.ultimo_cambio_at).toLocaleString('es-CL') : '—' },
    { key: 'acciones', label: 'Acciones', render: (t) => <button className="btn-secondary" type="button" onClick={() => openTicket(t)}>Gestionar</button> },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Ticketera</p>
          <h1>Soporte de plataforma</h1>
          <p className="admin-page-subtitle">
            Solicitudes de usuarios nuevos, clientes y problemas de acceso o uso de la plataforma.
          </p>
        </div>
        {meta ? <span className="admin-total-pill">{meta.total} tickets</span> : null}
      </header>

      <section className="admin-filter-card">
        <div className="admin-filter-grid">
          <label>
            Buscar
            <input className="input-field" value={filters.q} onChange={(e) => setFilters((f) => ({ ...f, q: e.target.value, page: 1 }))} />
          </label>
          <label>
            Estado
            <select className="input-field" value={filters.estado} onChange={(e) => setFilters((f) => ({ ...f, estado: e.target.value, page: 1 }))}>
              {ESTADOS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
            </select>
          </label>
          <label>
            Tipo
            <select className="input-field" value={filters.tipo_error} onChange={(e) => setFilters((f) => ({ ...f, tipo_error: e.target.value, page: 1 }))}>
              <option value="todos">Todos</option>
              {SUPPORT_TYPES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
            </select>
          </label>
        </div>
      </section>

      {error ? <p className="form-error">{error}</p> : null}

      <AdminTable
        columns={columns}
        rows={items}
        rowKey={(t) => t.uuid}
        loading={loading}
        searchable={false}
        emptyLabel="No hay tickets con esos filtros."
        pagination={meta ? {
          currentPage: meta.current_page,
          lastPage: meta.last_page,
          total: meta.total,
          onPageChange: (next) => setFilters((f) => ({ ...f, page: next })),
        } : undefined}
      />

      {selected ? (
        <section className="admin-filter-card support-detail-card">
          <div className="support-detail-header">
            <div>
              <p className="dash-eyebrow">{selected.codigo}</p>
              <h2>{selected.asunto}</h2>
              <p className="admin-page-subtitle">{selected.email} · {typeLabel(selected.tipo_error)}</p>
            </div>
            <span className={`badge estado-${selected.estado}`}>{SUPPORT_STATUS_LABELS[selected.estado] ?? selected.estado}</span>
          </div>

          {selected.adjuntos?.length ? (
            <div className="support-attachments">
              <strong>Adjuntos</strong>
              {selected.adjuntos.map((file) => (
                <button key={file.id} type="button" className="btn-secondary" onClick={() => file.download_url && downloadSupportAttachment(file.download_url, file.nombre_original)}>
                  Descargar {file.nombre_original}
                </button>
              ))}
            </div>
          ) : null}

          <div className="support-thread">
            {selected.mensajes?.map((message) => (
              <div key={message.id} className={`support-message support-message-${message.autor_tipo}`}>
                <strong>{message.autor_tipo === 'admin' ? message.autor || 'Soporte' : selected.nombre || selected.email}</strong>
                <p>{message.mensaje}</p>
                <small>{new Date(message.created_at).toLocaleString('es-CL')}{message.visible_para_cliente === false ? ' · interno' : ''}</small>
              </div>
            ))}
          </div>

          <div className="support-admin-editor">
            <div className="admin-filter-grid">
              <label>
                Estado
                <select className="input-field" value={estado} onChange={(e) => setEstado(e.target.value)}>
                  {ESTADOS.filter((item) => item.value !== 'todos').map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                </select>
              </label>
              <label>
                Prioridad
                <select className="input-field" value={prioridad} onChange={(e) => setPrioridad(e.target.value)}>
                  {PRIORIDADES.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
              </label>
            </div>
            <label>
              Respuesta o nota
              <textarea className="input-field" rows={5} value={respuesta} onChange={(e) => setRespuesta(e.target.value)} />
            </label>
            <label className="consent-check">
              <input type="checkbox" checked={visible} onChange={(e) => setVisible(e.target.checked)} />
              <span>Enviar esta respuesta al usuario por correo y mostrarla en su módulo</span>
            </label>
            <button className="btn-primary" type="button" disabled={saving} onClick={saveTicket}>
              {saving ? 'Guardando...' : 'Actualizar ticket'}
            </button>
          </div>
        </section>
      ) : null}
    </main>
  );
}
