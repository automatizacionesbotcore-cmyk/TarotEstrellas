import { useEffect, useState } from 'react';
import { listNotificaciones, getNotificacionMetricas, type Notificacion, type NotificacionMetricas } from '../../../lib/notificacionesAdminApi';
import { toast } from '../../../stores/toastStore';

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

      {loading ? <p style={{ color: 'var(--text-muted)' }}>Cargando…</p> : (
        <table className="admin-table" style={{ width: '100%' }}>
          <thead><tr><th>Fecha</th><th>Canal</th><th>Destinatario</th><th>Tipo</th><th>Asunto</th><th>Estado</th><th>Error</th></tr></thead>
          <tbody>
            {items.map((n) => (
              <tr key={n.id}>
                <td style={{ fontSize: '0.85em' }}>{n.enviado_en ? new Date(n.enviado_en).toLocaleString() : (n.created_at ? new Date(n.created_at).toLocaleString() : '—')}</td>
                <td><span className="badge">{n.canal}</span></td>
                <td>{n.destinatario}</td>
                <td><code>{n.tipo || n.plantilla_clave || '—'}</code></td>
                <td>{n.asunto || '—'}</td>
                <td>
                  <span className={`badge ${n.estado === 'enviado' || n.estado === 'leido' ? 'badge-success' : (n.estado === 'error' || n.estado === 'rebotado') ? 'badge-danger' : 'badge-muted'}`}>{n.estado}</span>
                </td>
                <td style={{ fontSize: '0.85em', color: '#dc2626' }}>{n.error || ''}</td>
              </tr>
            ))}
            {!items.length && <tr><td colSpan={7} style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-muted)' }}>Sin notificaciones.</td></tr>}
          </tbody>
        </table>
      )}

      <nav style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', marginTop: '1rem' }}>
        <button className="btn-secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button>
        <span style={{ color: 'var(--text-muted)', padding: '0.25rem 0.5rem' }}>Página {page} de {lastPage}</span>
        <button className="btn-secondary" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>›</button>
      </nav>
    </main>
  );
}
