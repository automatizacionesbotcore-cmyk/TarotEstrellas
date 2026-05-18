import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { getApiUsage, triggerApiCheck, updateApiLimits, type ApiUsageAlert, type ApiUsageRow } from '../../../lib/apiUsageApi';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

const ESTADO_COLOR: Record<ApiUsageRow['estado'], string> = {
  ok: '#16a34a',
  warning: '#d97706',
  exceeded: '#dc2626',
};

const ESTADO_LABEL: Record<ApiUsageRow['estado'], string> = {
  ok: 'OK',
  warning: 'Atención',
  exceeded: 'Excedido',
};

function ProviderCard({ row }: { row: ApiUsageRow }) {
  const pct = Math.min(100, row.porcentaje);
  return (
    <div className={`admin-provider-card admin-provider-card--${row.estado}`}>
      <div className="admin-provider-card__head">
        <h4>{row.provider}</h4>
        <span className={`badge ${row.estado === 'ok' ? 'badge-success' : row.estado === 'warning' ? 'badge-warning' : 'badge-danger'}`}>
          {ESTADO_LABEL[row.estado]}
        </span>
      </div>
      <div className="admin-provider-card__usage">
        {row.usado.toFixed(2)} / {row.limite} {row.unidad}
      </div>
      <div className="admin-progress">
        <div style={{ width: `${pct}%`, background: ESTADO_COLOR[row.estado] }} />
      </div>
      <div className="admin-provider-card__meta">
        {row.porcentaje.toFixed(1)}% (umbral: {row.warn_pct}%)
      </div>
    </div>
  );
}

export function AdminApiUsagePage() {
  const qc = useQueryClient();
  const { data, isLoading, error } = useQuery({ queryKey: ['admin-api-usage'], queryFn: getApiUsage });
  const [draftLimits, setDraftLimits] = useState<Record<string, { limite: string; warn_pct: string }>>({});
  const [emails, setEmails] = useState<string>('');

  useEffect(() => {
    if (data) {
      setEmails(data.emails_extra ?? '');
    }
  }, [data]);

  const saveMut = useMutation({
    mutationFn: updateApiLimits,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-api-usage'] }),
  });
  const checkMut = useMutation({
    mutationFn: triggerApiCheck,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-api-usage'] }),
  });

  if (isLoading) return <main className="page-content"><p>Cargando consumo de APIs…</p></main>;
  if (error || !data) return <main className="page-content"><p className="form-error">Error al cargar el dashboard.</p></main>;

  const limites = data.limites ?? {};
  const getDraft = (p: string) =>
    draftLimits[p] ?? {
      limite: String(limites[p]?.limite ?? ''),
      warn_pct: String(limites[p]?.warn_pct ?? ''),
    };

  const onSave = () => {
    const payload: Parameters<typeof updateApiLimits>[0] = {};
    for (const p of ['anthropic', 'openai', 'daily']) {
      const d = draftLimits[p];
      if (d) {
        (payload as any)[p] = {
          limite: parseFloat(d.limite || '0'),
          warn_pct: parseFloat(d.warn_pct || '0'),
        };
      }
    }
    payload.alert_emails = emails;
    saveMut.mutate(payload);
  };

  const limitColumns: Column<ApiUsageRow>[] = [
    {
      key: 'provider',
      label: 'Proveedor',
      render: (r) => <span className="admin-capitalize">{r.provider} ({r.unidad})</span>,
    },
    {
      key: 'limite',
      label: 'Límite mensual',
      render: (r) => {
        const d = getDraft(r.provider);
        return (
          <input
            type="number"
            step="0.01"
            min="0"
            value={d.limite}
            onChange={(e) => setDraftLimits((s) => ({ ...s, [r.provider]: { ...getDraft(r.provider), limite: e.target.value } }))}
            className="form-input admin-inline-input"
          />
        );
      },
    },
    {
      key: 'warn_pct',
      label: 'Umbral aviso (%)',
      render: (r) => {
        const d = getDraft(r.provider);
        return (
          <input
            type="number"
            step="1"
            min="1"
            max="100"
            value={d.warn_pct}
            onChange={(e) => setDraftLimits((s) => ({ ...s, [r.provider]: { ...getDraft(r.provider), warn_pct: e.target.value } }))}
            className="form-input admin-inline-input admin-inline-input--small"
          />
        );
      },
    },
  ];

  const alertColumns: Column<ApiUsageAlert>[] = [
    { key: 'created_at', label: 'Fecha', render: (a) => new Date(a.created_at).toLocaleString() },
    { key: 'provider', label: 'Proveedor', render: (a) => <span className="admin-capitalize">{a.provider}</span> },
    { key: 'period', label: 'Periodo' },
    {
      key: 'nivel',
      label: 'Nivel',
      render: (a) => (
        <span className={`badge ${a.nivel === 'exceeded' ? 'badge-danger' : 'badge-warning'}`}>
          {a.nivel}
        </span>
      ),
    },
    { key: 'porcentaje', label: '%', render: (a) => `${Number(a.porcentaje).toFixed(1)}%` },
    { key: 'uso', label: 'Uso / Límite', render: (a) => `${Number(a.usado ?? a.valor_actual ?? 0).toFixed(2)} / ${a.limite}` },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Consumo externo</p>
          <h1>APIs externas</h1>
          <p className="admin-page-subtitle">Periodo: {data.periodo}</p>
        </div>
        <button
          onClick={() => checkMut.mutate()}
          disabled={checkMut.isPending}
          className="btn-primary"
        >
          {checkMut.isPending ? 'Verificando…' : 'Verificar ahora'}
        </button>
      </header>

      <section className="admin-provider-grid">
        {data.rows.map((r) => <ProviderCard key={r.provider} row={r} />)}
      </section>

      <section className="admin-section-block">
        <h2 className="admin-section-title">Límites y umbrales</h2>
        <AdminTable
          columns={limitColumns}
          rows={data.rows}
          rowKey={(r) => r.provider}
          searchable={false}
          pageSizeOptions={[]}
          showFooter={false}
        />
      </section>

      <section className="admin-filter-card admin-alert-config">
        <div>
          <h2 className="admin-section-title">Emails extra para alertas</h2>
          <p className="admin-page-subtitle">
            Destinatarios actuales por rol: {data.destinatarios.join(', ') || '—'}
          </p>
        </div>
        <input
          type="text"
          value={emails}
          onChange={(e) => setEmails(e.target.value)}
          placeholder="email1@dominio.com, email2@dominio.com"
          className="form-input"
        />

        <div className="admin-filter-actions">
          <button
            onClick={onSave}
            disabled={saveMut.isPending}
            className="btn-primary"
          >
            {saveMut.isPending ? 'Guardando…' : 'Guardar cambios'}
          </button>
          {saveMut.isSuccess && <span className="badge badge-success">Guardado</span>}
        </div>
      </section>

      <section className="admin-section-block">
        <h2 className="admin-section-title">Alertas recientes</h2>
        {data.alertas_recientes.length === 0 ? (
          <div className="admin-empty-card"><p className="text-muted">Sin alertas registradas.</p></div>
        ) : (
          <AdminTable
            columns={alertColumns}
            rows={data.alertas_recientes}
            rowKey={(a) => a.id}
            searchable
            searchPlaceholder="Buscar alerta"
            getSearchText={(a) => `${a.provider} ${a.period} ${a.nivel}`}
            pageSize={5}
            pageSizeOptions={[5, 10, 25]}
          />
        )}
      </section>
    </main>
  );
}
