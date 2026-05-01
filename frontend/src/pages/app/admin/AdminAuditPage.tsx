import { useEffect, useRef, useState } from 'react';
import {
  AUDIT_ACTIONS,
  AUDIT_TYPES,
  listUnifiedAuditLogs,
  type UnifiedAuditFilters,
  type UnifiedAuditLog,
} from '../../../lib/auditAdminApi';
import { toast } from '../../../stores/toastStore';

const ACTION_BADGE: Record<string, string> = {
  login: 'badge-green',
  logout: 'badge-gray',
  login_failed: 'badge-red',
  register: 'badge-blue',
  created: 'badge-blue',
  updated: 'badge-yellow',
  deleted: 'badge-red',
  password_reset: 'badge-orange',
};

function ActionBadge({ action }: { action: string }) {
  const cls = ACTION_BADGE[action] ?? 'badge-gray';
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${cls}`}>
      {action}
    </span>
  );
}

function ChangesDetail({ changes }: { changes: UnifiedAuditLog['changes'] }) {
  const [open, setOpen] = useState(false);
  if (!changes || Object.keys(changes).length === 0) return <span className="text-gray-400">—</span>;
  return (
    <div>
      <button
        onClick={() => setOpen((p) => !p)}
        className="text-xs text-indigo-600 hover:underline"
      >
        {open ? 'Ocultar ▲' : 'Ver detalle ▼'}
      </button>
      {open && (
        <pre className="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-auto max-h-48 max-w-xs">
          {JSON.stringify(changes, null, 2)}
        </pre>
      )}
    </div>
  );
}

const DEFAULT_FILTERS: UnifiedAuditFilters = {
  q: '',
  action: '',
  auditable_type: '',
  desde: '',
  hasta: '',
  per_page: 30,
};

export function AdminAuditPage() {
  const [items, setItems] = useState<UnifiedAuditLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [filters, setFilters] = useState<UnifiedAuditFilters>(DEFAULT_FILTERS);
  const [applied, setApplied] = useState<UnifiedAuditFilters>(DEFAULT_FILTERS);
  const abortRef = useRef<AbortController | null>(null);

  const load = async (f: UnifiedAuditFilters, p: number) => {
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    setLoading(true);
    try {
      const params: UnifiedAuditFilters = { ...f, page: p };
      // clean empty strings
      (Object.keys(params) as (keyof UnifiedAuditFilters)[]).forEach((k) => {
        if (params[k] === '') delete params[k];
      });
      const r = await listUnifiedAuditLogs(params);
      setItems(r.data.data);
      setLastPage(r.data.last_page);
      setTotal(r.data.total);
    } catch (e: any) {
      if (e?.code !== 'ERR_CANCELED') toast.error(e?.response?.data?.message || 'Error cargando audit log.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(applied, page); }, [applied, page]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    setApplied({ ...filters });
  };

  const handleReset = () => {
    setFilters(DEFAULT_FILTERS);
    setApplied(DEFAULT_FILTERS);
    setPage(1);
  };

  const setF = (key: keyof UnifiedAuditFilters, value: string | number) =>
    setFilters((p) => ({ ...p, [key]: value }));

  const shortType = (type: string | null) => {
    if (!type) return '—';
    return AUDIT_TYPES[type] ?? type.split('\\').pop() ?? type;
  };

  return (
    <main className="page-content">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h1 className="text-2xl font-bold">Auditoría del sistema</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Registro de todas las acciones de usuarios — solo visible para super admins.
          </p>
        </div>
        {total > 0 && (
          <span className="text-sm text-gray-500">{total.toLocaleString()} registros</span>
        )}
      </div>

      {/* Filters */}
      <form onSubmit={handleSearch} className="bg-white dark:bg-gray-900 border rounded-lg p-4 mb-4">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 mb-3">
          <input
            className="input col-span-2 sm:col-span-1 lg:col-span-2"
            placeholder="Buscar email, URL, acción…"
            value={filters.q ?? ''}
            onChange={(e) => setF('q', e.target.value)}
          />
          <select className="input" value={filters.action ?? ''} onChange={(e) => setF('action', e.target.value)}>
            <option value="">Todas las acciones</option>
            {AUDIT_ACTIONS.map((a) => (
              <option key={a} value={a}>{a}</option>
            ))}
          </select>
          <select className="input" value={filters.auditable_type ?? ''} onChange={(e) => setF('auditable_type', e.target.value)}>
            <option value="">Todos los recursos</option>
            {Object.entries(AUDIT_TYPES).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
          </select>
          <input type="date" className="input" value={filters.desde ?? ''} onChange={(e) => setF('desde', e.target.value)} title="Desde" />
          <input type="date" className="input" value={filters.hasta ?? ''} onChange={(e) => setF('hasta', e.target.value)} title="Hasta" />
        </div>
        <div className="flex gap-2">
          <button type="submit" className="btn btn-primary">Buscar</button>
          <button type="button" className="btn btn-secondary" onClick={handleReset}>Limpiar</button>
        </div>
      </form>

      {/* Table */}
      <div className="overflow-x-auto rounded-lg border bg-white dark:bg-gray-900">
        <table className="data-table w-full text-sm">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Usuario</th>
              <th>Rol</th>
              <th>Acción</th>
              <th>Recurso</th>
              <th>IP</th>
              <th>Estado HTTP</th>
              <th>Cambios</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={8} className="text-center py-8 text-gray-400">Cargando…</td></tr>
            ) : items.length === 0 ? (
              <tr><td colSpan={8} className="text-center py-8 text-gray-400">Sin registros para los filtros seleccionados.</td></tr>
            ) : items.map((a) => (
              <tr key={a.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td className="whitespace-nowrap text-xs text-gray-500">
                  {new Date(a.created_at).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })}
                </td>
                <td className="max-w-[160px] truncate text-xs">
                  <div className="font-medium truncate">{a.user?.name ?? a.user_email ?? `#${a.user_id ?? '?'}`}</div>
                  {a.user_email && a.user?.name && (
                    <div className="text-gray-400 truncate">{a.user_email}</div>
                  )}
                </td>
                <td className="text-xs text-gray-500">{a.user_role ?? '—'}</td>
                <td><ActionBadge action={a.action} /></td>
                <td className="text-xs">
                  {a.auditable_type ? (
                    <span>
                      <span className="font-medium">{shortType(a.auditable_type)}</span>
                      {a.auditable_id && <span className="text-gray-400"> #{a.auditable_id}</span>}
                    </span>
                  ) : (
                    <span className="text-gray-400 truncate max-w-[160px] block" title={a.url ?? ''}>
                      {a.method && <span className="font-mono mr-1">{a.method}</span>}
                      {a.route ?? a.url ?? '—'}
                    </span>
                  )}
                </td>
                <td className="text-xs font-mono text-gray-500">{a.ip ?? '—'}</td>
                <td className="text-xs">
                  {a.status_code ? (
                    <span className={a.status_code >= 400 ? 'text-red-600 font-semibold' : 'text-green-600'}>
                      {a.status_code}
                    </span>
                  ) : '—'}
                </td>
                <td><ChangesDetail changes={a.changes} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      <nav className="flex items-center justify-center gap-2 mt-4">
        <button className="btn btn-secondary btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>‹ Anterior</button>
        <span className="text-sm text-gray-600">Página {page} de {lastPage}</span>
        <button className="btn btn-secondary btn-sm" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Siguiente ›</button>
      </nav>
    </main>
  );
}

