import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  consultarAgenteAdmin,
  consultarAgenteSelf,
  listConversacionesAdmin,
  listConversacionesSelf,
} from '../../lib/agenteApi';
import type { AgenteConversacion, AgenteListResponse } from '../../lib/agenteApi';
import { toast } from '../../stores/toastStore';
import { useAuthStore } from '../../stores/authStore';

interface Props {
  mode: 'self' | 'admin';
  clienteUuid?: string;
}

interface Msg {
  id: string;
  role: 'user' | 'assistant';
  text: string;
  meta?: { modelo?: string | null; tokens_in?: number | null; tokens_out?: number | null; latencia_ms?: number | null };
}

export function AgenteChat({ mode, clienteUuid }: Props) {
  const user = useAuthStore((s) => s.user);
  const [pregunta, setPregunta] = useState('');
  const [mensajes, setMensajes] = useState<Msg[]>([]);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const queryClient = useQueryClient();
  const scrollRef = useRef<HTMLDivElement>(null);

  const queryKey = mode === 'self' ? ['agente', 'self'] : ['agente', 'admin', clienteUuid];

  const listQuery = useQuery<AgenteListResponse>({
    queryKey,
    queryFn: () =>
      mode === 'self'
        ? listConversacionesSelf(50)
        : listConversacionesAdmin(50, clienteUuid as string),
    enabled: mode === 'self' || Boolean(clienteUuid),
  });

  const conversaciones = useMemo(() => listQuery.data?.data ?? [], [listQuery.data]);

  const nombreCorto = user?.nombre?.split(' ')[0] ?? '';
  const saludo = nombreCorto
    ? `✨ Hola ${nombreCorto}, soy Astrea. ¿En qué te acompaño hoy?`
    : '✨ Hola, soy Astrea. ¿En qué te puedo ayudar?';

  // Saludo inicial
  useEffect(() => {
    if (mensajes.length === 0) {
      setMensajes([{ id: `welcome-${Date.now()}`, role: 'assistant', text: saludo }]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [mensajes]);

  const mutation = useMutation<AgenteConversacion, unknown, string>({
    mutationFn: async (q) =>
      mode === 'self' ? consultarAgenteSelf(q) : consultarAgenteAdmin(q, clienteUuid as string),
    onSuccess: (data) => {
      setMensajes((prev) => [
        ...prev,
        {
          id: `a-${data.uuid}`,
          role: 'assistant',
          text: data.respuesta ?? '(sin respuesta)',
          meta: {
            modelo: data.modelo,
            tokens_in: data.tokens_in,
            tokens_out: data.tokens_out,
            latencia_ms: data.latencia_ms,
          },
        },
      ]);
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: any) => {
      const detalle = err?.response?.data?.detalle ?? err?.response?.data?.message ?? 'Error consultando al agente.';
      toast.error(detalle);
      setMensajes((prev) => [
        ...prev,
        { id: `e-${Date.now()}`, role: 'assistant', text: `⚠️ ${detalle}` },
      ]);
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    enviarPregunta(pregunta);
  };

  const enviarPregunta = (texto: string) => {
    const trimmed = texto.trim();
    if (trimmed.length < 3) {
      toast.error('La pregunta debe tener al menos 3 caracteres.');
      return;
    }
    if (mode === 'admin' && !clienteUuid) {
      toast.error('Selecciona primero un cliente.');
      return;
    }
    if (mutation.isPending) return;
    setMensajes((prev) => [...prev, { id: `q-${Date.now()}`, role: 'user', text: trimmed }]);
    setPregunta('');
    mutation.mutate(trimmed);
  };

  const SUGERENCIAS_SELF = [
    '¿Qué temas recurrentes aparecen en mis sesiones?',
    '¿Cuál fue mi última consulta y qué me dijeron?',
    'Resume mis sesiones de los últimos 3 meses.',
    '¿Qué consejos he recibido sobre mi vida amorosa?',
  ];
  const SUGERENCIAS_ADMIN = [
    'Resúmeme las últimas consultas de este cliente.',
    '¿Qué temas trae con más frecuencia?',
    '¿Hay algún patrón emocional importante?',
    'Dame puntos clave para la próxima sesión.',
  ];
  const sugerencias = mode === 'admin' ? SUGERENCIAS_ADMIN : SUGERENCIAS_SELF;
  const mostrarSugerencias = mensajes.length <= 1 && !mutation.isPending;

  const nuevoChat = () => {
    setMensajes([{ id: `welcome-${Date.now()}`, role: 'assistant', text: saludo }]);
    setSidebarOpen(false);
  };

  const cargarConversacion = (c: AgenteConversacion) => {
    const next: Msg[] = [
      { id: `welcome-${Date.now()}`, role: 'assistant', text: saludo },
      { id: `q-${c.uuid}`, role: 'user', text: c.pregunta },
    ];
    if (c.respuesta) {
      next.push({
        id: `a-${c.uuid}`,
        role: 'assistant',
        text: c.respuesta,
        meta: {
          modelo: c.modelo,
          tokens_in: c.tokens_in,
          tokens_out: c.tokens_out,
          latencia_ms: c.latencia_ms,
        },
      });
    }
    setMensajes(next);
    setSidebarOpen(false);
  };

  return (
    <section className="agente-chat" aria-label="Asistente IA">
      <div className="agente-chat__layout">
        {/* Sidebar */}
        <aside
          className={`agente-chat__sidebar ${sidebarOpen ? 'agente-chat__sidebar--open' : ''}`}
          aria-label="Historial de conversaciones"
        >
          <div className="agente-chat__sidebar-header">
            <h3>Conversaciones</h3>
            <button
              type="button"
              className="agente-chat__sidebar-close"
              onClick={() => setSidebarOpen(false)}
              aria-label="Cerrar historial"
            >×</button>
          </div>
          <button type="button" className="agente-chat__new-btn" onClick={nuevoChat}>
            ＋ Nueva conversación
          </button>
          <div className="agente-chat__sidebar-list">
            {listQuery.isLoading && <p className="agente-chat__hint">Cargando…</p>}
            {listQuery.isError && (
              <p className="agente-chat__hint agente-chat__hint--error">No se pudo cargar.</p>
            )}
            {!listQuery.isLoading && !listQuery.isError && conversaciones.length === 0 && (
              <p className="agente-chat__hint">Aún no hay preguntas registradas.</p>
            )}
            <ul>
              {conversaciones.map((c) => (
                <li key={c.uuid}>
                  <button
                    type="button"
                    className="agente-chat__sidebar-item"
                    onClick={() => cargarConversacion(c)}
                    title={c.pregunta}
                  >
                    <span className="agente-chat__sidebar-item-text">{c.pregunta}</span>
                    <small className="agente-chat__sidebar-item-date">
                      {c.created_at ? new Date(c.created_at).toLocaleDateString() : ''}
                    </small>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </aside>

        {sidebarOpen && (
          <div
            className="agente-chat__backdrop"
            onClick={() => setSidebarOpen(false)}
            aria-hidden="true"
          />
        )}

        {/* Main thread */}
        <div className="agente-chat__main">
          <div className="agente-chat__toolbar">
            <button
              type="button"
              className="agente-chat__icon-btn"
              onClick={() => setSidebarOpen(true)}
              aria-label="Abrir historial"
              title="Historial"
            >☰</button>
            <span className="agente-chat__toolbar-title">
              {mode === 'self' ? 'Tu conversación con Astrea' : 'Conversación'}
            </span>
            <button
              type="button"
              className="agente-chat__icon-btn"
              onClick={nuevoChat}
              aria-label="Nueva conversación"
              title="Nueva conversación"
            >＋</button>
          </div>

          <div className="agente-chat__thread" ref={scrollRef}>
            {mensajes.map((m) => (
              <div
                key={m.id}
                className={`agente-chat__bubble agente-chat__bubble--${m.role === 'user' ? 'user' : 'bot'}`}
              >
                <span className="agente-chat__bubble-label">
                  {m.role === 'user' ? 'Tú' : 'Astrea ✨'}
                </span>
                <p>{m.text}</p>
                {m.meta?.modelo && (
                  <small className="agente-chat__meta">
                    {m.meta.modelo} · {m.meta.tokens_in ?? '?'}/{m.meta.tokens_out ?? '?'} tok · {m.meta.latencia_ms ?? '?'}ms
                  </small>
                )}
              </div>
            ))}
            {mutation.isPending && (
              <div className="agente-chat__bubble agente-chat__bubble--bot agente-chat__bubble--typing">
                <span className="agente-chat__bubble-label">Astrea ✨</span>
                <span className="agente-chat__typing">
                  <span /><span /><span />
                </span>
              </div>
            )}
          </div>

          {mostrarSugerencias && (
            <div className="agente-chat__suggestions" role="group" aria-label="Preguntas sugeridas">
              <span className="agente-chat__suggestions-title">💡 Prueba con:</span>
              <div className="agente-chat__suggestions-chips">
                {sugerencias.map((s) => (
                  <button
                    key={s}
                    type="button"
                    className="agente-chat__chip"
                    onClick={() => enviarPregunta(s)}
                  >
                    {s}
                  </button>
                ))}
              </div>
            </div>
          )}

          <form onSubmit={submit} className="agente-chat__form">
            <textarea
              className="agente-chat__textarea"
              value={pregunta}
              onChange={(e) => setPregunta(e.target.value)}
              rows={2}
              maxLength={2000}
              placeholder="Escribe tu mensaje a Astrea…"
              disabled={mutation.isPending}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault();
                  submit(e as any);
                }
              }}
            />
            <div className="agente-chat__form-actions">
              <span className="agente-chat__counter">{pregunta.length}/2000</span>
              <button
                type="submit"
                className="btn-primary agente-chat__submit"
                disabled={mutation.isPending || pregunta.trim().length < 3}
              >
                {mutation.isPending ? 'Enviando…' : 'Enviar ✨'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>
  );
}
