import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  consultarAgenteAdmin,
  consultarAgentePublico,
  consultarAgenteSelf,
  listConversacionesAdmin,
  listConversacionesSelf,
} from '../../lib/agenteApi';
import type { AgenteConversacion, AgenteListResponse } from '../../lib/agenteApi';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';

type Mode = 'publico' | 'self' | 'admin';

interface Msg {
  id: string;
  role: 'user' | 'assistant';
  text: string;
  meta?: { modelo?: string | null; tokens_in?: number | null; tokens_out?: number | null; latencia_ms?: number | null };
}

const HIDDEN_PATHS = [/^\/auth(\/|$)/, /^\/app\/sala\//];

function shouldHideOnRoute(pathname: string): boolean {
  return HIDDEN_PATHS.some((rx) => rx.test(pathname));
}

export function AgenteWidget() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = useAuthStore((s) => s.isAdmin)();
  const location = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [open, setOpen] = useState(false);
  const [pregunta, setPregunta] = useState('');
  const [mensajes, setMensajes] = useState<Msg[]>([]);
  const scrollRef = useRef<HTMLDivElement>(null);

  const mode: Mode = useMemo(() => {
    if (!user) return 'publico';
    if (isAdmin) return 'admin';
    return 'self';
  }, [user, isAdmin]);

  const queryKey = mode === 'self'
    ? ['agente', 'self']
    : mode === 'admin'
      ? ['agente', 'admin']
      : null;

  const historialQuery = useQuery<AgenteListResponse>({
    queryKey: queryKey ?? ['agente', 'noop'],
    queryFn: () => (mode === 'self' ? listConversacionesSelf(20) : listConversacionesAdmin(20)),
    enabled: open && queryKey !== null,
  });

  // Hidratar mensajes con historial al abrir (solo si la lista de mensajes esta vacia)
  useEffect(() => {
    if (!open) return;
    if (mensajes.length > 0) return;
    const hist = historialQuery.data?.data ?? [];
    if (hist.length === 0) {
      setMensajes([
        {
          id: 'welcome',
          role: 'assistant',
          text: user
            ? `¡Hola, ${user.nombre?.split(' ')[0] ?? ''}! Soy el asistente de TarotEstrellas. ¿En qué puedo ayudarte hoy?`
            : '¡Hola! Soy el asistente de TarotEstrellas. Pregúntame lo que quieras saber sobre nuestras consultas, especialistas o cómo agendar.',
        },
      ]);
      return;
    }
    // Mostrar las ultimas 10 conversaciones (orden cronologico ascendente)
    const ordered = [...hist].reverse().slice(-10);
    const expanded: Msg[] = [];
    ordered.forEach((c) => {
      expanded.push({ id: `q-${c.uuid}`, role: 'user', text: c.pregunta });
      if (c.respuesta) {
        expanded.push({
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
    });
    setMensajes(expanded);
  }, [open, historialQuery.data, mensajes.length, user]);

  useEffect(() => {
    if (open && scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [open, mensajes]);

  const mutation = useMutation<AgenteConversacion, unknown, string>({
    mutationFn: async (q) => {
      if (mode === 'publico') return consultarAgentePublico(q);
      if (mode === 'self') return consultarAgenteSelf(q);
      return consultarAgenteAdmin(q);
    },
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
      if (queryKey) queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: any) => {
      const detalle =
        err?.response?.data?.detalle ?? err?.response?.data?.message ?? 'Error consultando al agente.';
      toast.error(detalle);
      setMensajes((prev) => [
        ...prev,
        { id: `e-${Date.now()}`, role: 'assistant', text: `⚠️ ${detalle}` },
      ]);
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = pregunta.trim();
    if (trimmed.length < 3) {
      toast.error('La pregunta debe tener al menos 3 caracteres.');
      return;
    }
    setMensajes((prev) => [...prev, { id: `q-${Date.now()}`, role: 'user', text: trimmed }]);
    setPregunta('');
    mutation.mutate(trimmed);
  };

  const nuevoChat = () => {
    setMensajes([
      {
        id: `welcome-${Date.now()}`,
        role: 'assistant',
        text: user
          ? `Nuevo chat iniciado. ¿En qué puedo ayudarte, ${user.nombre?.split(' ')[0] ?? ''}?`
          : 'Nuevo chat iniciado. ¿En qué puedo ayudarte?',
      },
    ]);
  };

  const expandir = () => {
    setOpen(false);
    navigate(isAdmin ? '/app/admin/asistente-ia' : '/app/asistente-ia');
  };

  if (shouldHideOnRoute(location.pathname)) return null;

  return (
    <>
      {!open && (
        <button
          type="button"
          className="agente-fab"
          aria-label="Abrir asistente IA"
          onClick={() => setOpen(true)}
        >
          <span aria-hidden="true">🤖</span>
        </button>
      )}

      {open && (
        <div className="agente-widget" role="dialog" aria-label="Asistente IA TarotEstrellas">
          <header className="agente-widget__header">
            <div className="agente-widget__title">
              <span className="agente-widget__avatar" aria-hidden="true">🤖</span>
              <div>
                <strong>Asistente TarotEstrellas</strong>
                <small>{mode === 'publico' ? 'Visitante' : mode === 'admin' ? 'Modo administradora' : `Hola, ${user?.nombre ?? ''}`}</small>
              </div>
            </div>
            <div className="agente-widget__actions">
              <button type="button" onClick={nuevoChat} title="Nuevo chat" aria-label="Nuevo chat">＋</button>
              {(mode === 'self' || mode === 'admin') && (
                <button type="button" onClick={expandir} title="Ver pantalla completa" aria-label="Pantalla completa">⛶</button>
              )}
              <button type="button" onClick={() => setOpen(false)} title="Cerrar" aria-label="Cerrar">×</button>
            </div>
          </header>

          <div className="agente-widget__body" ref={scrollRef}>
            {mensajes.map((m) => (
              <div key={m.id} className={`agente-msg agente-msg--${m.role}`}>
                <div className="agente-msg__bubble">
                  <p style={{ whiteSpace: 'pre-wrap', margin: 0 }}>{m.text}</p>
                  {m.meta && m.role === 'assistant' && (
                    <small className="agente-msg__meta">
                      {m.meta.modelo} · {m.meta.tokens_in ?? '?'}/{m.meta.tokens_out ?? '?'} tk · {m.meta.latencia_ms ?? '?'} ms
                    </small>
                  )}
                </div>
              </div>
            ))}
            {mutation.isPending && (
              <div className="agente-msg agente-msg--assistant">
                <div className="agente-msg__bubble agente-msg__bubble--typing">
                  <span /><span /><span />
                </div>
              </div>
            )}
          </div>

          <form className="agente-widget__form" onSubmit={submit}>
            <input
              type="text"
              value={pregunta}
              onChange={(e) => setPregunta(e.target.value)}
              placeholder={mode === 'admin' ? 'Pregunta sobre cualquier cliente o la plataforma…' : 'Escribe tu mensaje…'}
              maxLength={2000}
              disabled={mutation.isPending}
              aria-label="Pregunta al asistente"
            />
            <button type="submit" disabled={mutation.isPending} aria-label="Enviar">
              ➤
            </button>
          </form>
        </div>
      )}
    </>
  );
}

