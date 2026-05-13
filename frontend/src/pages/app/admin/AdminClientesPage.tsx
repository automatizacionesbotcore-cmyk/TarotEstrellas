import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { listClientes, type ClienteListaItem, type ClienteListaParams } from '../../../lib/clientesAdminApi';

const FRECUENCIAS: Array<{ value: ClienteListaParams['frecuencia']; label: string }> = [
  { value: undefined, label: 'Todas las frecuencias' },
  { value: 'sin_consultas', label: 'Sin consultas' },
  { value: 'baja', label: 'Baja (1-2)' },
  { value: 'media', label: 'Media (3-9)' },
  { value: 'alta', label: 'Alta (10+)' },
];

export function AdminClientesPage() {
  const [filters, setFilters] = useState<ClienteListaParams>({ per_page: 25 });
  const [items, setItems] = useState<ClienteListaItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null);

  useEffect(() => {
    let canceled = false;
    setLoading(true);
    setError(null);
    listClientes(filters)
      .then((res) => {
        if (canceled) return;
        setItems(res.data);
        setMeta(res.meta);
      })
      .catch((e) => !canceled && setError(e?.response?.data?.message || 'Error cargando clientes.'))
      .finally(() => !canceled && setLoading(false));
    return () => {
      canceled = true;
    };
  }, [filters]);

  const totalIngresos = useMemo(() => {
    const acc: Record<string, number> = {};
    items.forEach((i) => {
      Object.entries(i.stats.ingresos_centavos || {}).forEach(([m, c]) => {
        acc[m] = (acc[m] || 0) + c;
      });
    });
    return acc;
  }, [items]);

  return (
    <main className="page-content">
      <header style={{ marginBottom: '1.5rem' }}>
        <h1>Clientes</h1>
        <p style={{ color: 'var(--text-muted)' }}>
          Búsqueda y gestión de la base de clientes. Click en un cliente para abrir su ficha 360°.
        </p>
      </header>

      <section className="card" style={{ padding: '1rem', marginBottom: '1rem', display: 'grid', gap: '0.75rem', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' }}>
        <input
          type="search"
          className="input-field"
          placeholder="Buscar por nombre, email o teléfono"
          value={filters.q || ''}
          onChange={(e) => setFilters((f) => ({ ...f, q: e.target.value, page: 1 }))}
        />
        <input
          type="text"
          className="input-field"
          placeholder="País (CL, AR…)"
          maxLength={2}
          value={filters.pais || ''}
          onChange={(e) => setFilters((f) => ({ ...f, pais: e.target.value.toUpperCase() || undefined, page: 1 }))}
        />
        <select
          className="input-field"
          value={filters.frecuencia ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, frecuencia: (e.target.value || undefined) as ClienteListaParams['frecuencia'], page: 1 }))}
        >
          {FRECUENCIAS.map((f) => (
            <option key={f.label} value={f.value ?? ''}>{f.label}</option>
          ))}
        </select>
        <select
          className="input-field"
          value={filters.activos === undefined ? '' : String(filters.activos)}
          onChange={(e) => setFilters((f) => ({ ...f, activos: e.target.value === '' ? undefined : (Number(e.target.value) as 0 | 1), page: 1 }))}
        >
          <option value="">Activos: todos</option>
          <option value="1">Activos (login &lt; 60d)</option>
          <option value="0">Inactivos</option>
        </select>
      </section>

      {error && <p style={{ color: '#c0392b' }}>{error}</p>}

      <section style={{ overflowX: 'auto' }}>
        <table className="admin-table" style={{ width: '100%' }}>
          <thead>
            <tr>
              <th>Cliente</th>
              <th>País</th>
              <th>Consultas</th>
              <th>Ingresos</th>
              <th>Última consulta</th>
              <th>Tipo favorito</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr><td colSpan={7} style={{ textAlign: 'center', padding: '2rem' }}>Cargando…</td></tr>
            )}
            {!loading && items.length === 0 && (
              <tr><td colSpan={7} style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-muted)' }}>No hay clientes con esos filtros.</td></tr>
            )}
            {items.map((c) => {
              const ingresos = Object.entries(c.stats.ingresos_centavos || {})
                .map(([m, ctvs]) => `${m === 'CLP' ? '$' : ''}${formatMoney(ctvs, m)} ${m}`)
                .join(' • ') || '—';
              return (
                <tr key={c.uuid}>
                  <td>
                    <strong>{c.profile?.nombre || c.name}{c.profile?.apellido ? ` ${c.profile.apellido}` : ''}</strong>
                    <div style={{ fontSize: '0.85em', color: 'var(--text-muted)' }}>{c.email}</div>
                  </td>
                  <td>{c.profile?.pais_residencia || '—'}</td>
                  <td>
                    <strong>{c.stats.total_completadas}</strong>
                    {c.stats.total_no_show > 0 && <span style={{ color: '#d68910' }}> · {c.stats.total_no_show} no-show</span>}
                  </td>
                  <td>{ingresos}</td>
                  <td>{c.stats.ultima_consulta ? new Date(c.stats.ultima_consulta).toLocaleDateString() : '—'}</td>
                  <td>{c.stats.tipo_favorito || '—'}</td>
                  <td><Link className="btn-secondary" to={`/app/admin/clientes/${c.uuid}`}>Ver ficha</Link></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </section>

      {meta && (
        <footer style={{ marginTop: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span style={{ color: 'var(--text-muted)' }}>
            {meta.total} clientes · ingresos visibles: {Object.entries(totalIngresos).map(([m, c]) => `${formatMoney(c, m)} ${m}`).join(' · ') || '—'}
          </span>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              className="btn-secondary"
              disabled={meta.current_page <= 1}
              onClick={() => setFilters((f) => ({ ...f, page: Math.max(1, (f.page || 1) - 1) }))}
            >Anterior</button>
            <span>Página {meta.current_page} / {meta.last_page}</span>
            <button
              type="button"
              className="btn-secondary"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setFilters((f) => ({ ...f, page: (f.page || 1) + 1 }))}
            >Siguiente</button>
          </div>
        </footer>
      )}
    </main>
  );
}

function formatMoney(centavos: number, moneda: string): string {
  if (moneda === 'CLP') return Math.round(centavos / 100).toLocaleString('es-CL');
  return (centavos / 100).toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
