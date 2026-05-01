import { useEffect, useState } from 'react';
import { listAuditLog, type AuditEntry } from '../../../lib/auditAdminApi';
import { toast } from '../../../stores/toastStore';

export function AdminAuditPage() {
  const [items, setItems] = useState<AuditEntry[]>([]);
  const [loading, setLoading] = useState(false);
  const [settingKey, setSettingKey] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = async () => {
    setLoading(true);
    try {
      const r = await listAuditLog({ setting_key: settingKey || undefined, page });
      setItems(r.data.data); setLastPage(r.data.last_page);
    } catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page]);

  return (
    <main className="page-content">
      <h1>Audit log de configuraciones</h1>
      <form onSubmit={(e) => { e.preventDefault(); setPage(1); load(); }}
        style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem' }}>
        <input value={settingKey} onChange={(e) => setSettingKey(e.target.value)}
          placeholder="Filtrar por setting key" style={{ flex: 1 }} />
        <button type="submit">Filtrar</button>
      </form>

      {loading ? <p>Cargando…</p> : (
        <table className="data-table" style={{ width: '100%' }}>
          <thead><tr><th>Fecha</th><th>Setting</th><th>Antes</th><th>Después</th><th>Usuario</th><th>IP</th></tr></thead>
          <tbody>
            {items.map((a) => (
              <tr key={a.id}>
                <td style={{ fontSize: '0.85em' }}>{new Date(a.created_at).toLocaleString()}</td>
                <td><code>{a.setting_key}</code></td>
                <td style={{ fontSize: '0.85em', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.old_value || '—'}</td>
                <td style={{ fontSize: '0.85em', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.new_value || '—'}</td>
                <td style={{ fontSize: '0.85em' }}>{a.changed_by_email || `#${a.changed_by_user_id || '?'}`}</td>
                <td style={{ fontSize: '0.85em' }}>{a.ip_address || '—'}</td>
              </tr>
            ))}
            {!items.length && <tr><td colSpan={6} style={{ textAlign: 'center', padding: '2rem' }}>Sin entradas.</td></tr>}
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
