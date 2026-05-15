import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

// ── Types ─────────────────────────────────────────────────────────────────────
type PagoRow = {
  uuid: string; tipo: string; canal: string;
  monto_centavos: number; moneda: string;
  pagado_en: string; cita_uuid: string | null; servicio: string | null;
};
type ResumenMoneda = { moneda: string; total_centavos: number; cantidad: number };
type IngresosData = { desde: string; hasta: string; resumen: ResumenMoneda[]; pagos: PagoRow[] };

type ConsultasData = {
  desde: string; hasta: string; total: number; finalizadas: number;
  canceladas: number; no_show: number; tasa_cancelacion: number;
  tasa_no_show: number; duracion_promedio: number;
  por_estado: Record<string, number>; por_tipo: Record<string, number>;
};

type TopCliente = { id: number; name: string; email: string; total_centavos?: number; moneda?: string; total_consultas?: number };
type ClientesData = { desde: string; hasta: string; nuevos_clientes: number; top_ingresos: TopCliente[]; top_consultas: TopCliente[] };

type FiscalData = {
  desde: string; hasta: string;
  ingresos_brutos_centavos: number; comisiones_stripe_centavos: number; ingresos_netos_centavos: number;
  desglose_por_tipo: { tipo: string; total_centavos: number; cantidad_pagos: number; moneda: string }[];
  desglose_por_mes: { mes: string; total_centavos: number; cantidad_pagos: number }[];
};

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtMoney(cents: number, moneda: string) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency', currency: moneda,
    maximumFractionDigits: moneda === 'CLP' ? 0 : 2,
  }).format(cents / 100);
}

function fmtDate(iso: string) {
  return new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

const TIPO_LABELS: Record<string, string> = {
  abono_20: 'Abono 20%', saldo_80: 'Saldo 80%', completo: 'Pago completo',
};

// ── Date range shared component ───────────────────────────────────────────────
function DateFilters({
  desde, hasta, today, onChange, onRefetch,
}: { desde: string; hasta: string; today: string; onChange: (d: string, h: string) => void; onRefetch: () => void }) {
  return (
    <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '1.5rem', alignItems: 'flex-end' }}>
      <div className="form-group" style={{ margin: 0 }}>
        <label className="form-label">Desde</label>
        <input type="date" className="form-input" value={desde} max={hasta}
          onChange={(e) => onChange(e.target.value, hasta)} />
      </div>
      <div className="form-group" style={{ margin: 0 }}>
        <label className="form-label">Hasta</label>
        <input type="date" className="form-input" value={hasta} min={desde} max={today}
          onChange={(e) => onChange(desde, e.target.value)} />
      </div>
      <button type="button" className="btn-primary" onClick={onRefetch}>Actualizar</button>
    </div>
  );
}

// ── Tab: Ingresos ─────────────────────────────────────────────────────────────
function TabIngresos() {
  const today = new Date().toISOString().slice(0, 10);
  const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
  const [desde, setDesde] = useState(firstOfMonth);
  const [hasta, setHasta] = useState(today);

  const { data, isLoading, isError, refetch } = useQuery<{ data: IngresosData }>({
    queryKey: ['admin', 'reportes', 'ingresos', desde, hasta],
    queryFn: async () => (await api.get('/admin/reportes/ingresos', { params: { desde, hasta } })).data,
    staleTime: 60_000,
  });
  const report = data?.data;
  const pagosColumns: Column<PagoRow>[] = [
    { key: 'pagado_en', label: 'Fecha', render: (p) => fmtDate(p.pagado_en) },
    { key: 'tipo', label: 'Tipo', render: (p) => TIPO_LABELS[p.tipo] ?? p.tipo },
    { key: 'canal', label: 'Canal', render: (p) => <span style={{ textTransform: 'capitalize' }}>{p.canal}</span> },
    { key: 'servicio', label: 'Servicio', render: (p) => p.servicio ?? '—' },
    { key: 'monto_centavos', label: 'Monto', render: (p) => <strong style={{ color: 'var(--accent)' }}>{fmtMoney(p.monto_centavos, p.moneda)}</strong> },
  ];

  return (
    <>
      <DateFilters desde={desde} hasta={hasta} today={today}
        onChange={(d, h) => { setDesde(d); setHasta(h); }}
        onRefetch={() => refetch()} />
      {isLoading && <p className="text-muted" style={{ marginTop: '1.5rem' }}>Cargando…</p>}
      {isError && <p className="form-error" style={{ marginTop: '1rem' }}>Error al cargar.</p>}
      {report && (
        <>
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
          <section style={{ marginTop: '2rem' }}>
            <h2 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Detalle de pagos ({report.pagos.length})</h2>
            {report.pagos.length === 0 ? (
              <p className="text-muted">No hay pagos en este período.</p>
            ) : (
              <AdminTable
                columns={pagosColumns}
                rows={report.pagos}
                rowKey={(p) => p.uuid}
                searchable
                searchPlaceholder="Buscar pago"
                getSearchText={(p) => `${p.tipo} ${p.canal} ${p.servicio ?? ''} ${p.moneda}`}
                pageSize={10}
                pageSizeOptions={[10, 25, 50]}
              />
            )}
          </section>
        </>
      )}
    </>
  );
}

// ── Tab: Consultas ────────────────────────────────────────────────────────────
function TabConsultas() {
  const today = new Date().toISOString().slice(0, 10);
  const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
  const [desde, setDesde] = useState(firstOfMonth);
  const [hasta, setHasta] = useState(today);

  const { data, isLoading, isError, refetch } = useQuery<{ data: ConsultasData }>({
    queryKey: ['admin', 'reportes', 'consultas', desde, hasta],
    queryFn: async () => (await api.get('/admin/reportes/consultas', { params: { desde, hasta } })).data,
    staleTime: 60_000,
  });
  const r = data?.data;
  const breakdownColumns: Column<[string, number]>[] = [
    { key: 'label', label: 'Categoría', render: ([label]) => label },
    { key: 'cantidad', label: 'Cantidad', render: ([, count]) => count },
  ];

  return (
    <>
      <DateFilters desde={desde} hasta={hasta} today={today}
        onChange={(d, h) => { setDesde(d); setHasta(h); }}
        onRefetch={() => refetch()} />
      {isLoading && <p className="text-muted" style={{ marginTop: '1.5rem' }}>Cargando…</p>}
      {isError && <p className="form-error" style={{ marginTop: '1rem' }}>Error al cargar.</p>}
      {r && (
        <>
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '2rem' }}>
            {[
              { label: 'Total citas',       value: r.total },
              { label: 'Finalizadas',        value: r.finalizadas },
              { label: 'Canceladas',         value: `${r.canceladas} (${r.tasa_cancelacion}%)` },
              { label: 'No-show',            value: `${r.no_show} (${r.tasa_no_show}%)` },
              { label: 'Duración promedio',  value: `${r.duracion_promedio} min` },
            ].map(({ label, value }) => (
              <div key={label} className="metric-card" style={{ minWidth: 160 }}>
                <p className="metric-label">{label}</p>
                <p className="metric-value" style={{ fontSize: '1.4rem' }}>{value}</p>
              </div>
            ))}
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '1.5rem', marginTop: '2rem' }}>
            <section>
              <h3 style={{ marginBottom: '0.75rem', fontSize: '1rem' }}>Por estado</h3>
              <AdminTable
                columns={breakdownColumns}
                rows={Object.entries(r.por_estado)}
                rowKey={([label]) => label}
                searchable={false}
                pageSizeOptions={[]}
                showFooter={false}
              />
            </section>
            <section>
              <h3 style={{ marginBottom: '0.75rem', fontSize: '1rem' }}>Por tipo de consulta</h3>
              <AdminTable
                columns={breakdownColumns}
                rows={Object.entries(r.por_tipo)}
                rowKey={([label]) => label}
                searchable={false}
                pageSizeOptions={[]}
                showFooter={false}
              />
            </section>
          </div>
        </>
      )}
    </>
  );
}

// ── Tab: Clientes ─────────────────────────────────────────────────────────────
function TabClientes() {
  const today = new Date().toISOString().slice(0, 10);
  const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
  const [desde, setDesde] = useState(firstOfMonth);
  const [hasta, setHasta] = useState(today);

  const { data, isLoading, isError, refetch } = useQuery<{ data: ClientesData }>({
    queryKey: ['admin', 'reportes', 'clientes', desde, hasta],
    queryFn: async () => (await api.get('/admin/reportes/clientes', { params: { desde, hasta } })).data,
    staleTime: 60_000,
  });
  const r = data?.data;
  const topIngresosColumns: Column<TopCliente>[] = [
    {
      key: 'cliente',
      label: 'Cliente',
      render: (c) => (
        <span>
          {c.name}<br /><small style={{ opacity: 0.6 }}>{c.email}</small>
        </span>
      ),
    },
    { key: 'total', label: 'Total', render: (c) => c.total_centavos != null ? fmtMoney(c.total_centavos, c.moneda ?? 'CLP') : '—' },
  ];
  const topConsultasColumns: Column<TopCliente>[] = [
    {
      key: 'cliente',
      label: 'Cliente',
      render: (c) => (
        <span>
          {c.name}<br /><small style={{ opacity: 0.6 }}>{c.email}</small>
        </span>
      ),
    },
    { key: 'total_consultas', label: 'Consultas' },
  ];

  return (
    <>
      <DateFilters desde={desde} hasta={hasta} today={today}
        onChange={(d, h) => { setDesde(d); setHasta(h); }}
        onRefetch={() => refetch()} />
      {isLoading && <p className="text-muted" style={{ marginTop: '1.5rem' }}>Cargando…</p>}
      {isError && <p className="form-error" style={{ marginTop: '1rem' }}>Error al cargar.</p>}
      {r && (
        <>
          <div className="metric-card" style={{ marginTop: '2rem', minWidth: 180, display: 'inline-block' }}>
            <p className="metric-label">Clientes nuevos</p>
            <p className="metric-value">{r.nuevos_clientes}</p>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.5rem', marginTop: '2rem' }}>
            <section>
              <h3 style={{ marginBottom: '0.75rem', fontSize: '1rem' }}>Top 10 por ingresos</h3>
              <AdminTable
                columns={topIngresosColumns}
                rows={r.top_ingresos}
                rowKey={(c) => c.id}
                searchable={false}
                pageSizeOptions={[]}
                showFooter={false}
              />
            </section>
            <section>
              <h3 style={{ marginBottom: '0.75rem', fontSize: '1rem' }}>Top 10 por consultas</h3>
              <AdminTable
                columns={topConsultasColumns}
                rows={r.top_consultas}
                rowKey={(c) => c.id}
                searchable={false}
                pageSizeOptions={[]}
                showFooter={false}
              />
            </section>
          </div>
        </>
      )}
    </>
  );
}

// ── Tab: Fiscal ───────────────────────────────────────────────────────────────
function TabFiscal() {
  const today = new Date().toISOString().slice(0, 10);
  const firstOfYear = `${new Date().getFullYear()}-01-01`;
  const [desde, setDesde] = useState(firstOfYear);
  const [hasta, setHasta] = useState(today);

  const { data, isLoading, isError, refetch } = useQuery<{ data: FiscalData }>({
    queryKey: ['admin', 'reportes', 'fiscal', desde, hasta],
    queryFn: async () => (await api.get('/admin/reportes/fiscal', { params: { desde, hasta } })).data,
    staleTime: 60_000,
  });
  const r = data?.data;
  const tipoColumns: Column<FiscalData['desglose_por_tipo'][number]>[] = [
    { key: 'tipo', label: 'Servicio' },
    { key: 'cantidad_pagos', label: 'Pagos' },
    { key: 'total_centavos', label: 'Total', render: (d) => fmtMoney(d.total_centavos, d.moneda) },
  ];
  const mesColumns: Column<FiscalData['desglose_por_mes'][number]>[] = [
    { key: 'mes', label: 'Mes' },
    { key: 'cantidad_pagos', label: 'Pagos' },
    { key: 'total_centavos', label: 'Total', render: (d) => fmtMoney(d.total_centavos, 'CLP') },
  ];

  return (
    <>
      <DateFilters desde={desde} hasta={hasta} today={today}
        onChange={(d, h) => { setDesde(d); setHasta(h); }}
        onRefetch={() => refetch()} />
      {isLoading && <p className="text-muted" style={{ marginTop: '1.5rem' }}>Cargando…</p>}
      {isError && <p className="form-error" style={{ marginTop: '1rem' }}>Error al cargar.</p>}
      {r && (
        <>
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '2rem' }}>
            {[
              { label: 'Ingresos brutos', cents: r.ingresos_brutos_centavos },
              { label: 'Comisiones Stripe (est.)', cents: r.comisiones_stripe_centavos },
              { label: 'Ingresos netos', cents: r.ingresos_netos_centavos },
            ].map(({ label, cents }) => (
              <div key={label} className="metric-card" style={{ minWidth: 200 }}>
                <p className="metric-label">{label}</p>
                <p className="metric-value" style={{ fontSize: '1.3rem' }}>{fmtMoney(cents, 'CLP')}</p>
              </div>
            ))}
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1.5rem', marginTop: '2rem' }}>
            <section>
              <h3 style={{ marginBottom: '0.75rem', fontSize: '1rem' }}>Por tipo de servicio</h3>
              <AdminTable
                columns={tipoColumns}
                rows={r.desglose_por_tipo}
                rowKey={(d) => d.tipo}
                searchable={false}
                pageSizeOptions={[]}
                showFooter={false}
              />
            </section>
            <section>
              <h3 style={{ marginBottom: '0.75rem', fontSize: '1rem' }}>Por mes</h3>
              <AdminTable
                columns={mesColumns}
                rows={r.desglose_por_mes}
                rowKey={(d) => d.mes}
                searchable={false}
                pageSizeOptions={[]}
                showFooter={false}
              />
            </section>
          </div>
        </>
      )}
    </>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────
const TABS = [
  { id: 'ingresos',  label: 'Ingresos' },
  { id: 'consultas', label: 'Consultas' },
  { id: 'clientes',  label: 'Clientes' },
  { id: 'fiscal',    label: 'Fiscal' },
] as const;
type TabId = typeof TABS[number]['id'];

export function AdminReportesPage() {
  const [activeTab, setActiveTab] = useState<TabId>('ingresos');

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
        <p className="dash-eyebrow">✦ Reportes</p>
        <h1 className="dash-title">Reportes</h1>
      </motion.div>

      <div className="admin-tabs" style={{ display: 'flex', gap: '0.5rem', marginTop: '1.5rem', borderBottom: '1px solid rgba(245,230,211,0.15)', paddingBottom: '0.25rem' }}>
        {TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => setActiveTab(tab.id)}
            className={activeTab === tab.id ? 'btn-primary' : 'btn-secondary'}
            style={{ fontSize: '0.9rem', padding: '0.4rem 1rem' }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <motion.div key={activeTab} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: 0.2 }}>
        {activeTab === 'ingresos'  && <TabIngresos />}
        {activeTab === 'consultas' && <TabConsultas />}
        {activeTab === 'clientes'  && <TabClientes />}
        {activeTab === 'fiscal'    && <TabFiscal />}
      </motion.div>
    </main>
  );
}
