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

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderMarkdown(text: string): string {
  let html = escapeHtml(text);
  html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
  html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
  html = html.replace(/\n/g, '<br/>');
  return html;
}

interface Msg {
  id: string;
  role: 'user' | 'assistant';
  text: string;
  meta?: { modelo?: string | null; tokens_in?: number | null; tokens_out?: number | null; latencia_ms?: number | null };
}

const HIDDEN_PATHS = [/^\/auth(\/|$)/, /^\/app\/sala\//, /^\/app\/asistente-ia(\/|$)/, /^\/app\/admin\/asistente-ia(\/|$)/];

const SALUDOS_PUBLICO = [
  '✨ Hola, soy Astrea, tu guía estelar en TarotEstrellas. ¿Sobre qué quieres saber hoy?',
  '✨ Hola, bienvenido. Soy Astrea. Puedo contarte sobre nuestros servicios, especialistas o cómo agendar tu consulta.',
  '✨ Hola, las estrellas te saludan. Soy Astrea, pregúntame lo que quieras.',
];

function saludoCliente(nombre: string): string[] {
  const primer = nombre.split(' ')[0] || '';
  return [
    `✨ ¡Hola, ${primer}! Soy Astrea. ¿Qué quieres consultar hoy?`,
    `✨ Hola ${primer}, bienvenido de vuelta. Soy Astrea, recuerdo tus sesiones previas. ¿En qué te acompaño?`,
    `✨ Hola ${primer}, las estrellas te saludan. Soy Astrea, pregúntame lo que quieras.`,
  ];
}

function saludoAdmin(nombre?: string): string[] {
  const primer = nombre?.split(' ')[0] ?? 'Chachita';
  return [
    `✨ Hola ${primer}. Soy Astrea. ¿Sobre qué cliente o tema quieres saber?`,
    `✨ Hola ${primer}, lista para asistirte. Pregúntame por la plataforma o por cualquier cliente.`,
    `✨ Hola ${primer}, las estrellas alinean tu jornada. Soy Astrea, ¿qué necesitas?`,
  ];
}

function pickRandom<T>(arr: T[]): T {
  return arr[Math.floor(Math.random() * arr.length)];
}

function shouldHideOnRoute(pathname: string): boolean {
  return HIDDEN_PATHS.some((rx) => rx.test(pathname));
}

function StarIcon({ size = 20 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12 2.5l2.6 6.5 7 .6-5.3 4.6 1.7 6.8L12 17.4l-6 3.6 1.7-6.8L2.4 9.6l7-.6L12 2.5z" />
    </svg>
  );
}

export function AgenteWidget() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = useAuthStore((s) => s.isAdmin)();
  const location = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [open, setOpen] = useState(false);
  const [expanded, setExpanded] = useState(false);
  const [teaserVisible, setTeaserVisible] = useState(false);
  const [teaserDismissed, setTeaserDismissed] = useState(() => {
    return sessionStorage.getItem('astrea-teaser-dismissed') === '1';
  });
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

  const buildSaludo = (): string => {
    if (mode === 'publico') return pickRandom(SALUDOS_PUBLICO);
    if (mode === 'admin') return pickRandom(saludoAdmin(user?.nombre ?? undefined));
    return pickRandom(saludoCliente(user?.nombre ?? ''));
  };

  // Hidratar mensajes con historial al abrir
  useEffect(() => {
    if (!open) return;
    if (mensajes.length > 0) return;
    const hist = historialQuery.data?.data ?? [];
    if (hist.length === 0 || mode === 'publico') {
      setMensajes([{ id: `welcome-${Date.now()}`, role: 'assistant', text: buildSaludo() }]);
      return;
    }
    const ordered = [...hist].reverse().slice(-10);
    const expanded: Msg[] = [{ id: `welcome-${Date.now()}`, role: 'assistant', text: buildSaludo() }];
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, historialQuery.data, mode, user?.nombre]);

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
    setMensajes([{ id: `welcome-${Date.now()}`, role: 'assistant', text: buildSaludo() }]);
  };

  const expandir = () => {
    if (mode === 'publico') {
      setExpanded((value) => !value);
      return;
    }
    setOpen(false);
    navigate(isAdmin ? '/app/admin/asistente-ia' : '/app/asistente-ia');
  };

  const nombreCorto = user?.nombre?.split(' ')[0] ?? '';
  const teaserMensaje = user
    ? `Hola ${nombreCorto} ✨ ¿Te ayudo con algo?`
    : 'Hola ✨ ¿Tienes alguna duda? Pregúntame.';

  useEffect(() => {
    if (open || teaserDismissed) return;
    const t1 = setTimeout(() => setTeaserVisible(true), 3500);
    const t2 = setTimeout(() => setTeaserVisible(false), 14000);
    return () => { clearTimeout(t1); clearTimeout(t2); };
  }, [open, teaserDismissed, user?.uuid]);

  function dismissTeaser() {
    setTeaserVisible(false);
    setTeaserDismissed(true);
    sessionStorage.setItem('astrea-teaser-dismissed', '1');
  }

  if (shouldHideOnRoute(location.pathname)) return null;

  const subtitulo = mode === 'publico'
    ? 'Tu guía estelar'
    : mode === 'admin'
      ? `Modo administradora · ${user?.nombre?.split(' ')[0] ?? ''}`.trim()
      : `Acompañándote, ${user?.nombre?.split(' ')[0] ?? ''}`;

  return (
    <>
      {!open && teaserVisible && (
        <div className="astrea-teaser" role="status" aria-live="polite">
          <button
            type="button"
            className="astrea-teaser__close"
            onClick={dismissTeaser}
            aria-label="Cerrar invitación"
          >×</button>
          <button
            type="button"
            className="astrea-teaser__bubble"
            onClick={() => { setOpen(true); dismissTeaser(); }}
          >
            {teaserMensaje}
          </button>
        </div>
      )}
      {!open && (
        <button
          type="button"
          className="astrea-fab"
          aria-label="Abrir asistente Astrea"
          onClick={() => setOpen(true)}
        >
          <span className="astrea-fab__star" aria-hidden="true">
            <StarIcon size={26} />
          </span>
          <span className="astrea-fab__pulse" aria-hidden="true" />
        </button>
      )}

      {open && (
        <div
          className={`astrea-widget ${expanded ? 'astrea-widget--expanded' : ''}`}
          role="dialog"
          aria-label="Astrea — Asistente de TarotEstrellas"
        >
          <header className="astrea-widget__header">
            <div className="astrea-widget__title">
              <span className="astrea-widget__avatar" aria-hidden="true">
                <StarIcon size={22} />
              </span>
              <div className="astrea-widget__name">
                <strong>Astrea</strong>
                <small>{subtitulo}</small>
              </div>
            </div>
            <div className="astrea-widget__actions">
              <button type="button" onClick={nuevoChat} title="Nuevo chat" aria-label="Nuevo chat">＋</button>
              <button
                type="button"
                onClick={expandir}
                title={expanded ? 'Restaurar' : 'Pantalla completa'}
                aria-label={expanded ? 'Restaurar chat' : 'Pantalla completa'}
              >⛶</button>
              <button
                type="button"
                onClick={() => { setOpen(false); setExpanded(false); }}
                title="Cerrar"
                aria-label="Cerrar"
              >×</button>
            </div>
          </header>

          <div className="astrea-widget__body" ref={scrollRef}>
            <div className="astrea-constellation" aria-hidden="true">
              <span className="astrea-star astrea-star--1" />
              <span className="astrea-star astrea-star--2" />
              <span className="astrea-star astrea-star--3" />
              <span className="astrea-star astrea-star--4" />
              <span className="astrea-star astrea-star--5" />
              <span className="astrea-star astrea-star--6" />
              <span className="astrea-star astrea-star--7" />
              <span className="astrea-star astrea-star--8" />
              <svg className="astrea-constellation__lines" viewBox="0 0 300 500" preserveAspectRatio="none">
                <polyline points="40,60 90,110 160,80 220,150 270,90" fill="none" stroke="currentColor" strokeWidth="0.6" strokeOpacity="0.35" />
                <polyline points="60,300 130,260 180,330 250,310" fill="none" stroke="currentColor" strokeWidth="0.6" strokeOpacity="0.3" />
                <polyline points="40,440 100,400 170,460 240,420" fill="none" stroke="currentColor" strokeWidth="0.6" strokeOpacity="0.3" />
              </svg>
            </div>
            {mensajes.map((m) => (
              <div key={m.id} className={`astrea-msg astrea-msg--${m.role}`}>
                {m.role === 'assistant' && (
                  <span className="astrea-msg__icon" aria-hidden="true"><StarIcon size={14} /></span>
                )}
                <div className="astrea-msg__bubble">
                  <div
                    className="astrea-msg__text"
                    style={{ margin: 0 }}
                    dangerouslySetInnerHTML={{ __html: renderMarkdown(m.text) }}
                  />
                </div>
              </div>
            ))}
            {mutation.isPending && (
              <div className="astrea-msg astrea-msg--assistant">
                <span className="astrea-msg__icon" aria-hidden="true"><StarIcon size={14} /></span>
                <div className="astrea-msg__bubble astrea-msg__bubble--typing">
                  <span /><span /><span />
                </div>
              </div>
            )}
          </div>

          {(() => {
            const sugerenciasPub = [
              '¿Qué servicios ofrecen?',
              '¿Cómo agendar una consulta?',
              '¿Quiénes son sus especialistas?',
            ];
            const sugerenciasSelf = [
              '¿Cuál fue mi última consulta?',
              'Resume mis sesiones recientes',
              '¿Qué temas trato más?',
            ];
            const sugerenciasAdm = [
              'Resume las consultas de hoy',
              '¿Qué clientes tienen seguimiento pendiente?',
              'Dame un patrón emocional general',
            ];
            const sugs = mode === 'publico' ? sugerenciasPub : mode === 'admin' ? sugerenciasAdm : sugerenciasSelf;
            const mostrar = mensajes.length <= 1 && !mutation.isPending;
            if (!mostrar) return null;
            return (
              <div className="astrea-widget__suggestions" role="group" aria-label="Sugerencias">
                {sugs.map((s) => (
                  <button
                    key={s}
                    type="button"
                    className="astrea-widget__chip"
                    onClick={() => {
                      setPregunta('');
                      setMensajes((prev) => [...prev, { id: `q-${Date.now()}`, role: 'user', text: s }]);
                      mutation.mutate(s);
                    }}
                  >
                    {s}
                  </button>
                ))}
              </div>
            );
          })()}

          <form className="astrea-widget__form" onSubmit={submit}>
            <input
              type="text"
              value={pregunta}
              onChange={(e) => setPregunta(e.target.value)}
              placeholder={mode === 'admin' ? 'Pregunta sobre cualquier cliente o la plataforma…' : 'Escribe tu pregunta a Astrea…'}
              maxLength={2000}
              disabled={mutation.isPending}
              aria-label="Pregunta para Astrea"
            />
            <button type="submit" disabled={mutation.isPending} aria-label="Enviar">
              <StarIcon size={16} />
            </button>
          </form>
        </div>
      )}
    </>
  );
}
