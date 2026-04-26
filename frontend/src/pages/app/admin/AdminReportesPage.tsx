import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../../lib/api';

type PagoRow = {
  uuid: string;
  tipo: string;
  canal: string;
  monto_centavos: number;
  moneda: string;
  pagado_en: string;
  cita_uuid: string | null;
  servicio: string | null;
};

type ResumenMoneda = {
  moneda: string;
  total_centavos: number;
  cantidad: number;
};

type ReportesData = {
  desde: string;
  hasta: string;
  resumen: ResumenMoneda[];
  pagos: PagoRow[];
};

function fmtMoney(cents: number, moneda: string) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: moneda,
    maximumFractionDigits: moneda === 'CLP' ? 0 : 2,
  }).format(cents / 100);
}

function fmtDate(iso: string) {
  return new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

const TIPO_LABELS: Record<string, string> = {
  abono_20: 'Abono 20%',
  saldo_80: 'Saldo 80%',
  completo: 'Pago completo',
};

export function AdminReportesPage() {
  const today = new Date().toISOString().slice(0, 10);
  const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);

  const [desde, setDesde] = useState(firstOfMonth);
  const [hasta, setHasta] = useState(today);

  const { data, isLoading, isError, refetch } = useQuery<{ data: ReportesData }>({
    queryKey: ['admin', 'reportes', 'ingresos', desde, hasta],
    queryFn: async () => (await api.get('/admin/reportes/ingresos', { params: { desde, hasta } })).data,
    staleTime: 60_000,
  });

  const report = data?.data;

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
        <p className="dash-eyebrow">✦ Reportes</p>
        <h1 className="dash-title">Ingresos</h1>
      </motion.div>

      {/* Filtros de fecha */}
      <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '1.5rem', alignItems: 'flex-end' }}>
        <div className="form-group" style={{ margin: 0 }}>
          <label className="form-label">Desde</label>
          <input
            type="date"
            className="form-input"
            value={desde}
            max={hasta}
            onChange={(e) => setDesde(e.target.value)}
          />
        </div>
        <div className="form-group" style={{ margin: 0 }}>
          <label className="form-label">Hasta</label>
          <input
            type="date"
            className="form-input"
            value={hasta}
            min={desde}
            max={today}
            onChange={(e) => setHasta(e.target.value)}
          />
        </div>
        <button type="button" className="btn-primary" onClick={() => refetch()}>
          Actualizar
        </button>
      </div>

      {isLoading && <p className="text-muted" style={{ marginTop: '1.5rem' }}>Cargando reportes…</p>}
      {isError && <p className="form-error" style={{ marginTop: '1rem' }}>No se pudieron cargar los reportes.</p>}

      {report && (
        <>
          {/* Tarjetas resumen por moneda */}
          {report.resumen.length > 0 && (
            <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '2rem' }}>
              {report.resumen.map((r) => (
                <div key={r.moneda} className="metric-card" style={{ minWidth: 180 }}>
                  <p className="metric-label">{r.moneda}</p>
                  <p className="metric-value">{fmtMoney(r.total_centavos, r.moneda)}</p>
                  <p className="metric-sub">{r.cantidad} pago{r.cantidad !== 1 ? 's' : ''}</p>
                </div>
              ))}
            </div>
          )}

          {/* Tabla de pagos */}
          <section style={{ marginTop: '2rem' }}>
            <h2 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>
              Detalle de pagos ({report.pagos.length})
            </h2>
            {report.pagos.length === 0 ? (
              <p className="text-muted">No hay pagos en este período.</p>
            ) : (
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Tipo</th>
                      <th>Canal</th>
                      <th>Servicio</th>
                      <th>Monto</th>
                    </tr>
                  </thead>
                  <tbody>
                    {report.pagos.map((p) => (
                      <tr key={p.uuid}>
                        <td>{fmtDate(p.pagado_en)}</td>
                        <td>{TIPO_LABELS[p.tipo] ?? p.tipo}</td>
                        <td style={{ textTransform: 'capitalize' }}>{p.canal}</td>
                        <td>{p.servicio ?? '—'}</td>
                        <td>
                          <strong style={{ color: 'var(--accent)' }}>
                            {fmtMoney(p.monto_centavos, p.moneda)}
                          </strong>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </main>
  );
}
