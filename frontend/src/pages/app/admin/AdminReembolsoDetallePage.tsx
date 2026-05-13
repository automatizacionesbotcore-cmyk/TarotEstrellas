import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { Timeline, type TimelineEvent } from '../../../components/admin/Timeline';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Reembolso = {
  uuid:   string;
  estado: string;
  razon:  string;
  metodo: string;
  monto_centavos: number;
  moneda: string;
  solicitado_en: string | null;
  procesado_en:  string | null;
  cita?:    { uuid: string } | null;
  cliente?: { email: string; nombre?: string | null } | null;
  pago?:    { uuid: string; canal: string } | null;
  metadata?: Record<string, unknown> | null;
};

type DetalleResponse = { data: { reembolso: Reembolso; timeline: TimelineEvent[] } };

export function AdminReembolsoDetallePage() {
  const { uuid = '' } = useParams();
  const queryClient   = useQueryClient();
  const [action, setAction]   = useState<'procesar' | 'reintentar' | 'marcar_completado' | null>(null);
  const [referencia, setReferencia] = useState('');
  const [nota, setNota] = useState('');

  const query = useQuery({
    queryKey: ['admin', 'reembolsos', 'detalle', uuid],
    queryFn:  async () => (await api.get<DetalleResponse>(`/admin/reembolsos/${uuid}`)).data.data,
    enabled:  !!uuid,
  });

  const procesar = useMutation({
    mutationFn: async () => {
      const payload: Record<string, unknown> = { accion: action, nota_admin: nota };
      if (action === 'marcar_completado') payload.referencia_manual = referencia;
      return api.post(`/admin/reembolsos/${uuid}/procesar`, payload);
    },
    onSuccess: () => {
      setAction(null);
      setReferencia('');
      setNota('');
      queryClient.invalidateQueries({ queryKey: ['admin', 'reembolsos'] });
    },
  });

  if (query.isLoading) return <main className="page-content"><p>Cargando…</p></main>;
  if (!query.data)     return <main className="page-content"><p>No encontrado.</p></main>;

  const { reembolso, timeline } = query.data;

  return (
    <main className="page-content">
      <Link to="/app/admin/reembolsos">← Volver</Link>
      <h1>Reembolso {reembolso.uuid.slice(0, 8)}…</h1>

      <section className="dash-stats">
        <div className="stat-card"><span className="stat-value">{reembolso.estado}</span><span className="stat-label">Estado</span></div>
        <div className="stat-card"><span className="stat-value">{reembolso.razon}</span><span className="stat-label">Razón</span></div>
        <div className="stat-card"><span className="stat-value">{reembolso.metodo}</span><span className="stat-label">Método</span></div>
        <div className="stat-card"><span className="stat-value">{(reembolso.monto_centavos / 100).toFixed(2)} {reembolso.moneda}</span><span className="stat-label">Monto</span></div>
      </section>

      <section>
        <h2>Acciones</h2>
       <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          {reembolso.estado === 'pendiente' && (
            <button className="btn-primary" onClick={() => setAction('procesar')} disabled={procesar.isPending}>Procesar</button>
          )}
          {reembolso.estado === 'fallido' && (
            <button className="btn-secondary" onClick={() => setAction('reintentar')} disabled={procesar.isPending}>Reintentar</button>
          )}
          {reembolso.estado !== 'completado' && (
            <button className="btn-secondary" onClick={() => setAction('marcar_completado')} disabled={procesar.isPending}>Marcar completado</button>
          )}
        </div>
      </section>

      <section>
        <h2>Timeline</h2>
        <Timeline events={timeline} />
      </section>

      {action === 'marcar_completado' && (
        <div className="confirm-dialog-backdrop">
          <div className="confirm-dialog" style={{ maxWidth: 500 }}>
            <h3>Marcar como completado</h3>
            <form onSubmit={(e) => { e.preventDefault(); procesar.mutate(); }} style={{ display: 'grid', gap: '1rem' }}>
              <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Referencia manual</span>
                <input className="input-field" required value={referencia} onChange={(e) => setReferencia(e.target.value)} />
              </label>
              <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Nota admin</span>
                <textarea className="input-field" value={nota} onChange={(e) => setNota(e.target.value)} rows={3} />
              </label>
              <div className="confirm-dialog-actions">
                <button type="button" className="btn-secondary" onClick={() => setAction(null)}>Cancelar</button>
                <button type="submit" className="btn-primary" disabled={procesar.isPending}>
                  {procesar.isPending ? 'Procesando…' : 'Confirmar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={action === 'procesar' || action === 'reintentar'}
        title={action === 'procesar' ? 'Procesar reembolso' : 'Reintentar reembolso'}
        message={action === 'procesar'
          ? '¿Encolar el job de procesamiento Stripe?'
          : '¿Mover a pendiente y reencolar el job Stripe?'}
        loading={procesar.isPending}
        onConfirm={() => procesar.mutate()}
        onCancel={()  => setAction(null)}
      />
    </main>
  );
}
