import { useQuery } from '@tanstack/react-query';
import {
  getAgenteMetrics,
  type AgenteMetricsPorModelo,
  type AgenteMetricsSerie,
  type AgenteMetricsTopUsuario,
} from '../../../lib/agenteMetricsApi';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

export function AdminAgenteMetricsPage() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['admin-agente-metrics'],
    queryFn: () => getAgenteMetrics().then((r) => r.data),
  });

  if (isLoading) return <main className="page-content"><p>Cargando…</p></main>;
  if (isError) return (
    <main className="page-content">
      <p className="form-error">Error: {(error as Error).message}</p>
    </main>
  );
  if (!data) return null;

  const fmtUsd = (v: number) => `$${v.toFixed(4)}`;
  const fmtN   = (v: number) => v.toLocaleString();

  const modelColumns: Column<AgenteMetricsPorModelo>[] = [
    { key: 'modelo', label: 'Modelo', render: (m) => <code>{m.modelo ?? '—'}</code> },
    { key: 'conv', label: 'Conv.', render: (m) => fmtN(m.conv) },
    { key: 'tokens_in', label: 'Tokens in', render: (m) => fmtN(m.tokens_in) },
    { key: 'tokens_out', label: 'Tokens out', render: (m) => fmtN(m.tokens_out) },
    { key: 'precio_in', label: '$/1M in', render: (m) => `$${m.precio.in.toFixed(2)}` },
    { key: 'precio_out', label: '$/1M out', render: (m) => `$${m.precio.out.toFixed(2)}` },
    { key: 'costo_usd', label: 'Costo USD', render: (m) => <strong>{fmtUsd(m.costo_usd)}</strong> },
    { key: 'errores', label: 'Errores' },
  ];

  const userColumns: Column<AgenteMetricsTopUsuario>[] = [
    { key: 'cliente_id', label: 'Cliente ID' },
    { key: 'conversaciones', label: 'Conv.', render: (u) => fmtN(u.conversaciones) },
    { key: 'tokens_in', label: 'Tokens in', render: (u) => fmtN(u.tokens_in) },
    { key: 'tokens_out', label: 'Tokens out', render: (u) => fmtN(u.tokens_out) },
    { key: 'costo_usd', label: 'Costo USD', render: (u) => <strong>{fmtUsd(u.costo_usd)}</strong> },
  ];

  const dailyColumns: Column<AgenteMetricsSerie>[] = [
    { key: 'dia', label: 'Día' },
    { key: 'conversaciones', label: 'Conv.', render: (s) => fmtN(s.conversaciones) },
    { key: 'costo_usd', label: 'Costo USD', render: (s) => fmtUsd(s.costo_usd) },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Astrea IA</p>
          <h1>Métricas y costos</h1>
          <p className="admin-page-subtitle">
            Periodo: {new Date(data.desde).toLocaleDateString()} - {new Date(data.hasta).toLocaleDateString()}
          </p>
        </div>
      </header>

      <section className="admin-metric-grid">
        <Card label="Conversaciones" value={fmtN(data.totales.conversaciones)} />
        <Card label="Tokens entrada" value={fmtN(data.totales.tokens_in)} />
        <Card label="Tokens salida"  value={fmtN(data.totales.tokens_out)} />
        <Card label="Costo total USD" value={fmtUsd(data.totales.costo_usd)} accent />
        <Card label="Errores" value={fmtN(data.totales.errores)} danger={data.totales.errores > 0} />
        <Card label="Latencia prom." value={`${data.totales.latencia_avg_ms} ms`} />
      </section>

      <section className="admin-section-block">
        <h2 className="admin-section-title">Costo por modelo</h2>
        <AdminTable
          columns={modelColumns}
          rows={data.por_modelo}
          rowKey={(m) => m.modelo ?? 'sin-modelo'}
          searchable
          searchPlaceholder="Buscar modelo"
          getSearchText={(m) => `${m.modelo ?? ''} ${m.errores}`}
          pageSize={5}
          pageSizeOptions={[5, 10, 25]}
        />
      </section>

      <section className="admin-section-block">
        <h2 className="admin-section-title">Top 10 usuarios por costo</h2>
        <AdminTable
          columns={userColumns}
          rows={data.top_usuarios}
          rowKey={(u) => u.cliente_id}
          searchable
          searchPlaceholder="Buscar cliente"
          getSearchText={(u) => `${u.cliente_id}`}
          emptyLabel="Sin datos en el periodo."
          pageSize={5}
          pageSizeOptions={[5, 10, 25]}
        />
      </section>

      <section className="admin-section-block">
        <h2 className="admin-section-title">Serie diaria</h2>
        <AdminTable
          columns={dailyColumns}
          rows={data.serie_diaria}
          rowKey={(s) => s.dia}
          searchable={false}
          emptyLabel="Sin datos."
          pageSize={10}
          pageSizeOptions={[10, 25, 50]}
        />
      </section>
    </main>
  );
}

function Card({ label, value, accent, danger }: { label: string; value: string; accent?: boolean; danger?: boolean }) {
  return (
    <div className="admin-metric-card">
      <span className="admin-metric-label">{label}</span>
      <strong className={`admin-metric-value${danger ? ' admin-metric-value--danger' : ''}${accent ? ' admin-metric-value--accent' : ''}`}>
        {value}
      </strong>
    </div>
  );
}
