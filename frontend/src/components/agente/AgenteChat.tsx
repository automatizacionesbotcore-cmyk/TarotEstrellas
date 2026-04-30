import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import {
  consultarAgenteAdmin,
  consultarAgenteSelf,
  listConversacionesAdmin,
  listConversacionesSelf,
} from '../../lib/agenteApi';
import type { AgenteConversacion, AgenteListResponse } from '../../lib/agenteApi';
import { toast } from '../../stores/toastStore';

interface Props {
  mode: 'self' | 'admin';
  clienteUuid?: string;
}

export function AgenteChat({ mode, clienteUuid }: Props) {
  const [pregunta, setPregunta] = useState('');
  const queryClient = useQueryClient();
  const queryKey = mode === 'self' ? ['agente', 'self'] : ['agente', 'admin', clienteUuid];

  const listQuery = useQuery<AgenteListResponse>({
    queryKey,
    queryFn: () =>
      mode === 'self'
        ? listConversacionesSelf()
        : listConversacionesAdmin(clienteUuid as string),
    enabled: mode === 'self' || Boolean(clienteUuid),
  });

  const mutation = useMutation<AgenteConversacion, unknown, string>({
    mutationFn: async (q) =>
      mode === 'self'
        ? consultarAgenteSelf(q)
        : consultarAgenteAdmin(clienteUuid as string, q),
    onSuccess: () => {
      setPregunta('');
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: any) => {
      const detalle = err?.response?.data?.detalle ?? err?.response?.data?.message ?? 'Error consultando al agente.';
      toast.error(detalle);
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = pregunta.trim();
    if (trimmed.length < 3) {
      toast.error('La pregunta debe tener al menos 3 caracteres.');
      return;
    }
    if (mode === 'admin' && !clienteUuid) {
      toast.error('Selecciona primero un cliente.');
      return;
    }
    mutation.mutate(trimmed);
  };

  const conversaciones = listQuery.data?.data ?? [];

  return (
    <section className="agente-chat" aria-label="Asistente IA">
      <form onSubmit={submit} className="agente-chat__form">
        <label htmlFor="agente-pregunta" className="agente-chat__label">
          {mode === 'self' ? 'Pregunta al asistente sobre tu historial' : 'Pregunta al agente sobre el cliente'}
        </label>
        <textarea
          id="agente-pregunta"
          value={pregunta}
          onChange={(e) => setPregunta(e.target.value)}
          rows={3}
          maxLength={2000}
          placeholder="¿Qué temas recurrentes aparecen en mis sesiones?"
          disabled={mutation.isPending}
        />
        <button type="submit" className="btn-primary" disabled={mutation.isPending}>
          {mutation.isPending ? 'Consultando…' : 'Preguntar'}
        </button>
      </form>

      {mutation.data && (
        <article className="agente-chat__respuesta-actual" aria-live="polite">
          <header><strong>Última respuesta</strong></header>
          <p style={{ whiteSpace: 'pre-wrap' }}>{mutation.data.respuesta}</p>
          <small style={{ color: 'var(--text-muted)' }}>
            {mutation.data.modelo} · {mutation.data.tokens_in ?? '?'} in / {mutation.data.tokens_out ?? '?'} out · {mutation.data.latencia_ms ?? '?'} ms · contexto: {mutation.data.contexto_sesiones ?? 0} sesiones
          </small>
        </article>
      )}

      <div className="agente-chat__historial" style={{ marginTop: '1.5rem' }}>
        <h3>Conversaciones previas</h3>
        {listQuery.isLoading && <p style={{ color: 'var(--text-muted)' }}>Cargando…</p>}
        {listQuery.isError && <p style={{ color: 'var(--text-error)' }}>No se pudo cargar el historial.</p>}
        {!listQuery.isLoading && conversaciones.length === 0 && (
          <p style={{ color: 'var(--text-muted)' }}>Aún no hay preguntas registradas.</p>
        )}
        <ul style={{ listStyle: 'none', padding: 0 }}>
          {conversaciones.map((c) => (
            <li key={c.uuid} style={{ borderTop: '1px solid var(--border)', padding: '0.75rem 0' }}>
              <p style={{ margin: 0 }}><strong>P:</strong> {c.pregunta}</p>
              <p style={{ margin: '0.25rem 0 0', whiteSpace: 'pre-wrap' }}>
                <strong>R:</strong> {c.respuesta ?? <em style={{ color: 'var(--text-error)' }}>(sin respuesta)</em>}
              </p>
              <small style={{ color: 'var(--text-muted)' }}>
                {c.created_at ? new Date(c.created_at).toLocaleString() : ''} · {c.autor?.name ?? c.autor_rol}
              </small>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
