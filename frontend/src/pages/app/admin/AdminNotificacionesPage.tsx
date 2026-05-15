import { useEffect, useState } from 'react';
import { listNotificaciones, getNotificacionMetricas, type Notificacion, type NotificacionMetricas } from '../../../lib/notificacionesAdminApi';
import { toast } from '../../../stores/toastStore';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

export function AdminNotificacionesPage() {
  const [items, setItems] = useState<Notificacion[]>([]);
  const [metricas, setMetricas] = useState<NotificacionMetricas | null>(null);
  const [loading, setLoading] = useState(false);
  const [canal, setCanal] = useState<'all' | 'email' | 'whatsapp' | 'sms' | 'push'>('all');
  const [estado, setEstado] = useState<'all' | 'enviado' | 'error' | 'rebotado' | 'leido'>('all');
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = async () => {
    setLoading(true);
    try {
      const r = await listNotificaciones({
        canal: canal === 'all' ? undefined : canal,
        estado: estado === 'all' ? undefined : estado,
        desde: desde || undefined,
        hasta: hasta || undefined,
        page,
      });
      setItems(r.data?.data ?? []);
      setLastPage(r.data?.last_page ?? 1);
    } catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setLoading(false); }
  };

  const loadMetricas = async () => {
    try {
      const r = await getNotificacionMetricas();
      const payload: any = r.data;
      // Backend devuelve { data: {...} } - desempaquetar si es necesario
      setMetricas(payload?.data ?? payload);
    } catch {}
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page]);
  useEffect(() => { loadMetricas(); }, []);

  const porCanal = metricas?.porCanal ?? {};

  const columns: Column<Notificacion>[] = [
    {
      key: 'fecha',
      label: 'Fecha',
      render: (n) => n.enviado_en
        ? new Date(n.enviado_en).toLocaleString()
        : (n.created_at ? new Date(n.created_at).toLocaleString() : '—'),
    },
    { key: 'canal', label: 'Canal', render: (n) => <span className="badge">{n.canal}</span> },
    { key: 'destinatario', label: 'Destinatario' },
    { key: 'tipo', label: 'Tipo', render: (n) => <code>{n.tipo || n.plantilla_clave || '—'}</code> },
    { key: 'asunto', label: 'Asunto', render: (n) => n.asunto || '—' },
    {
      key: 'estado',
      label: 'Estado',
      render: (n) => (
        <span className={`badge ${n.estado === 'enviado' || n.estado === 'leido' ? 'badge-success' : (n.estado === 'error' || n.estado === 'rebotado') ? 'badge-danger' : 'badge-muted'}`}>
          {n.estado}
        </span>
      ),
    },
    { key: 'error', label: 'Error', render: (n) => <span style={{ color: '#dc2626' }}>{n.error || '—'}</span> },
  ];

  return (
    <main className="page-content">
      <h1>Notificaciones enviadas</h1>

      {metricas && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
          <div className="card" style={{ padding: '0.75rem' }}><strong>Hoy</strong><div style={{ fontSize: '1.5em' }}>{metricas.hoy ?? 0}</div></div>
          <div className="card" style={{ padding: '0.75rem' }}><strong>Errores hoy</strong><div style={{ fontSize: '1.5em' }}>{metricas.errores ?? 0}</div></div>
          <div className="card" style={{ padding: '0.75rem' }}>
            <strong>Por canal</strong>
            <div style={{ fontSize: '0.85em' }}>
              {Object.entries(porCanal).length === 0
                ? <div>—</div>
                : Object.entries(porCanal).map(([k, v]) => <div key={k}>{k}: {v}</div>)}
            </div>
          </div>
        </div>
      )}

      <form onSubmit={(e) => { e.preventDefault(); setPage(1); load(); }}
        className="admin-filters" style={{ marginBottom: '1rem' }}>
        <select className="form-input" value={canal} onChange={(e) => setCanal(e.target.value as any)}>
          <option value="all">Todos los canales</option>
          <option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="push">Push</option>
        </select>
        <select className="form-input" value={estado} onChange={(e) => setEstado(e.target.value as any)}>
          <option value="all">Todos los estados</option>
          <option value="enviado">Enviado</option>
          <option value="error">Error</option>
          <option value="rebotado">Rebotado</option>
          <option value="leido">Leído</option>
        </select>
        <input type="date" className="form-input" value={desde} onChange={(e) => setDesde(e.target.value)} />
        <input type="date" className="form-input" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        <button type="submit" className="btn-primary">Filtrar</button>
      </form>

      <AdminTable
        columns={columns}
        rows={items}
        loading={loading}
        emptyLabel="Sin notificaciones."
        rowKey={(n) => n.id}
        searchable={false}
        pagination={{
          currentPage: page,
          lastPage,
          onPageChange: setPage,
        }}
      />
    </main>
  );
}
