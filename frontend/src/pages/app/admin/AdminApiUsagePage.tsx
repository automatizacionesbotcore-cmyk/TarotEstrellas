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
    <div style={{
      border: '1px solid var(--border-subtle)',
      borderLeft: `6px solid ${ESTADO_COLOR[row.estado]}`,
      borderRadius: 8,
      padding: 16,
      background: 'var(--card)',
    }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
        <h4 style={{ margin: 0, textTransform: 'capitalize', color: 'var(--text)' }}>{row.provider}</h4>
        <span style={{ color: ESTADO_COLOR[row.estado], fontWeight: 600, fontSize: 12 }}>
          {ESTADO_LABEL[row.estado]}
        </span>
      </div>
      <div style={{ marginTop: 8, fontSize: 14, color: 'var(--text-muted)' }}>
        {row.usado.toFixed(2)} / {row.limite} {row.unidad}
      </div>
      <div style={{ marginTop: 8, height: 8, background: 'var(--bg-secondary)', borderRadius: 4, overflow: 'hidden' }}>
        <div style={{ width: `${pct}%`, height: '100%', background: ESTADO_COLOR[row.estado] }} />
      </div>
      <div style={{ marginTop: 4, fontSize: 12, color: 'var(--text-muted)' }}>
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

  if (isLoading) return <p style={{ color: 'var(--text)' }}>Cargando consumo de APIs…</p>;
  if (error || !data) return <p style={{ color: '#dc2626' }}>Error al cargar el dashboard.</p>;

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
      render: (r) => <span style={{ textTransform: 'capitalize' }}>{r.provider} ({r.unidad})</span>,
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
    { key: 'provider', label: 'Proveedor', render: (a) => <span style={{ textTransform: 'capitalize' }}>{a.provider}</span> },
    { key: 'period', label: 'Periodo' },
    {
      key: 'nivel',
      label: 'Nivel',
      render: (a) => (
        <span style={{ color: a.nivel === 'exceeded' ? '#dc2626' : '#d97706', fontWeight: 600 }}>
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
          <h1 style={{ margin: 0, color: 'var(--text)' }}>APIs externas</h1>
          <p className="text-muted" style={{ margin: '0.25rem 0 0' }}>Periodo: {data.periodo}</p>
        </div>
        <button
          onClick={() => checkMut.mutate()}
          disabled={checkMut.isPending}
          className="btn-primary"
        >
          {checkMut.isPending ? 'Verificando…' : 'Verificar ahora'}
        </button>
      </header>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 16 }}>
        {data.rows.map((r) => <ProviderCard key={r.provider} row={r} />)}
      </div>

      <h3 style={{ marginTop: 32, color: 'var(--text)' }}>Límites y umbrales</h3>
      <AdminTable
        columns={limitColumns}
        rows={data.rows}
        rowKey={(r) => r.provider}
        searchable={false}
        pageSizeOptions={[]}
        showFooter={false}
      />

      <h3 style={{ marginTop: 24, color: 'var(--text)' }}>Emails extra para alertas</h3>
      <p style={{ fontSize: 13, color: 'var(--text-muted)', margin: '4px 0 8px' }}>
        Destinatarios actuales por rol: {data.destinatarios.join(', ') || '—'}
      </p>
      <input
        type="text"
        value={emails}
        onChange={(e) => setEmails(e.target.value)}
        placeholder="email1@dominio.com, email2@dominio.com"
        className="form-input"
        style={{ width: '100%', maxWidth: 600 }}
      />

      <div style={{ marginTop: 16 }}>
        <button
          onClick={onSave}
          disabled={saveMut.isPending}
          className="btn-primary"
        >
          {saveMut.isPending ? 'Guardando…' : 'Guardar cambios'}
        </button>
        {saveMut.isSuccess && <span style={{ marginLeft: 12, color: 'var(--accent)' }}>✓ Guardado</span>}
      </div>

      <h3 style={{ marginTop: 32, color: 'var(--text)' }}>Alertas recientes</h3>
      {data.alertas_recientes.length === 0 ? (
        <p style={{ color: 'var(--text-muted)' }}>Sin alertas registradas.</p>
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
    </main>
  );
}
