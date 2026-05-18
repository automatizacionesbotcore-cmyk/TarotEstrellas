import { AnimatePresence, motion } from 'framer-motion';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { DefaultSpecialistAvatar } from '../../components/common/DefaultSpecialistAvatar';
import { useAuthStore } from '../../stores/authStore';
import { useAuthModalStore } from '../../stores/authModalStore';
import { MonthCalendar } from '../../components/ui/MonthCalendar';

type EspecialistaPublico = {
  id: number;
  uuid: string;
  slug: string;
  nombre: string;
  especialidad: string | null;
  biografia: string | null;
  avatar_url: string | null;
};

type ServicioDetalle = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  requiere_datos_natales: boolean;
  duracion_minutos: number;
  precio_referencial_centavos: number | null;
  moneda: string;
  color_hex: string | null;
};

type ServicioResponse = {
  data: ServicioDetalle;
};

type QuickAvailabilityResponse = {
  data: {
    timezone_especialista: string;
    slots: Array<{ inicio_utc: string; fin_utc: string }>;
  };
};

type AvailabilityResponse = {
  data: Array<{
    inicio_utc: string;
    fin_utc: string;
    inicio_local: string;
    fin_local: string;
    zona_horaria: string;
  }>;
};

type CitaResponse = {
  data: {
    id: number;
    uuid: string;
    estado: string;
    inicio_utc: string;
    fin_utc: string;
    reservada_hasta: string;
    precio_total_centavos: number;
    precio_final_centavos: number;
    moneda: string;
  };
};

async function fetchDetalle(slug: string): Promise<ServicioResponse> {
  const response = await api.get(`/public/tipos-consulta/${slug}`);
  return response.data as ServicioResponse;
}

async function fetchQuickAvailability(slug: string): Promise<QuickAvailabilityResponse> {
  const response = await api.get(`/public/tipos-consulta/${slug}/disponibilidad-rapida`);
  return response.data as QuickAvailabilityResponse;
}


async function fetchEspecialistas(): Promise<EspecialistaPublico[]> {
  const r = await api.get<{ data: EspecialistaPublico[] }>('/public/especialistas');
  return r.data.data;
}

async function reservarCita(payload: {
  tipo_consulta_slug: string;
  inicio_local: string;
  zona_horaria_cliente: string;
  canal_pago: 'stripe' | 'transferencia';
  especialista_id?: number;
  tema_principal?: string;
  notas_cliente?: string;
  grabacion_solicitada?: boolean;
}): Promise<CitaResponse> {
  const response = await api.post('/citas', payload);
  return response.data as CitaResponse;
}

function getLocalDateYmd() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60_000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

function formatPrice(value: number | null, currency: string) {
  if (value === null) {
    return 'Consultar precio';
  }

  const amount = value / 100;

  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(amount);
}

function getRemainingSeconds(expiresAtIso: string | undefined, nowMs: number) {
  if (!expiresAtIso) {
    return null;
  }

  const expiresAtMs = new Date(expiresAtIso).getTime();
  if (!Number.isFinite(expiresAtMs)) {
    return null;
  }

  return Math.max(0, Math.floor((expiresAtMs - nowMs) / 1000));
}

function formatCountdown(totalSeconds: number | null) {
  if (totalSeconds === null) {
    return '--:--';
  }

  const minutes = Math.floor(totalSeconds / 60)
    .toString()
    .padStart(2, '0');
  const seconds = (totalSeconds % 60).toString().padStart(2, '0');
  return `${minutes}:${seconds}`;
}

const stepVariants = {
  enter: { opacity: 0, x: 24 },
  center: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -24 },
};

export function ServicioDetallePage() {
  const navigate = useNavigate();
  const location = useLocation();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const openRegister = useAuthModalStore((s) => s.openRegister);
  const bookingRef = useRef<HTMLElement>(null);
  const detectedTimezone = useMemo(() => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC', []);

  const [currentStep, setCurrentStep] = useState<1 | 2 | 3 | 4>(1);
  const [selectedTimezone, setSelectedTimezone] = useState(detectedTimezone);
  const [agendaDate, setAgendaDate] = useState(getLocalDateYmd());
  const [selectedSlot, setSelectedSlot] = useState<string>('');
  const [temaPrincipal, setTemaPrincipal] = useState('');
  const [pregunta, setPregunta] = useState('');
  const [grabarSesion, setGrabarSesion] = useState(false);
  const [selectedEspecialistaId, setSelectedEspecialistaId] = useState<number | null>(null);
  const [canalPago, setCanalPago] = useState<'stripe' | 'transferencia'>('stripe');
  const [remainingSeconds, setRemainingSeconds] = useState<number | null>(null);

  const { slug = '' } = useParams();
  const { data, isLoading } = useQuery({
    queryKey: ['tipo-consulta', slug],
    queryFn: () => fetchDetalle(slug),
    enabled: Boolean(slug),
  });
  const { data: quickAvailability } = useQuery({
    queryKey: ['tipo-consulta-disponibilidad-rapida', slug],
    queryFn: () => fetchQuickAvailability(slug),
    enabled: Boolean(slug),
  });
  const { data: especialistas = [] } = useQuery<EspecialistaPublico[]>({
    queryKey: ['public', 'especialistas'],
    queryFn: fetchEspecialistas,
    staleTime: 5 * 60 * 1000,
  });

  const multiEspecialista = especialistas.length > 1;

  useEffect(() => {
    if (especialistas.length === 1 && selectedEspecialistaId === null) {
      setSelectedEspecialistaId(especialistas[0].id);
    }
  }, [especialistas]);

  const servicio = data?.data;

  const timezoneOptions = useMemo(() => {
    const commonTimezones = [
      'America/Santiago',
      'America/Bogota',
      'America/Lima',
      'America/Buenos_Aires',
      'America/Mexico_City',
      'America/New_York',
      'Europe/Madrid',
      'UTC',
    ];

    return Array.from(new Set([detectedTimezone, ...commonTimezones]));
  }, [detectedTimezone]);

  const availabilityQuery = useQuery({
    queryKey: ['disponibilidad-dia', servicio?.slug, agendaDate, selectedTimezone, selectedEspecialistaId],
    queryFn: async () => {
      const r = await api.get('/disponibilidad', {
        params: {
          tipo_consulta_slug: servicio!.slug,
          date: agendaDate,
          tz: selectedTimezone,
          ...(selectedEspecialistaId ? { especialista_id: selectedEspecialistaId } : {}),
        },
      });
      return r.data as AvailabilityResponse;
    },
    enabled: Boolean(servicio?.slug) && Boolean(agendaDate),
  });

  const bookingMutation = useMutation({
    mutationFn: reservarCita,
    onSuccess: () => {
      setCurrentStep(4);
    },
  });

  useEffect(() => {
    if (!servicio) {
      return;
    }

    const title = `${servicio.nombre} | TarotEstrellas`;
    const description = `${servicio.descripcion} Agenda tu sesión de ${servicio.duracion_minutos} minutos en TarotEstrellas.`;

    document.title = title;

    let metaDescription = document.querySelector('meta[name="description"]');
    if (!metaDescription) {
      metaDescription = document.createElement('meta');
      metaDescription.setAttribute('name', 'description');
      document.head.appendChild(metaDescription);
    }
    metaDescription.setAttribute('content', description);

    let ogTitle = document.querySelector('meta[property="og:title"]');
    if (!ogTitle) {
      ogTitle = document.createElement('meta');
      ogTitle.setAttribute('property', 'og:title');
      document.head.appendChild(ogTitle);
    }
    ogTitle.setAttribute('content', title);

    let ogDescription = document.querySelector('meta[property="og:description"]');
    if (!ogDescription) {
      ogDescription = document.createElement('meta');
      ogDescription.setAttribute('property', 'og:description');
      document.head.appendChild(ogDescription);
    }
    ogDescription.setAttribute('content', description);
  }, [servicio]);

  const quickSlots = quickAvailability?.data.slots ?? [];

  const slots = availabilityQuery.data?.data ?? [];

  useEffect(() => {
    setSelectedSlot('');
    if (currentStep < 4) {
      setCurrentStep(1);
    }
  }, [agendaDate, servicio?.id, selectedTimezone]);

  useEffect(() => {
    if (!selectedTimezone) {
      setSelectedTimezone(detectedTimezone);
    }
  }, [detectedTimezone, selectedTimezone]);

  const selectedSlotData = useMemo(
    () => slots.find((slot) => slot.inicio_utc === selectedSlot) ?? null,
    [selectedSlot, slots],
  );

  const reservadaHasta = bookingMutation.data?.data.reservada_hasta;

  const countdownLabel = useMemo(() => formatCountdown(remainingSeconds), [remainingSeconds]);

  const isCountdownExpired = remainingSeconds !== null && remainingSeconds <= 0;

  const stepHint = useMemo(() => {
    if (currentStep === 1) {
      if (multiEspecialista && !selectedEspecialistaId) {
        return 'Paso 1 de 4: elige tu especialista';
      }
      return 'Paso 1 de 4: elige fecha y horario';
    }

    if (currentStep === 2) {
      return 'Paso 2 de 4: cuéntanos sobre tu consulta';
    }

    if (currentStep === 3) {
      return 'Paso 3 de 4: revisa el resumen antes de reservar';
    }

    return 'Paso 4 de 4: reserva creada';
  }, [currentStep, multiEspecialista, selectedEspecialistaId]);

  const goNext = () => {
    if (currentStep === 1 && !selectedSlot) {
      return;
    }

    if (currentStep === 2 || currentStep === 1) {
      setCurrentStep((prev) => (prev === 1 ? 2 : 3));
    }
  };

  const goBack = () => {
    if (currentStep === 2) {
      setCurrentStep(1);
      return;
    }

    if (currentStep === 3) {
      setCurrentStep(2);
    }
  };

  const handleReservar = async () => {
    if (!servicio || !selectedSlot) {
      return;
    }

    if (!isAuthenticated) {
      navigate('/auth/login', {
        state: { from: location.pathname },
      });
      return;
    }

    await bookingMutation.mutateAsync({
      tipo_consulta_slug: servicio.slug,
      inicio_local: selectedSlotData!.inicio_local,
      zona_horaria_cliente: selectedTimezone || detectedTimezone,
      canal_pago: canalPago,
      especialista_id: selectedEspecialistaId ?? undefined,
      tema_principal: temaPrincipal || undefined,
      notas_cliente: pregunta || undefined,
      grabacion_solicitada: grabarSesion,
    });
  };

  useEffect(() => {
    if (!bookingMutation.isSuccess || currentStep !== 4) {
      setRemainingSeconds(null);
      return;
    }

    const initialSeconds = getRemainingSeconds(reservadaHasta, Date.now());
    setRemainingSeconds(initialSeconds);

    if (initialSeconds === null || initialSeconds <= 0) {
      return;
    }

    const intervalId = window.setInterval(() => {
      setRemainingSeconds((prev) => {
        const next = getRemainingSeconds(reservadaHasta, Date.now());
        if (next === null) {
          return prev;
        }

        if (next <= 0) {
          window.clearInterval(intervalId);
          return 0;
        }

        return next;
      });
    }, 1000);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [bookingMutation.isSuccess, currentStep, reservadaHasta]);

  return (
    <main className="page-content service-detail-page">
      {isLoading ? <p>Cargando detalle...</p> : null}
      {servicio ? (
        <>
          <motion.div
            initial={{ opacity: 0, y: 22 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            style={{ marginBottom: '1.2rem' }}
          >
            <p className="dash-eyebrow">Detalle del servicio</p>
            <h1 className="dash-title">{servicio.nombre}</h1>
          </motion.div>

          <motion.section className="detail-card service-detail-card" initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }} transition={{ delay: 0.1 }}>
          <div className="detail-hero">
            <div>
              <p>{servicio.descripcion}</p>
              <div className="detail-meta-row service-detail-meta">
                <span><strong>{servicio.duracion_minutos}</strong> minutos</span>
                <span><strong>{formatPrice(servicio.precio_referencial_centavos, servicio.moneda)}</strong></span>
                <span>{servicio.requiere_datos_natales ? 'Requiere datos natales' : 'Sin datos natales'}</span>
              </div>
            </div>

            <aside className="quick-availability">
              <h2>Próximos horarios</h2>
              {quickSlots.length === 0 ? (
                <p>Sin horarios visibles por ahora.</p>
              ) : (
                <ul>
                  {quickSlots.map((slot) => (
                    <li key={slot.inicio_utc}>
                      {new Date(slot.inicio_utc).toLocaleString('es-CL', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                      })}
                    </li>
                  ))}
                </ul>
              )}
            </aside>
          </div>

          <div className="cta-row">
            {isAuthenticated ? (
              <button
                type="button"
                className="btn-primary btn-shimmer"
                onClick={() => bookingRef.current?.scrollIntoView({ behavior: 'smooth' })}
              >
                Agendar ahora
              </button>
            ) : (
              <button type="button" className="btn-primary btn-shimmer" onClick={openRegister}>
                Crear cuenta y agendar
              </button>
            )}
            <Link className="btn-secondary" to="/servicios">
              Volver al catálogo
            </Link>
          </div>

          <section className="booking-panel booking-panel--premium" aria-label="Panel de agendamiento" ref={bookingRef}>
            <div className="booking-panel-header">
              <div>
                <p className="dash-eyebrow">Reserva</p>
                <h2>Agendar esta consulta</h2>
              </div>
              <p className="wizard-step-hint">{stepHint}</p>
            </div>

            <ol className="wizard-steps" aria-label="Progreso de agendamiento">
              <li className={currentStep >= 1 ? 'active' : ''}>{multiEspecialista ? 'Especialista y hora' : 'Fecha y hora'}</li>
              <li className={currentStep >= 2 ? 'active' : ''}>Información</li>
              <li className={currentStep >= 3 ? 'active' : ''}>Resumen</li>
              <li className={currentStep >= 4 ? 'active' : ''}>Confirmación</li>
            </ol>

            <AnimatePresence mode="wait" initial={false}>
            {currentStep === 1 ? (
              <motion.div
                key="step-1"
                variants={stepVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.25 }}
              >
                {multiEspecialista && !selectedEspecialistaId ? (
                  /* ── Selector de especialista ── */
                  <div>
                    <p className="booking-step-intro">Elige con quién quieres hacer tu consulta.</p>
                    <div className="especialista-cards">
                      {especialistas.map((e) => (
                        <button
                          key={e.id}
                          type="button"
                          className="especialista-card"
                          onClick={() => setSelectedEspecialistaId(e.id)}
                        >
                          {e.avatar_url ? (
                            <img className="especialista-card-avatar" src={e.avatar_url} alt={e.nombre} />
                          ) : (
                            <DefaultSpecialistAvatar name={e.nombre} className="especialista-card-avatar" />
                          )}
                          <strong className="especialista-card-nombre">{e.nombre}</strong>
                          {e.especialidad && (
                            <span className="especialista-card-especialidad">{e.especialidad}</span>
                          )}
                          {e.biografia && (
                            <p className="especialista-card-bio">{e.biografia}</p>
                          )}
                        </button>
                      ))}
                    </div>
                  </div>
                ) : (
                  /* ── Calendario y slots ── */
                  <div>
                    {multiEspecialista && selectedEspecialistaId && (
                      <div style={{ marginBottom: '0.75rem', display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                        <button
                          type="button"
                          className="btn-secondary"
                          style={{ fontSize: '0.82rem', padding: '0.3rem 0.75rem' }}
                          onClick={() => { setSelectedEspecialistaId(null); setSelectedSlot(''); }}
                        >
                          Cambiar especialista
                        </button>
                        <span style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
                          {especialistas.find((e) => e.id === selectedEspecialistaId)?.nombre}
                        </span>
                      </div>
                    )}
                    <div className="booking-step1-layout">
                      <div className="booking-step1-calendar">
                        <MonthCalendar
                          value={agendaDate}
                          min={getLocalDateYmd()}
                          onChange={setAgendaDate}
                        />
                      </div>

                      <div className="booking-step1-slots">
                        <label>
                          Zona horaria
                          <select
                            value={selectedTimezone}
                            onChange={(event) => setSelectedTimezone(event.target.value || detectedTimezone)}
                          >
                            {timezoneOptions.map((timezone) => (
                              <option key={timezone} value={timezone}>
                                {timezone}
                                {timezone === detectedTimezone ? ' (detectada)' : ''}
                              </option>
                            ))}
                          </select>
                        </label>

                        <div className="slots-grid">
                          {availabilityQuery.isLoading ? <p>Cargando horarios del día...</p> : null}
                          {!availabilityQuery.isLoading && slots.length === 0 ? (
                            <p>No hay horarios disponibles para la fecha seleccionada.</p>
                          ) : null}

                          {slots.map((slot) => {
                            const isSelected = selectedSlot === slot.inicio_utc;
                            return (
                              <button
                                key={slot.inicio_utc}
                                type="button"
                                className={isSelected ? 'slot-chip selected' : 'slot-chip'}
                                onClick={() => setSelectedSlot(slot.inicio_utc)}
                              >
                                {new Date(slot.inicio_local).toLocaleTimeString('es-CL', {
                                  hour: '2-digit',
                                  minute: '2-digit',
                                })}
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    </div>

                    <div className="wizard-actions">
                      <button className="btn-primary" type="button" disabled={!selectedSlot} onClick={goNext}>
                        Continuar
                      </button>
                    </div>
                  </div>
                )}
              </motion.div>
            ) : null}

            {currentStep === 2 ? (
              <motion.div
                key="step-2"
                className="booking-step-content"
                variants={stepVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.25 }}
              >
                <p className="booking-step-intro">Cuéntanos sobre tu consulta para que la especialista pueda preparar mejor tu sesión.</p>

                <label>
                  <span className="booking-field-label">Tema principal <span className="booking-optional">(opcional)</span></span>
                  <input
                    type="text"
                    maxLength={80}
                    value={temaPrincipal}
                    onChange={(event) => setTemaPrincipal(event.target.value)}
                    placeholder="Amor, trabajo, familia..."
                  />
                </label>

                <label>
                  <span className="booking-field-label">Pregunta específica <span className="booking-optional">(opcional)</span></span>
                  <textarea
                    rows={4}
                    maxLength={1000}
                    value={pregunta}
                    onChange={(event) => setPregunta(event.target.value)}
                    placeholder="Describe brevemente lo que te gustaría explorar en la sesión..."
                  />
                </label>

                <div className="booking-field-group">
                  <span className="booking-field-label">Método de pago del abono</span>
                  <div className="canal-pago-options">
                    <label className={`canal-pago-option${canalPago === 'stripe' ? ' selected' : ''}`}>
                      <input
                        type="radio"
                        name="canal_pago"
                        value="stripe"
                        checked={canalPago === 'stripe'}
                        onChange={() => setCanalPago('stripe')}
                      />
                      <span>Tarjeta de crédito / débito</span>
                    </label>
                    <label className={`canal-pago-option${canalPago === 'transferencia' ? ' selected' : ''}`}>
                      <input
                        type="radio"
                        name="canal_pago"
                        value="transferencia"
                        checked={canalPago === 'transferencia'}
                        onChange={() => setCanalPago('transferencia')}
                      />
                      <span>Transferencia bancaria (Chile)</span>
                    </label>
                  </div>
                </div>

                <div className="wizard-actions">
                  <button className="btn-secondary" type="button" onClick={goBack}>
                    Volver
                  </button>
                  <button className="btn-primary" type="button" onClick={goNext}>
                    Revisar resumen
                  </button>
                </div>
              </motion.div>
            ) : null}

            {currentStep === 3 ? (
              <motion.div
                key="step-3"
                className="booking-step-content"
                variants={stepVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.25 }}
              >
                <p className="booking-step-intro">Verifica los datos de tu reserva antes de confirmar.</p>

                <div className="booking-summary">
                  <p><strong>Servicio</strong> {servicio?.nombre}</p>
                  <p>
                    <strong>Fecha y hora</strong>
                    {selectedSlotData
                      ? new Date(selectedSlotData.inicio_local).toLocaleString('es-CL', {
                          dateStyle: 'full',
                          timeStyle: 'short',
                        })
                      : 'Sin seleccionar'}
                  </p>
                  <p><strong>Zona horaria</strong> {selectedTimezone || detectedTimezone}</p>
                  <p><strong>Duración</strong> {servicio?.duracion_minutos} minutos</p>
                  <p><strong>Precio total</strong> {formatPrice(servicio?.precio_referencial_centavos ?? null, servicio?.moneda ?? 'CLP')}</p>
                  <p><strong>Método de pago</strong> {canalPago === 'stripe' ? 'Tarjeta de crédito / débito' : 'Transferencia bancaria'}</p>
                  <p><strong>Tema</strong> {temaPrincipal || '—'}</p>
                  {pregunta && <p className="booking-summary-full"><strong>Pregunta</strong> {pregunta}</p>}
                </div>

                <label className="consent-check" style={{ marginTop: '1rem', display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}>
                  <input
                    type="checkbox"
                    checked={grabarSesion}
                    onChange={(e) => setGrabarSesion(e.target.checked)}
                  />
                  <span>
                    <strong>Grabar mi sesión (opcional)</strong>
                    <br />
                    <small>
                      Recibirás el video después de la sesión. Costo adicional según duración:
                      30 min $2.500 / 60 min $3.990 / 90 min $5.990 CLP. La grabación estará
                      disponible 90 días en tu historial. La transcripción IA está incluida sin costo.
                    </small>
                  </span>
                </label>

                {!isAuthenticated && (
                  <p className="form-warning">Debes iniciar sesión para confirmar la reserva.</p>
                )}

                {bookingMutation.isError && (
                  <p className="form-error">No se pudo reservar el horario. Intenta con otro slot.</p>
                )}

                <div className="wizard-actions">
                  <button className="btn-secondary" type="button" onClick={goBack}>Volver</button>
                  <button
                    className="btn-primary btn-shimmer"
                    type="button"
                    disabled={!selectedSlot || bookingMutation.isPending}
                    onClick={handleReservar}
                  >
                    {bookingMutation.isPending ? 'Reservando...' : 'Confirmar reserva'}
                  </button>
                </div>
              </motion.div>
            ) : null}

            {currentStep === 4 && bookingMutation.isSuccess ? (
              <motion.div
                key="step-4"
                variants={stepVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.25 }}
              >
                <div className="booking-success">
                  <h3>Reserva creada</h3>
                  <p>
                    Tu hora está reservada. Tienes hasta el{' '}
                    <strong>
                      {new Date(bookingMutation.data.data.reservada_hasta).toLocaleString('es-CL', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                      })}
                    </strong>{' '}
                    para completar el pago y confirmar tu cita.
                  </p>
                  <div className="countdown-box" role="status" aria-live="polite">
                    <span className="countdown-label">Tiempo restante para pagar</span>
                    <strong className={isCountdownExpired ? 'countdown-time expired' : 'countdown-time'}>
                      {countdownLabel}
                    </strong>
                    {isCountdownExpired ? (
                      <p className="countdown-expired">La ventana de reserva ha vencido. Puedes intentar con otro horario.</p>
                    ) : null}
                  </div>
                  <div className="wizard-actions">
                    <Link className="btn-secondary" to="/app/mis-consultas">
                      Ver mis consultas
                    </Link>
                    {!isCountdownExpired ? (
                      <Link
                        className="btn-primary btn-shimmer"
                        to={`/app/citas/${bookingMutation.data.data.uuid}/pagar`}
                      >
                        Ir a pagar
                      </Link>
                    ) : null}
                  </div>
                </div>
              </motion.div>
            ) : null}
            </AnimatePresence>
          </section>
        </motion.section>
        </>
      ) : null}
    </main>
  );
}
