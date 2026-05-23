import { useCallback, useEffect, useRef, useState, lazy, Suspense, type PointerEvent } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import DailyIframe, { type DailyCall } from '@daily-co/daily-js';
import { api } from '../../lib/api';
import { useThemeStore } from '../../stores/themeStore';

const ConstellationPortal = lazy(() => import('../../components/3d/ConstellationPortal'));

// ── Types ─────────────────────────────────────────────────────────────────────
type SalaInfo = {
  url: string;
  room_url?: string;
  token: string;
  is_owner?: boolean;
  sala_creada: boolean;
  pago_completado: boolean;
  en_horario: boolean;
  recording_enabled?: boolean;
  requires_recording_consent?: boolean;
  grabacion_habilitada?: boolean;
  cita: {
    uuid: string;
    inicio_utc: string;
    fin_utc: string;
    tipo_consulta: { nombre: string; duracion_minutos: number } | null;
  };
};

type Phase =
  | 'loading'
  | 'pre-check-failed'
  | 'consent'
  | 'pre-sala'
  | 'in-call'
  | 'post-session';

// ── API ───────────────────────────────────────────────────────────────────────
const fetchSala = (uuid: string) =>
  api.get(`/me/citas/${uuid}/sala-video`).then((r) => (r.data as { data: SalaInfo }).data);

const postConsentimiento = (uuid: string) =>
  api.post(`/citas/${uuid}/sala/consentimiento`, {
    acepta_grabacion: true,
    version_documento: 'v1',
  });

const postIniciarGrabacion = (uuid: string) =>
  api.post(`/me/citas/${uuid}/sala-video/grabacion/iniciar`);

const postDetenerGrabacion = (uuid: string) =>
  api.post(`/me/citas/${uuid}/sala-video/grabacion/detener`);

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtDuration(s: number) {
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  return h > 0 ? `${h}:${m}:${sec}` : `${m}:${sec}`;
}

function getRemainingSeconds(finUtc: string) {
  return Math.max(0, Math.floor((new Date(finUtc).getTime() - Date.now()) / 1000));
}

// ── Pre-check failed screen ───────────────────────────────────────────────────
function PreCheckFailed({ reason }: { reason: string }) {
  return (
    <motion.div
      className="sala-gate"
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
    >
      <span className="sala-gate-icon">🌙</span>
      <h2>{reason}</h2>
      <Link className="btn-primary" to="/app/mis-consultas">Ir a mis consultas</Link>
    </motion.div>
  );
}

// ── Consent modal ─────────────────────────────────────────────────────────────
function ConsentModal({ onAccept, loading }: { onAccept: () => void; loading: boolean }) {
  const [checked, setChecked] = useState(false);
  return (
    <motion.div
      className="sala-gate"
      initial={{ opacity: 0, scale: 0.96 }}
      animate={{ opacity: 1, scale: 1 }}
    >
      <span className="sala-gate-icon">🎙</span>
      <h2>Consentimiento de grabación</h2>
      <p className="sala-gate-body">
        Esta sesión será grabada con fines de seguimiento y consulta personal.
        La grabación estará disponible en tu historial durante 90 días y solo
        tú tendrás acceso a ella.
      </p>
      <label className="consent-check">
        <input
          type="checkbox"
          checked={checked}
          onChange={(e) => setChecked(e.target.checked)}
        />
        <span>Acepto que esta sesión sea grabada</span>
      </label>
      <button
        className="btn-primary btn-shimmer"
        disabled={!checked || loading}
        onClick={onAccept}
      >
        {loading ? 'Guardando…' : 'Continuar →'}
      </button>
    </motion.div>
  );
}

// ── Pre-sala camera test ──────────────────────────────────────────────────────
function PreSala({
  onEnter,
  servicioNombre,
  isOwner,
}: {
  onEnter: () => void;
  servicioNombre: string;
  isOwner?: boolean;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [camOk, setCamOk] = useState<boolean | null>(null);
  const [micOk, setMicOk] = useState<boolean | null>(null);
  const [motionPaused, setMotionPaused] = useState(false);
  const theme = useThemeStore((state) => state.theme);
  const toggleTheme = useThemeStore((state) => state.toggleTheme);
  const streamRef = useRef<MediaStream | null>(null);
  const stageRef = useRef<HTMLDivElement>(null);
  const deckRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    navigator.mediaDevices
      .getUserMedia({ video: true, audio: true })
      .then((stream) => {
        streamRef.current = stream;
        if (videoRef.current) videoRef.current.srcObject = stream;
        setCamOk(true);
        setMicOk(true);
      })
      .catch(() => {
        setCamOk(false);
        setMicOk(false);
      });

    return () => {
      streamRef.current?.getTracks().forEach((t) => t.stop());
    };
  }, []);

  const handlePointerMove = (event: PointerEvent<HTMLDivElement>) => {
    const stage = stageRef.current;
    const deck = deckRef.current;
    if (!stage) return;

    const rect = stage.getBoundingClientRect();
    const x = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
    const y = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));
    stage.style.setProperty('--mx', `${Math.round(x)}%`);
    stage.style.setProperty('--my', `${Math.round(y)}%`);

    if (deck && !motionPaused) {
      const tiltX = ((x / 100) - 0.5) * 10;
      const tiltY = ((y / 100) - 0.5) * -8;
      deck.style.setProperty('--tilt-x', `${tiltX.toFixed(2)}deg`);
      deck.style.setProperty('--tilt-y', `${tiltY.toFixed(2)}deg`);
    }
  };

  const waitingText = isOwner ? 'Esperando al cliente' : 'Esperando a la especialista';

  return (
    <motion.div
      className={`sala-presala immersive-presala ${motionPaused ? 'motion-paused' : ''}`}
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      ref={stageRef}
      onPointerMove={handlePointerMove}
    >
      <div className="immersive-presala-bg" aria-hidden="true">
        <span className="immersive-constellation" />
        <span className="immersive-orbit" />
        <span className="immersive-orbit small" />
        <span className="immersive-comet one" />
        <span className="immersive-comet two" />
      </div>

      <div className="presala-top-actions">
        <button
          type="button"
          className="presala-motion-toggle"
          aria-pressed={motionPaused}
          onClick={() => setMotionPaused((value) => !value)}
        >
          {motionPaused ? 'Reanudar movimiento' : 'Pausar movimiento'}
        </button>
        <button
          type="button"
          className="presala-theme-toggle"
          aria-pressed={theme === 'light'}
          onClick={toggleTheme}
        >
          {theme === 'dark' ? 'Modo claro' : 'Modo oscuro'}
        </button>
      </div>

      <p className="sr-only" aria-live="polite">
        Sala lista. {waitingText}. Cámara {camOk ? 'lista' : camOk === false ? 'sin permiso' : 'verificándose'}.
        Micrófono {micOk ? 'listo' : micOk === false ? 'sin permiso' : 'verificándose'}.
      </p>

      <div className="presala-brand" aria-label="TarotEstrellas">
        <span className="presala-brand-mark" aria-hidden="true">✦</span>
        <span>TarotEstrellas</span>
      </div>

      <div className="presala-copy">
        <p className="dash-eyebrow">✦ Verificación de dispositivos</p>
        <h2 className="sala-presala-title">Tu sala está lista</h2>
        <p className="sala-gate-body">
          {servicioNombre}. La consulta comenzará cuando ambos participantes estén presentes.
        </p>
      </div>

      <div className="presala-main-panel">
        <div className="presala-deck-scene" ref={deckRef} aria-hidden="true">
          <div className="presala-portal" />
          <div className="presala-deck-card left" data-glyph="☽" />
          <div className="presala-deck-card right" data-glyph="☉" />
          <div className="presala-tarot-card">
            <div className="presala-rune-ring" />
            <div className="presala-star">✦</div>
            <div className="presala-frame-symbol">☽</div>
            <div className="presala-frame-symbol">♄</div>
            <div className="presala-frame-symbol">✧</div>
            <div className="presala-frame-symbol">☉</div>
          </div>
        </div>

        <div className="presala-preview">
          <video ref={videoRef} autoPlay muted playsInline className="presala-video" />
          <div className="presala-checks">
            <div className={`presala-check ${camOk === null ? '' : camOk ? 'ok' : 'fail'}`}>
              <span className="presala-dot" aria-hidden="true" /> Cámara lista
            </div>
            <div className={`presala-check ${micOk === null ? '' : micOk ? 'ok' : 'fail'}`}>
              <span className="presala-dot" aria-hidden="true" /> Micrófono listo
            </div>
            <div className="presala-check pending">
              <span className="presala-dot" aria-hidden="true" /> {waitingText}
            </div>
          </div>
        </div>
      </div>

      {camOk === false && (
        <p className="form-warning">
          No se pudo acceder a tu cámara o micrófono. Revisa los permisos del
          navegador e intenta de nuevo.
        </p>
      )}

      <button
        className="btn-primary btn-shimmer presala-enter"
        onClick={onEnter}
        disabled={camOk === null}
      >
        Entrar a la sala
      </button>
    </motion.div>
  );
}

// ── In-call room ──────────────────────────────────────────────────────────────
function CallRoom({
  sala,
  onLeave,
}: {
  sala: SalaInfo;
  onLeave: () => void;
}) {
  const containerRef = useRef<HTMLDivElement>(null);
  const callRef = useRef<DailyCall | null>(null);
  const [muted, setMuted] = useState(false);
  const [camOff, setCamOff] = useState(false);
  const [recording, setRecording] = useState(false);
  const [elapsed, setElapsed] = useState(0);
  const [remaining, setRemaining] = useState(() =>
    getRemainingSeconds(sala.cita.fin_utc),
  );
  const [error, setError] = useState('');
  const [recordingLoading, setRecordingLoading] = useState(false);

  // Tick
  useEffect(() => {
    const id = setInterval(() => {
      setElapsed((s) => s + 1);
      setRemaining(getRemainingSeconds(sala.cita.fin_utc));
    }, 1000);
    return () => clearInterval(id);
  }, [sala.cita.fin_utc]);

  // Daily init
  useEffect(() => {
    if (!containerRef.current) return;

    const call = DailyIframe.createFrame(containerRef.current, {
      iframeStyle: {
        width: '100%',
        height: '100%',
        border: 'none',
        borderRadius: '0',
      },
      showLeaveButton: false,
      showFullscreenButton: false,
    });

    callRef.current = call;

    call
      .on('recording-started', () => setRecording(true))
      .on('recording-stopped', () => setRecording(false))
      .on('error', (e) => setError(e?.errorMsg ?? 'Error en la videollamada.'))
      .on('left-meeting', onLeave);

    call
      .join({ url: sala.url || sala.room_url, token: sala.token })
      .catch(() => setError('No se pudo unir a la sala. Intenta recargar la página.'));

    return () => {
      call.destroy();
    };
  }, [sala.url, sala.room_url, sala.token, onLeave]);

  const toggleMic = () => {
    callRef.current?.setLocalAudio(muted);
    setMuted((m) => !m);
  };

  const toggleCam = () => {
    callRef.current?.setLocalVideo(camOff);
    setCamOff((c) => !c);
  };

  const leave = () => {
    callRef.current?.leave();
  };

  const toggleRecording = async () => {
    setRecordingLoading(true);
    setError('');
    try {
      if (recording) {
        await postDetenerGrabacion(sala.cita.uuid);
        setRecording(false);
      } else {
        await postIniciarGrabacion(sala.cita.uuid);
        setRecording(true);
      }
    } catch (e: any) {
      setError(e?.response?.data?.message ?? 'No se pudo actualizar la grabación.');
    } finally {
      setRecordingLoading(false);
    }
  };

  return (
    <div className="call-room">
      {/* Status bar */}
      <div className="call-statusbar">
        <div className="call-status-left">
          {recording && (
            <span className="rec-badge">
              <span className="rec-dot" />
              Grabando
            </span>
          )}
          <span className="call-timer">{fmtDuration(elapsed)}</span>
        </div>
        <span className="call-service">{sala.cita.tipo_consulta?.nombre}</span>
        <span className={`call-remaining ${remaining < 300 ? 'urgent' : ''}`}>
          {fmtDuration(remaining)} restantes
        </span>
      </div>

      {/* Daily iframe container */}
      <div ref={containerRef} className="call-frame" />

      {error && <p className="call-error">{error}</p>}

      {/* Controls */}
      <div className="call-controls">
        <button
          type="button"
          className={`ctrl-btn ${muted ? 'ctrl-off' : ''}`}
          onClick={toggleMic}
          aria-label={muted ? 'Activar micrófono' : 'Silenciar'}
        >
          {muted ? '🔇' : '🎙'}
          <span>{muted ? 'Activar mic' : 'Silenciar'}</span>
        </button>

        <button
          type="button"
          className={`ctrl-btn ${camOff ? 'ctrl-off' : ''}`}
          onClick={toggleCam}
          aria-label={camOff ? 'Activar cámara' : 'Apagar cámara'}
        >
          {camOff ? '📷' : '🎥'}
          <span>{camOff ? 'Activar cam' : 'Apagar cam'}</span>
        </button>

        {sala.is_owner && (sala.recording_enabled || sala.grabacion_habilitada) ? (
          <button
            type="button"
            className={`ctrl-btn ${recording ? 'ctrl-off' : ''}`}
            onClick={toggleRecording}
            disabled={recordingLoading}
            aria-label={recording ? 'Detener grabación' : 'Iniciar grabación'}
          >
            {recording ? '■' : '●'}
            <span>{recordingLoading ? 'Procesando' : recording ? 'Detener grab.' : 'Grabar'}</span>
          </button>
        ) : null}

        <button
          type="button"
          className="ctrl-btn ctrl-leave"
          onClick={leave}
          aria-label="Salir de la sala"
        >
          📵
          <span>Salir</span>
        </button>
      </div>
    </div>
  );
}

// ── Post-session screen ───────────────────────────────────────────────────────
function PostSession() {
  return (
    <motion.div
      className="sala-gate"
      initial={{ opacity: 0, scale: 0.96 }}
      animate={{ opacity: 1, scale: 1 }}
    >
      <motion.span
        className="sala-gate-icon"
        animate={{ rotate: [0, 12, -12, 0] }}
        transition={{ duration: 3, repeat: Infinity }}
      >
        ✦
      </motion.span>
      <h2>Sesión finalizada</h2>
      <p className="sala-gate-body">
        Tu grabación y transcripción estarán disponibles en tu historial en
        unos minutos. Te notificaremos por correo cuando estén listas.
      </p>
      <div className="wizard-actions">
        <Link className="btn-secondary" to="/app/mis-consultas">Ver mis consultas</Link>
        <Link className="btn-primary btn-shimmer" to="/servicios">Agendar otra sesión</Link>
      </div>
    </motion.div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────
export function SalaVideoPage() {
  const { uuid = '' } = useParams<{ uuid: string }>();
  const [phase, setPhase] = useState<Phase>('loading');
  const [preCheckReason, setPreCheckReason] = useState('');

  const { data: sala, isError } = useQuery({
    queryKey: ['sala', uuid],
    queryFn: () => fetchSala(uuid),
    enabled: Boolean(uuid),
    retry: false,
  });

  const consentMutation = useMutation({
    mutationFn: () => postConsentimiento(uuid),
    onSuccess: () => setPhase('pre-sala'),
  });

  useEffect(() => {
    document.title = 'Sala de consulta | TarotEstrellas';
  }, []);

  useEffect(() => {
    if (!sala) return;

    if (!sala.pago_completado) {
      setPreCheckReason('Esta consulta aún no tiene el pago completado.');
      setPhase('pre-check-failed');
    } else if (!sala.sala_creada) {
      setPreCheckReason('La sala aún no está lista. Vuelve unos minutos antes del horario de tu consulta.');
      setPhase('pre-check-failed');
    } else if (!sala.en_horario) {
      setPreCheckReason('La sala solo está disponible durante el horario de tu consulta.');
      setPhase('pre-check-failed');
    } else if (sala.requires_recording_consent) {
      setPhase('consent');
    } else {
      setPhase('pre-sala');
    }
  }, [sala]);

  useEffect(() => {
    if (isError) {
      setPreCheckReason('No se encontró la sala. Verifica el enlace o contacta soporte.');
      setPhase('pre-check-failed');
    }
  }, [isError]);

  const handleLeave = useCallback(() => setPhase('post-session'), []);

  return (
    <main className="sala-page">
      <AnimatePresence mode="wait">
        {phase === 'loading' && (
          <motion.div key="loading" className="sala-gate" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <Suspense fallback={<span className="sala-gate-icon">🔮</span>}>
              <ConstellationPortal />
            </Suspense>
            <p>Preparando tu sala…</p>
          </motion.div>
        )}

        {phase === 'pre-check-failed' && (
          <motion.div key="failed" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <PreCheckFailed reason={preCheckReason} />
          </motion.div>
        )}

        {phase === 'consent' && sala && (
          <motion.div key="consent" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <ConsentModal
              onAccept={() => consentMutation.mutate()}
              loading={consentMutation.isPending}
            />
          </motion.div>
        )}

        {phase === 'pre-sala' && sala && (
          <motion.div key="presala" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <PreSala
              servicioNombre={sala.cita.tipo_consulta?.nombre ?? 'Consulta'}
              isOwner={sala.is_owner}
              onEnter={() => setPhase('in-call')}
            />
          </motion.div>
        )}

        {phase === 'in-call' && sala && (
          <motion.div key="call" className="call-wrapper" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <CallRoom sala={sala} onLeave={handleLeave} />
          </motion.div>
        )}

        {phase === 'post-session' && (
          <motion.div key="post" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <PostSession />
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
