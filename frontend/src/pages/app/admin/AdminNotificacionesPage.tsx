import { useEffect, useState } from 'react';
import { listNotificaciones, getNotificacionMetricas, type Notificacion, type NotificacionMetricas } from '../../../lib/notificacionesAdminApi';
import { toast } from '../../../stores/toastStore';

export function AdminNotificacionesPage() {
  const [items, setItems] = useState<Notificacion[]>([]);
  const [metricas, setMetricas] = useState<NotificacionMetricas | null>(null);
  const [loading, setLoading] = useState(false);
  const [canal, setCanal] = useState<'all' | 'email' | 'whatsapp' | 'sms'>('all');
  const [estado, setEstado] = useState<'all' | 'pendiente' | 'enviado' | 'fallido' | 'bounced'>('all');
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
      setItems(r.data.data); setLastPage(r.data.last_page);
    } catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setLoading(false); }
  };

  const loadMetricas = async () => {
    try { const r = await getNotificacionMetricas(); setMetricas(r.data); } catch {}
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page]);
  useEffect(() => { loadMetricas(); }, []);

  return (
    <main className="page-content">
      <h1>Notificaciones enviadas</h1>

      {metricas && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
          <div className="card" style={{ padding: '0.75rem' }}><strong>Total</strong><div style={{ fontSize: '1.5em' }}>{metricas.total}</div></div>
          <div className="card" style={{ padding: '0.75rem' }}><strong>Últimas 24h</strong><div style={{ fontSize: '1.5em' }}>{metricas.ultimas_24h}</div></div>
          <div className="card" style={{ padding: '0.75rem' }}><strong>% fallidas</strong><div style={{ fontSize: '1.5em' }}>{metricas.fallidas_pct.toFixed(1)}%</div></div>
          <div className="card" style={{ padding: '0.75rem' }}>
            <strong>Por estado</strong>
            <div style={{ fontSize: '0.85em' }}>{Object.entries(metricas.por_estado).map(([k, v]) => <div key={k}>{k}: {v}</div>)}</div>
          </div>
        </div>
      )}

      <form onSubmit={(e) => { e.preventDefault(); setPage(1); load(); }}
        style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1rem' }}>
        <select value={canal} onChange={(e) => setCanal(e.target.value as any)}>
          <option value="all">Todos los canales</option>
          <option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option>
        </select>
        <select value={estado} onChange={(e) => setEstado(e.target.value as any)}>
          <option value="all">Todos los estados</option>
          <option value="pendiente">Pendiente</option><option value="enviado">Enviado</option>
          <option value="fallido">Fallido</option><option value="bounced">Bounced</option>
        </select>
        <input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        <input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        <button type="submit">Filtrar</button>
      </form>

      {loading ? <p>Cargando…</p> : (
        <table className="data-table" style={{ width: '100%' }}>
          <thead><tr><th>Fecha</th><th>Canal</th><th>Destinatario</th><th>Plantilla</th><th>Asunto</th><th>Estado</th><th>Error</th></tr></thead>
          <tbody>
            {items.map((n) => (
              <tr key={n.id}>
                <td style={{ fontSize: '0.85em' }}>{new Date(n.created_at).toLocaleString()}</td>
                <td><span className="badge">{n.canal}</span></td>
                <td>{n.destinatario}</td>
                <td><code>{n.plantilla_clave || '—'}</code></td>
                <td>{n.asunto || '—'}</td>
                <td>
                  <span className={`badge ${n.estado === 'enviado' ? 'badge-success' : n.estado === 'fallido' || n.estado === 'bounced' ? 'badge-danger' : 'badge-muted'}`}>{n.estado}</span>
                </td>
                <td style={{ fontSize: '0.85em', color: 'var(--danger)' }}>{n.error || ''}</td>
              </tr>
            ))}
            {!items.length && <tr><td colSpan={7} style={{ textAlign: 'center', padding: '2rem' }}>Sin notificaciones.</td></tr>}
          </tbody>
        </table>
      )}

      <nav style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', marginTop: '1rem' }}>
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button>
        <span>Página {page} de {lastPage}</span>
        <button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>›</button>
      </nav>
    </main>
  );
}
