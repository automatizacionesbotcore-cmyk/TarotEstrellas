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
        : listConversacionesAdmin(20, clienteUuid as string),
    enabled: mode === 'self' || Boolean(clienteUuid),
  });

  const mutation = useMutation<AgenteConversacion, unknown, string>({
    mutationFn: async (q) =>
      mode === 'self'
        ? consultarAgenteSelf(q)
        : consultarAgenteAdmin(q, clienteUuid as string),
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
          className="agente-chat__textarea"
          value={pregunta}
          onChange={(e) => setPregunta(e.target.value)}
          rows={3}
          maxLength={2000}
          placeholder="¿Qué temas recurrentes aparecen en mis sesiones?"
          disabled={mutation.isPending}
        />
        <div className="agente-chat__form-actions">
          <span className="agente-chat__counter">{pregunta.length}/2000</span>
          <button type="submit" className="btn-primary agente-chat__submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Consultando…' : 'Preguntar ✨'}
          </button>
        </div>
      </form>

      {mutation.data && (
        <article className="agente-chat__respuesta-actual" aria-live="polite">
          <header className="agente-chat__respuesta-header">
            <span className="agente-chat__badge">✨ Última respuesta</span>
          </header>
          <p className="agente-chat__respuesta-texto">{mutation.data.respuesta}</p>
          <small className="agente-chat__meta">
            {mutation.data.modelo} · {mutation.data.tokens_in ?? '?'} in / {mutation.data.tokens_out ?? '?'} out · {mutation.data.latencia_ms ?? '?'} ms · contexto: {mutation.data.contexto_sesiones ?? 0} sesiones
          </small>
        </article>
      )}

      <div className="agente-chat__historial">
        <h3 className="agente-chat__historial-title">Conversaciones previas</h3>
        {listQuery.isLoading && <p className="agente-chat__hint">Cargando…</p>}
        {listQuery.isError && <p className="agente-chat__hint agente-chat__hint--error">No se pudo cargar el historial.</p>}
        {!listQuery.isLoading && !listQuery.isError && conversaciones.length === 0 && (
          <p className="agente-chat__hint">Aún no hay preguntas registradas.</p>
        )}
        <ul className="agente-chat__lista">
          {conversaciones.map((c) => (
            <li key={c.uuid} className="agente-chat__item">
              <div className="agente-chat__bubble agente-chat__bubble--user">
                <span className="agente-chat__bubble-label">Tú</span>
                <p>{c.pregunta}</p>
              </div>
              <div className="agente-chat__bubble agente-chat__bubble--bot">
                <span className="agente-chat__bubble-label">Astrea ✨</span>
                <p>
                  {c.respuesta ?? <em className="agente-chat__hint--error">(sin respuesta)</em>}
                </p>
              </div>
              <small className="agente-chat__meta">
                {c.created_at ? new Date(c.created_at).toLocaleString() : ''} · {c.autor?.name ?? c.autor_rol}
              </small>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
