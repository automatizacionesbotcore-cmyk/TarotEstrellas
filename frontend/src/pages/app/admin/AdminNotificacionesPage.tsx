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
  const [total, setTotal] = useState(0);

  const load = async (
    targetPage = page,
    nextFilters = { canal, estado, desde, hasta }
  ) => {
    setLoading(true);
    try {
      const r = await listNotificaciones({
        canal: nextFilters.canal === 'all' ? undefined : nextFilters.canal,
        estado: nextFilters.estado === 'all' ? undefined : nextFilters.estado,
        desde: nextFilters.desde || undefined,
        hasta: nextFilters.hasta || undefined,
        page: targetPage,
      });
      setItems(r.data?.data ?? []);
      setLastPage(r.data?.last_page ?? 1);
      setTotal(r.data?.total ?? 0);
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

  const resetFilters = () => {
    const cleared = { canal: 'all' as const, estado: 'all' as const, desde: '', hasta: '' };
    setCanal('all');
    setEstado('all');
    setDesde('');
    setHasta('');
    setPage(1);
    load(1, cleared);
  };

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
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Comunicaciones</p>
          <h1>Notificaciones enviadas</h1>
          <p className="admin-page-subtitle">
            Revisa canales, estados y errores de entrega desde una vista operativa.
          </p>
        </div>
        {total > 0 && <span className="admin-total-pill">{total.toLocaleString()} registros</span>}
      </header>

      {metricas && (
        <section className="admin-metric-grid">
          <div className="admin-metric-card">
            <span className="admin-metric-label">Hoy</span>
            <strong className="admin-metric-value">{metricas.hoy ?? 0}</strong>
          </div>
          <div className="admin-metric-card">
            <span className="admin-metric-label">Errores hoy</span>
            <strong className="admin-metric-value admin-metric-value--danger">{metricas.errores ?? 0}</strong>
          </div>
          <div className="admin-metric-card admin-metric-card--wide">
            <span className="admin-metric-label">Por canal</span>
            <div className="admin-channel-list">
              {Object.entries(porCanal).length === 0
                ? <span className="text-muted">Sin datos</span>
                : Object.entries(porCanal).map(([k, v]) => (
                    <span key={k} className="admin-channel-chip">{k}: {v}</span>
                  ))}
            </div>
          </div>
        </section>
      )}

      <form onSubmit={(e) => { e.preventDefault(); setPage(1); load(1, { canal, estado, desde, hasta }); }}
        className="admin-filter-card">
        <div className="admin-filter-grid">
          <label>
            Canal
            <select className="form-input" value={canal} onChange={(e) => setCanal(e.target.value as any)}>
              <option value="all">Todos los canales</option>
              <option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="push">Push</option>
            </select>
          </label>
          <label>
            Estado
            <select className="form-input" value={estado} onChange={(e) => setEstado(e.target.value as any)}>
              <option value="all">Todos los estados</option>
              <option value="enviado">Enviado</option>
              <option value="error">Error</option>
              <option value="rebotado">Rebotado</option>
              <option value="leido">Leído</option>
            </select>
          </label>
          <label>
            Desde
            <input type="date" className="form-input" value={desde} onChange={(e) => setDesde(e.target.value)} />
          </label>
          <label>
            Hasta
            <input type="date" className="form-input" value={hasta} onChange={(e) => setHasta(e.target.value)} />
          </label>
        </div>
        <div className="admin-filter-actions">
          <button type="submit" className="btn-primary">Filtrar</button>
          <button type="button" className="btn-secondary" onClick={resetFilters}>Limpiar</button>
        </div>
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
          total,
          onPageChange: setPage,
        }}
      />
    </main>
  );
}
