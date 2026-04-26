import { useQuery } from '@tanstack/react-query';
import { api } from '../../../lib/api';

type StatusDetail = { total: number; monto_centavos: number };

type ReembolsosMetricas = {
  total: number;
  monto_total_centavos: number;
  by_status: Record<string, StatusDetail>;
  by_method: Record<string, StatusDetail>;
};

type ComprobantesMetricas = {
  total: number;
  by_status?: Record<string, number>;
  last_24h?: number;
  manual_queue?: number;
};

type GeneralMetricas = {
  citas: {
    total: number;
    completadas: number;
    canceladas: number;
    tasa_cancelacion: number;
    ingreso_total_centavos: number;
    by_estado: Record<string, StatusDetail>;
  };
  pagos: {
    total: number;
    monto_total_centavos: number;
    pendientes: number;
    by_canal: Record<string, StatusDetail>;
    by_tipo: Record<string, StatusDetail>;
  };
  servicios: Array<{
    nombre: string;
    total_citas: number;
    completadas: number;
    monto_centavos: number;
  }>;
  clientes: {
    total_clientes: number;
    top_clientes: Array<{
      nombre: string;
      email: string;
      total_citas: number;
      monto_centavos: number;
    }>;
  };
};

function formatMoney(cents: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(cents / 100);
}

const ESTADO_COLORS: Record<string, string> = {
  pendiente_abono: '#e09944',
  reservada: '#4a9eff',
  confirmada: '#4caf6e',
  en_curso: '#9b59b6',
  completada: '#6C3FA0',
  cancelada: '#e05555',
};

const CANAL_LABELS: Record<string, string> = {
  stripe: 'Stripe',
  transferencia: 'Transferencia',
  credito_cliente: 'Crédito cliente',
  membresia: 'Membresía',
};

export function AdminDashboardPage() {
  const reembolsos = useQuery({
    queryKey: ['admin', 'reembolsos', 'metricas'],
    queryFn:  async () => (await api.get<{ data: ReembolsosMetricas }>('/admin/reembolsos/metricas')).data.data,
  });

  const comprobantes = useQuery({
    queryKey: ['admin', 'comprobantes', 'metricas'],
    queryFn:  async () => (await api.get<{ data: ComprobantesMetricas }>('/admin/comprobantes/metricas')).data.data,
  });

  const metricas = useQuery({
    queryKey: ['admin', 'metricas'],
    queryFn:  async () => (await api.get<{ data: GeneralMetricas }>('/admin/metricas')).data.data,
  });

  const m = metricas.data;

  return (
    <main className="page-content">
      <h1>Resumen administrativo</h1>

      {/* KPIs principales */}
      <section className="dash-stats">
        <div className="stat-card">
          <span className="stat-value">{m?.citas.total ?? '—'}</span>
          <span className="stat-label">Citas totales</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{m?.citas.completadas ?? '—'}</span>
          <span className="stat-label">Completadas</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">
            {m ? formatMoney(m.citas.ingreso_total_centavos) : '—'}
          </span>
          <span className="stat-label">Ingresos totales</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">
            {m ? formatMoney(m.pagos.monto_total_centavos) : '—'}
          </span>
          <span className="stat-label">Pagos recibidos</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{m?.clientes.total_clientes ?? '—'}</span>
          <span className="stat-label">Clientes únicos</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{m ? `${m.citas.tasa_cancelacion}%` : '—'}</span>
          <span className="stat-label">Tasa cancelación</span>
        </div>
      </section>

      {/* Citas por estado */}
      {m && Object.keys(m.citas.by_estado).length > 0 && (
        <section className="admin-breakdown-section">
          <h2 className="admin-breakdown-title">Citas por estado</h2>
          <div className="admin-breakdown-grid">
            {Object.entries(m.citas.by_estado).map(([k, v]) => (
              <div key={k} className="admin-breakdown-card" style={{ borderLeftColor: ESTADO_COLORS[k] ?? 'var(--accent)', borderLeftWidth: 3, borderLeftStyle: 'solid' }}>
                <span className="admin-breakdown-count">{v.total}</span>
                <span className="admin-breakdown-label">{k.replace(/_/g, ' ')}</span>
                <span className="admin-breakdown-amount">{formatMoney(v.monto_centavos)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Pagos por canal */}
      {m && Object.keys(m.pagos.by_canal).length > 0 && (
        <section className="admin-breakdown-section">
          <h2 className="admin-breakdown-title">Pagos por canal</h2>
          <div className="admin-breakdown-grid">
            {Object.entries(m.pagos.by_canal).map(([k, v]) => (
              <div key={k} className="admin-breakdown-card">
                <span className="admin-breakdown-count">{v.total}</span>
                <span className="admin-breakdown-label">{CANAL_LABELS[k] ?? k.replace(/_/g, ' ')}</span>
                <span className="admin-breakdown-amount">{formatMoney(v.monto_centavos)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Pagos por tipo (abono vs saldo) */}
      {m && Object.keys(m.pagos.by_tipo).length > 0 && (
        <section className="admin-breakdown-section">
          <h2 className="admin-breakdown-title">Pagos por tipo</h2>
          <div className="admin-breakdown-grid">
            {Object.entries(m.pagos.by_tipo).map(([k, v]) => (
              <div key={k} className="admin-breakdown-card">
                <span className="admin-breakdown-count">{v.total}</span>
                <span className="admin-breakdown-label">{k.replace(/_/g, ' ')}</span>
                <span className="admin-breakdown-amount">{formatMoney(v.monto_centavos)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Montos por servicio */}
      {m && m.servicios.length > 0 && (
        <section className="admin-breakdown-section">
          <h2 className="admin-breakdown-title">Por servicio</h2>
          <div className="admin-breakdown-grid">
            {m.servicios.map((s) => (
              <div key={s.nombre} className="admin-breakdown-card">
                <span className="admin-breakdown-count">{s.total_citas}</span>
                <span className="admin-breakdown-label">{s.nombre}</span>
                <span className="admin-breakdown-amount">{s.completadas} completadas · {formatMoney(s.monto_centavos)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Top clientes */}
      {m && m.clientes.top_clientes.length > 0 && (
        <section className="admin-breakdown-section">
          <h2 className="admin-breakdown-title">Top clientes</h2>
          <div className="admin-breakdown-grid">
            {m.clientes.top_clientes.map((c) => (
              <div key={c.email} className="admin-breakdown-card">
                <span className="admin-breakdown-count">{c.total_citas}</span>
                <span className="admin-breakdown-label">{c.nombre || c.email}</span>
                <span className="admin-breakdown-amount">{formatMoney(c.monto_centavos)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Comprobantes + Reembolsos */}
      <section className="admin-dashboard-row">
        <div>
          <section className="dash-stats" style={{ marginTop: '0.5rem' }}>
            <div className="stat-card">
              <span className="stat-value">{comprobantes.data?.total ?? '—'}</span>
              <span className="stat-label">Comprobantes</span>
            </div>
            <div className="stat-card">
              <span className="stat-value">{comprobantes.data?.manual_queue ?? 0}</span>
              <span className="stat-label">Cola manual</span>
            </div>
            <div className="stat-card">
              <span className="stat-value">{comprobantes.data?.last_24h ?? 0}</span>
              <span className="stat-label">Últimas 24h</span>
            </div>
          </section>

          <section className="dash-stats">
            <div className="stat-card">
              <span className="stat-value">{reembolsos.data?.total ?? '—'}</span>
              <span className="stat-label">Reembolsos</span>
            </div>
            <div className="stat-card">
              <span className="stat-value">
                {reembolsos.data ? formatMoney(reembolsos.data.monto_total_centavos) : '—'}
              </span>
              <span className="stat-label">Monto reembolsado</span>
            </div>
            <div className="stat-card">
              <span className="stat-value">{reembolsos.data?.by_status?.pendiente?.total ?? 0}</span>
              <span className="stat-label">Pendientes</span>
            </div>
          </section>
        </div>
      </section>

      {reembolsos.data && (
        <>
          <section className="admin-breakdown-section">
            <h2 className="admin-breakdown-title">Reembolsos por estado</h2>
            <div className="admin-breakdown-grid">
              {Object.entries(reembolsos.data.by_status).map(([k, v]) => (
                <div key={k} className={`admin-breakdown-card admin-breakdown-${k}`}>
                  <span className="admin-breakdown-count">{v.total}</span>
                  <span className="admin-breakdown-label">{k.replace(/_/g, ' ')}</span>
                  <span className="admin-breakdown-amount">{formatMoney(v.monto_centavos)}</span>
                </div>
              ))}
            </div>
          </section>

          <section className="admin-breakdown-section">
            <h2 className="admin-breakdown-title">Reembolsos por método</h2>
            <div className="admin-breakdown-grid">
              {Object.entries(reembolsos.data.by_method).map(([k, v]) => (
                <div key={k} className="admin-breakdown-card">
                  <span className="admin-breakdown-count">{v.total}</span>
                  <span className="admin-breakdown-label">{k.replace(/_/g, ' ')}</span>
                  <span className="admin-breakdown-amount">{formatMoney(v.monto_centavos)}</span>
                </div>
              ))}
            </div>
          </section>
        </>
      )}
    </main>
  );
}
