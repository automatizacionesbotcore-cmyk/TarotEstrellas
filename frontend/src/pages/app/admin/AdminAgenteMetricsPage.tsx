import { useQuery } from '@tanstack/react-query';
import { getAgenteMetrics } from '../../../lib/agenteMetricsApi';

export function AdminAgenteMetricsPage() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['admin-agente-metrics'],
    queryFn: () => getAgenteMetrics().then((r) => r.data),
  });

  if (isLoading) return <main className="page-content"><p>Cargando…</p></main>;
  if (isError) return (
    <main className="page-content">
      <p style={{ color: 'crimson' }}>Error: {(error as Error).message}</p>
    </main>
  );
  if (!data) return null;

  const fmtUsd = (v: number) => `$${v.toFixed(4)}`;
  const fmtN   = (v: number) => v.toLocaleString();

  return (
    <main className="page-content">
      <header style={{ marginBottom: '1.5rem' }}>
        <h1>Agente IA — Métricas y costos</h1>
        <p style={{ color: 'var(--text-muted)' }}>
          Periodo: {new Date(data.desde).toLocaleDateString()} → {new Date(data.hasta).toLocaleDateString()}
        </p>
      </header>

      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', marginBottom: '2rem' }}>
        <Card label="Conversaciones" value={fmtN(data.totales.conversaciones)} />
        <Card label="Tokens entrada" value={fmtN(data.totales.tokens_in)} />
        <Card label="Tokens salida"  value={fmtN(data.totales.tokens_out)} />
        <Card label="Costo total USD" value={fmtUsd(data.totales.costo_usd)} accent />
        <Card label="Errores" value={fmtN(data.totales.errores)} />
        <Card label="Latencia prom." value={`${data.totales.latencia_avg_ms} ms`} />
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>Costo por modelo</h2>
        <table className="table">
          <thead>
            <tr><th>Modelo</th><th>Conv.</th><th>Tokens in</th><th>Tokens out</th><th>$/1M in</th><th>$/1M out</th><th>Costo USD</th><th>Errores</th></tr>
          </thead>
          <tbody>
            {data.por_modelo.map((m, i) => (
              <tr key={i}>
                <td><code>{m.modelo ?? '—'}</code></td>
                <td>{fmtN(m.conv)}</td>
                <td>{fmtN(m.tokens_in)}</td>
                <td>{fmtN(m.tokens_out)}</td>
                <td>${m.precio.in.toFixed(2)}</td>
                <td>${m.precio.out.toFixed(2)}</td>
                <td><strong>{fmtUsd(m.costo_usd)}</strong></td>
                <td>{m.errores}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>Top 10 usuarios por costo</h2>
        <table className="table">
          <thead>
            <tr><th>Cliente ID</th><th>Conv.</th><th>Tokens in</th><th>Tokens out</th><th>Costo USD</th></tr>
          </thead>
          <tbody>
            {data.top_usuarios.map((u) => (
              <tr key={u.cliente_id}>
                <td>{u.cliente_id}</td>
                <td>{fmtN(u.conversaciones)}</td>
                <td>{fmtN(u.tokens_in)}</td>
                <td>{fmtN(u.tokens_out)}</td>
                <td><strong>{fmtUsd(u.costo_usd)}</strong></td>
              </tr>
            ))}
            {data.top_usuarios.length === 0 && <tr><td colSpan={5}>Sin datos en el periodo.</td></tr>}
          </tbody>
        </table>
      </section>

      <section>
        <h2>Serie diaria</h2>
        <table className="table">
          <thead><tr><th>Día</th><th>Conv.</th><th>Costo USD</th></tr></thead>
          <tbody>
            {data.serie_diaria.map((s) => (
              <tr key={s.dia}>
                <td>{s.dia}</td>
                <td>{fmtN(s.conversaciones)}</td>
                <td>{fmtUsd(s.costo_usd)}</td>
              </tr>
            ))}
            {data.serie_diaria.length === 0 && <tr><td colSpan={3}>Sin datos.</td></tr>}
          </tbody>
        </table>
      </section>
    </main>
  );
}

function Card({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  return (
    <div
      style={{
        padding: '1rem',
        borderRadius: 8,
        border: '1px solid var(--border)',
        background: accent ? 'var(--accent-bg)' : 'var(--surface)',
      }}
    >
      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>{label}</div>
      <div style={{ fontSize: '1.5rem', fontWeight: 600, marginTop: '0.25rem' }}>{value}</div>
    </div>
  );
}
