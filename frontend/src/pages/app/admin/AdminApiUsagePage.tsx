import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { getApiUsage, triggerApiCheck, updateApiLimits, type ApiUsageRow } from '../../../lib/apiUsageApi';

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
    if (emails) payload.alert_emails = emails;
    saveMut.mutate(payload);
  };

  return (
    <div style={{ padding: 24, maxWidth: 1100 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <h2 style={{ margin: 0, color: 'var(--text)' }}>APIs externas — consumo {data.periodo}</h2>
        <button
          onClick={() => checkMut.mutate()}
          disabled={checkMut.isPending}
          className="btn-primary"
        >
          {checkMut.isPending ? 'Verificando…' : 'Verificar ahora'}
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 16 }}>
        {data.rows.map((r) => <ProviderCard key={r.provider} row={r} />)}
      </div>

      <h3 style={{ marginTop: 32, color: 'var(--text)' }}>Límites y umbrales</h3>
      <table className="admin-table" style={{ width: '100%' }}>
        <thead>
          <tr>
            <th style={{ padding: 8, textAlign: 'left' }}>Proveedor</th>
            <th style={{ padding: 8, textAlign: 'left' }}>Límite mensual</th>
            <th style={{ padding: 8, textAlign: 'left' }}>Umbral aviso (%)</th>
          </tr>
        </thead>
        <tbody>
          {data.rows.map((r) => {
            const d = getDraft(r.provider);
            return (
              <tr key={r.provider}>
                <td style={{ padding: 8, textTransform: 'capitalize' }}>{r.provider} ({r.unidad})</td>
                <td style={{ padding: 8 }}>
                  <input
                    type="number" step="0.01" min="0"
                    value={d.limite}
                    onChange={(e) => setDraftLimits((s) => ({ ...s, [r.provider]: { ...getDraft(r.provider), limite: e.target.value } }))}
                    className="form-input"
                    style={{ width: 120 }}
                  />
                </td>
                <td style={{ padding: 8 }}>
                  <input
                    type="number" step="1" min="1" max="100"
                    value={d.warn_pct}
                    onChange={(e) => setDraftLimits((s) => ({ ...s, [r.provider]: { ...getDraft(r.provider), warn_pct: e.target.value } }))}
                    className="form-input"
                    style={{ width: 80 }}
                  />
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>

      <h3 style={{ marginTop: 24, color: 'var(--text)' }}>Emails extra para alertas</h3>
      <p style={{ fontSize: 13, color: 'var(--text-muted)', margin: '4px 0 8px' }}>
        Destinatarios actuales por rol: {data.destinatarios.join(', ') || '—'}
      </p>
      <input
        type="text"
        defaultValue={data.emails_extra ?? ''}
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
        <table className="admin-table" style={{ width: '100%' }}>
          <thead>
            <tr>
              <th style={{ padding: 8, textAlign: 'left' }}>Fecha</th>
              <th style={{ padding: 8, textAlign: 'left' }}>Proveedor</th>
              <th style={{ padding: 8, textAlign: 'left' }}>Periodo</th>
              <th style={{ padding: 8, textAlign: 'left' }}>Nivel</th>
              <th style={{ padding: 8, textAlign: 'left' }}>%</th>
              <th style={{ padding: 8, textAlign: 'left' }}>Uso / Límite</th>
            </tr>
          </thead>
          <tbody>
            {data.alertas_recientes.map((a) => (
              <tr key={a.id}>
                <td style={{ padding: 8 }}>{new Date(a.created_at).toLocaleString()}</td>
                <td style={{ padding: 8, textTransform: 'capitalize' }}>{a.provider}</td>
                <td style={{ padding: 8 }}>{a.period}</td>
                <td style={{ padding: 8, color: a.nivel === 'exceeded' ? '#dc2626' : '#d97706', fontWeight: 600 }}>
                  {a.nivel}
                </td>
                <td style={{ padding: 8 }}>{Number(a.porcentaje).toFixed(1)}%</td>
                <td style={{ padding: 8 }}>{Number(a.usado).toFixed(2)} / {a.limite}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
