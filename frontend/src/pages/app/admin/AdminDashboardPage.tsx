import { useQuery } from '@tanstack/react-query';
import { api } from '../../../lib/api';

type ReembolsosMetricas = {
  total: number;
  monto_total_centavos: number;
  by_status: Record<string, number>;
  by_method: Record<string, number>;
};

type ComprobantesMetricas = {
  total: number;
  by_status?: Record<string, number>;
};

function formatMoney(cents: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(cents / 100);
}

export function AdminDashboardPage() {
  const reembolsos = useQuery({
    queryKey: ['admin', 'reembolsos', 'metricas'],
    queryFn:  async () => (await api.get<{ data: ReembolsosMetricas }>('/admin/reembolsos/metricas')).data.data,
  });

  const comprobantes = useQuery({
    queryKey: ['admin', 'comprobantes', 'metricas'],
    queryFn:  async () => (await api.get<{ data: ComprobantesMetricas }>('/admin/comprobantes/metricas')).data.data,
  });

  return (
    <main className="page-content">
      <h1>Resumen administrativo</h1>
      <section className="dash-stats">
        <div className="stat-card">
          <span className="stat-value">{reembolsos.data?.total ?? '—'}</span>
          <span className="stat-label">Reembolsos totales</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">
            {reembolsos.data ? formatMoney(reembolsos.data.monto_total_centavos) : '—'}
          </span>
          <span className="stat-label">Monto reembolsado</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{reembolsos.data?.by_status?.pendiente ?? 0}</span>
          <span className="stat-label">Reembolsos pendientes</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{comprobantes.data?.total ?? '—'}</span>
          <span className="stat-label">Comprobantes totales</span>
        </div>
      </section>

      {reembolsos.data && (
        <section>
          <h2>Por estado</h2>
          <ul>
            {Object.entries(reembolsos.data.by_status).map(([k, v]) => (
              <li key={k}><strong>{k}:</strong> {v}</li>
            ))}
          </ul>
          <h2>Por método</h2>
          <ul>
            {Object.entries(reembolsos.data.by_method).map(([k, v]) => (
              <li key={k}><strong>{k}:</strong> {v}</li>
            ))}
          </ul>
        </section>
      )}
    </main>
  );
}
