import { useEffect, useRef, useState } from 'react';
import {
  AUDIT_ACTIONS,
  AUDIT_TYPES,
  listUnifiedAuditLogs,
  type UnifiedAuditFilters,
  type UnifiedAuditLog,
} from '../../../lib/auditAdminApi';
import { toast } from '../../../stores/toastStore';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

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
  if (!changes || Object.keys(changes).length === 0) return <span style={{ color: 'var(--text-muted)' }}>—</span>;
  return (
    <div>
      <button
        onClick={() => setOpen((p) => !p)}
        style={{ fontSize: '0.75rem', color: 'var(--accent)', textDecoration: 'underline', background: 'none', border: 0, cursor: 'pointer' }}
      >
        {open ? 'Ocultar ▲' : 'Ver detalle ▼'}
      </button>
      {open && (
        <pre style={{ marginTop: '0.25rem', fontSize: '0.75rem', background: 'var(--bg-secondary)', padding: '0.5rem', borderRadius: '0.25rem', overflow: 'auto', maxHeight: '12rem', maxWidth: '20rem' }}>
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

  const columns: Column<UnifiedAuditLog>[] = [
    {
      key: 'created_at',
      label: 'Fecha',
      render: (a) => new Date(a.created_at).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' }),
    },
    {
      key: 'usuario',
      label: 'Usuario',
      render: (a) => (
        <div style={{ maxWidth: '18rem' }}>
          <div style={{ fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.user?.name ?? a.user_email ?? `#${a.user_id ?? '?'}`}</div>
          {a.user_email && a.user?.name && (
            <div style={{ color: 'var(--text-muted)', overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.user_email}</div>
          )}
        </div>
      ),
    },
    { key: 'user_role', label: 'Rol', render: (a) => a.user_role ?? '—' },
    { key: 'action', label: 'Acción', render: (a) => <ActionBadge action={a.action} /> },
    {
      key: 'recurso',
      label: 'Recurso',
      render: (a) => a.auditable_type ? (
        <span>
          <span style={{ fontWeight: 500 }}>{shortType(a.auditable_type)}</span>
          {a.auditable_id && <span style={{ color: 'var(--text-muted)' }}> #{a.auditable_id}</span>}
        </span>
      ) : (
        <span style={{ color: 'var(--text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: '18rem', display: 'block' }} title={a.url ?? ''}>
          {a.method && <span style={{ fontFamily: 'monospace', marginRight: '0.25rem' }}>{a.method}</span>}
          {a.route ?? a.url ?? '—'}
        </span>
      ),
    },
    { key: 'ip', label: 'IP', render: (a) => <span style={{ fontFamily: 'monospace', color: 'var(--text-muted)' }}>{a.ip ?? '—'}</span> },
    {
      key: 'status_code',
      label: 'Estado HTTP',
      render: (a) => a.status_code ? (
        <span style={{ color: a.status_code >= 400 ? '#dc2626' : '#16a34a', fontWeight: 600 }}>
          {a.status_code}
        </span>
      ) : '—',
    },
    { key: 'changes', label: 'Cambios', render: (a) => <ChangesDetail changes={a.changes} /> },
  ];

  return (
    <main className="page-content">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.5rem', fontWeight: 700, margin: 0 }}>Auditoría del sistema</h1>
          <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
            Registro de todas las acciones de usuarios — solo visible para super admins.
          </p>
        </div>
        {total > 0 && (
          <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>{total.toLocaleString()} registros</span>
        )}
      </div>

      {/* Filters */}
      <form onSubmit={handleSearch} className="card" style={{ padding: '1rem', marginBottom: '1rem' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '0.75rem', marginBottom: '0.75rem' }}>
          <input
            className="form-input"
            style={{ gridColumn: 'span 2' }}
            placeholder="Buscar email, URL, acción…"
            value={filters.q ?? ''}
            onChange={(e) => setF('q', e.target.value)}
          />
          <select className="form-input" value={filters.action ?? ''} onChange={(e) => setF('action', e.target.value)}>
            <option value="">Todas las acciones</option>
            {AUDIT_ACTIONS.map((a) => (
              <option key={a} value={a}>{a}</option>
            ))}
          </select>
          <select className="form-input" value={filters.auditable_type ?? ''} onChange={(e) => setF('auditable_type', e.target.value)}>
            <option value="">Todos los recursos</option>
            {Object.entries(AUDIT_TYPES).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
          </select>
          <input type="date" className="form-input" value={filters.desde ?? ''} onChange={(e) => setF('desde', e.target.value)} title="Desde" />
          <input type="date" className="form-input" value={filters.hasta ?? ''} onChange={(e) => setF('hasta', e.target.value)} title="Hasta" />
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button type="submit" className="btn-primary">Buscar</button>
          <button type="button" className="btn-secondary" onClick={handleReset}>Limpiar</button>
        </div>
      </form>

      <AdminTable
        columns={columns}
        rows={items}
        loading={loading}
        emptyLabel="Sin registros para los filtros seleccionados."
        rowKey={(a) => a.id}
        searchable={false}
        pageSizeOptions={[]}
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

