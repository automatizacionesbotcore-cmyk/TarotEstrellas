import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { loadStripe, type StripeCardElement } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { api } from '../../lib/api';
import { generateIcs } from '../../lib/ics';

// ── Stripe init ─────────────────────────────────────────────────────────────
const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY ?? '');

// ── Types ────────────────────────────────────────────────────────────────────
type Cita = {
  id: number;
  uuid: string;
  estado: string;
  inicio_utc: string;
  fin_utc: string;
  reservada_hasta: string;
  precio_total_centavos: number;
  precio_final_centavos: number;
  moneda: string;
  codigo_referencia: string;
  primera_consulta: boolean;
  cupon_aplicado: string | null;
  tipo_consulta: { nombre: string; duracion_minutos: number } | null;
};

type MetodoPago = {
  id: number;
  tipo: 'transferencia';
  banco: string;
  titular: string;
  numero_cuenta: string;
  tipo_cuenta: string;
  rut?: string;
  email?: string;
};

type CuponValidacion = {
  valido: boolean;
  descripcion: string;
  descuento_centavos: number;
};

type Membresia = {
  activa: boolean;
  nombre: string | null;
  consultas_restantes: number;
  fecha_vencimiento: string | null;
};

type PaymentIntentResponse = { client_secret: string };
type MetodosPagoResponse  = { data: MetodoPago[] };

// ── API helpers ──────────────────────────────────────────────────────────────
const fetchCita       = (id: string) =>
  api.get(`/citas/${id}`).then((r) => (r.data as { data: Cita }).data);

const fetchMetodosPago = () =>
  api.get('/metodos-pago').then((r) => (r.data as MetodosPagoResponse).data);

const createPaymentIntent = (citaId: string) =>
  api.post(`/citas/${citaId}/pagar/stripe`).then((r) => r.data as PaymentIntentResponse);

const uploadComprobante = (citaId: string, file: File) => {
  const form = new FormData();
  form.append('comprobante', file);
  return api.post(`/citas/${citaId}/pagar/transferencia/comprobante`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};

const extenderReserva = (citaId: string) =>
  api.post(`/citas/${citaId}/extender-reserva`).then((r) => r.data as { reservada_hasta: string });

const validarCupon = (codigo: string, citaId: string) =>
  api.post('/cupones/validar', { codigo, cita_id: Number(citaId) })
     .then((r) => r.data as CuponValidacion);

const aplicarCupon = (citaId: string, codigo: string) =>
  api.post(`/citas/${citaId}/aplicar-cupon`, { codigo })
     .then((r) => (r.data as { data: Cita }).data);

const fetchMembresia = () =>
  api.get('/membresia').then((r) => (r.data as { data: Membresia }).data);

const aplicarMembresia = (citaId: string) =>
  api.post(`/citas/${citaId}/aplicar-membresia`);

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(cents / 100);
}

function getRemainingSeconds(iso: string) {
  return Math.max(0, Math.floor((new Date(iso).getTime() - Date.now()) / 1000));
}

function fmtCountdown(s: number) {
  const m   = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  return `${m}:${sec}`;
}

// ── Cupón section ────────────────────────────────────────────────────────────
function CuponSection({
  citaId,
  applied,
  onApplied,
}: {
  citaId: string;
  applied: string | null;
  onApplied: (cita: Cita) => void;
}) {
  const [codigo, setCodigo]   = useState(applied ?? '');
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');
  const [ok, setOk]           = useState<CuponValidacion | null>(null);

  if (applied && !ok) {
    return (
      <div className="cupon-applied">
        <span className="cupon-check">✓</span>
        <span>Cupón <strong>{applied}</strong> aplicado</span>
      </div>
    );
  }

  if (ok) {
    return (
      <div className="cupon-applied">
        <span className="cupon-check">✓</span>
        <span>Cupón <strong>{codigo}</strong>: {ok.descripcion}</span>
      </div>
    );
  }

  const handleApply = async () => {
    const trimmed = codigo.trim();
    if (!trimmed) return;
    setLoading(true);
    setError('');
    try {
      const validation = await validarCupon(trimmed, citaId);
      if (!validation.valido) {
        setError('El código no es válido o no aplica para esta consulta.');
        return;
      }
      const updatedCita = await aplicarCupon(citaId, trimmed);
      setOk(validation);
      onApplied(updatedCita);
    } catch {
      setError('No se pudo aplicar el cupón. Verifica el código e intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="cupon-section">
      <p className="pay-section-label">¿Tienes un cupón?</p>
      <div className="cupon-input-row">
        <input
          type="text"
          value={codigo}
          onChange={(e) => setCodigo(e.target.value.toUpperCase())}
          onKeyDown={(e) => e.key === 'Enter' && handleApply()}
          placeholder="CÓDIGO"
          className="cupon-input"
          maxLength={24}
        />
        <button
          type="button"
          className="btn-secondary cupon-btn"
          onClick={handleApply}
          disabled={loading || !codigo.trim()}
        >
          {loading ? '…' : 'Aplicar'}
        </button>
      </div>
      {error && <p className="form-error cupon-error">{error}</p>}
    </div>
  );
}

// ── Membresía section (checkout) ─────────────────────────────────────────────
function MembresiaCheckout({
  citaId,
  onUsed,
}: {
  citaId: string;
  onUsed: () => void;
}) {
  const { data, isLoading } = useQuery<Membresia>({
    queryKey: ['membresia'],
    queryFn:  fetchMembresia,
  });

  const mutation = useMutation({
    mutationFn: () => aplicarMembresia(citaId),
    onSuccess:  onUsed,
  });

  if (isLoading || !data?.activa || data.consultas_restantes <= 0) return null;

  return (
    <motion.div
      className="membresia-checkout"
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
    >
      <div className="membresia-checkout-info">
        <span className="membresia-star">✦</span>
        <div>
          <p className="membresia-nombre">{data.nombre ?? 'Membresía activa'}</p>
          <p className="membresia-restantes">{data.consultas_restantes} consulta{data.consultas_restantes !== 1 ? 's' : ''} restante{data.consultas_restantes !== 1 ? 's' : ''}</p>
        </div>
      </div>
      <button
        type="button"
        className="btn-primary btn-shimmer"
        onClick={() => mutation.mutate()}
        disabled={mutation.isPending}
      >
        {mutation.isPending ? 'Aplicando…' : 'Usar membresía'}
      </button>
      {mutation.isError && (
        <p className="form-error">No se pudo aplicar la membresía. Intenta de nuevo.</p>
      )}
    </motion.div>
  );
}

// ── Stripe card form ─────────────────────────────────────────────────────────
function StripeForm({
  clientSecret,
  cita,
  onSuccess,
}: {
  clientSecret: string;
  cita: Cita;
  onSuccess: () => void;
}) {
  const stripe   = useStripe();
  const elements = useElements();
  const [error, setError]   = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements) return;
    setLoading(true);
    setError('');

    const card = elements.getElement(CardElement) as StripeCardElement;
    const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
      payment_method: { card },
    });

    if (stripeError) {
      setError(stripeError.message ?? 'Error al procesar el pago.');
      setLoading(false);
    } else if (paymentIntent?.status === 'succeeded') {
      onSuccess();
    } else {
      setError('El pago no pudo completarse. Intenta nuevamente.');
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="stripe-form">
      <p className="pay-section-label">Datos de tu tarjeta</p>
      <div className="stripe-card-wrapper">
        <CardElement
          options={{
            style: {
              base: {
                color: '#f5e6d3',
                fontFamily: 'Georgia, serif',
                fontSize: '15px',
                '::placeholder': { color: 'rgba(245,230,211,0.4)' },
              },
              invalid: { color: '#ffb3b3' },
            },
          }}
        />
      </div>
      {error ? <p className="form-error">{error}</p> : null}
      <p className="pay-abono-note">
        Se cobrará el <strong>20% de abono</strong>:{' '}
        <strong>{formatMoney(cita.precio_final_centavos, cita.moneda)}</strong>
      </p>
      <button className="btn-primary btn-shimmer pay-submit" type="submit" disabled={loading || !stripe}>
        {loading ? 'Procesando…' : `Pagar ${formatMoney(cita.precio_final_centavos, cita.moneda)}`}
      </button>
    </form>
  );
}

// ── Transfer panel ───────────────────────────────────────────────────────────
function TransferPanel({ cita, metodos }: { cita: Cita; metodos: MetodoPago[] }) {
  const [seconds, setSeconds]     = useState(() => getRemainingSeconds(cita.reservada_hasta));
  const [extended, setExtended]   = useState(false);
  const [file, setFile]           = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [uploaded, setUploaded]   = useState(false);
  const [uploadError, setUploadError] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (seconds <= 0) return;
    const id = setInterval(() => setSeconds((s) => Math.max(0, s - 1)), 1000);
    return () => clearInterval(id);
  }, [seconds]);

  const handleExtend = async () => {
    try {
      const res = await extenderReserva(String(cita.id));
      setSeconds(getRemainingSeconds(res.reservada_hasta));
      setExtended(true);
    } catch { /* silencioso */ }
  };

  const handleUpload = async () => {
    if (!file) return;
    setUploading(true);
    setUploadError('');
    try {
      await uploadComprobante(String(cita.id), file);
      setUploaded(true);
    } catch {
      setUploadError('No se pudo subir el comprobante. Intenta de nuevo.');
    } finally {
      setUploading(false);
    }
  };

  const banco = metodos[0];

  return (
    <div className="transfer-panel">
      <div className="countdown-box" role="status" aria-live="polite">
        <span className="countdown-label">Tiempo para transferir</span>
        <strong className={seconds <= 0 ? 'countdown-time expired' : 'countdown-time'}>
          {fmtCountdown(seconds)}
        </strong>
        {seconds <= 300 && seconds > 0 && !extended ? (
          <button type="button" className="btn-secondary extend-btn" onClick={handleExtend}>
            Extender 10 min
          </button>
        ) : null}
        {seconds <= 0 ? (
          <p className="countdown-expired">El tiempo de reserva venció. Vuelve a agendar.</p>
        ) : null}
      </div>

      <div className="ref-code-box">
        <span className="pay-section-label">Código de referencia (incluirlo en la transferencia)</span>
        <strong className="ref-code">{cita.codigo_referencia}</strong>
      </div>

      {banco ? (
        <div className="bank-details">
          <p className="pay-section-label">Datos para transferir</p>
          <dl>
            <dt>Banco</dt><dd>{banco.banco}</dd>
            <dt>Titular</dt><dd>{banco.titular}</dd>
            <dt>N° de cuenta</dt><dd>{banco.numero_cuenta}</dd>
            <dt>Tipo de cuenta</dt><dd>{banco.tipo_cuenta}</dd>
            {banco.rut   ? <><dt>RUT</dt><dd>{banco.rut}</dd></>    : null}
            {banco.email ? <><dt>Email</dt><dd>{banco.email}</dd></> : null}
          </dl>
          <p className="pay-abono-note">
            Monto a transferir: <strong>{formatMoney(cita.precio_final_centavos, cita.moneda)}</strong>
          </p>
        </div>
      ) : (
        <p className="pay-section-label">Cargando datos bancarios…</p>
      )}

      {!uploaded ? (
        <div className="comprobante-upload">
          <p className="pay-section-label">Sube tu comprobante de transferencia</p>
          <input
            ref={fileRef}
            type="file"
            accept="image/*,application/pdf"
            style={{ display: 'none' }}
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          <button
            type="button"
            className="btn-secondary"
            onClick={() => fileRef.current?.click()}
          >
            {file ? `📎 ${file.name}` : 'Seleccionar archivo'}
          </button>
          {file ? (
            <button type="button" className="btn-primary" disabled={uploading} onClick={handleUpload}>
              {uploading ? 'Subiendo…' : 'Enviar comprobante'}
            </button>
          ) : null}
          {uploadError ? <p className="form-error">{uploadError}</p> : null}
        </div>
      ) : (
        <motion.div
          className="upload-success"
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <span>✦</span>
          <p>Comprobante recibido. Lo revisaremos y te confirmaremos por correo.</p>
        </motion.div>
      )}
    </div>
  );
}

// ── Success screen ───────────────────────────────────────────────────────────
function SuccessScreen({ cita, usedMembresia = false }: { cita: Cita; usedMembresia?: boolean }) {
  const STARS = [
    [20, 15], [80, 10], [50, 30], [10, 60], [90, 55],
    [35, 80], [65, 25], [15, 40], [75, 70], [45, 90],
  ] as [number, number][];

  return (
    <motion.div
      className="pay-success"
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.5 }}
    >
      {STARS.map(([l, t], i) => (
        <motion.span
          key={i}
          className="hero-star"
          style={{ left: `${l}%`, top: `${t}%`, width: 8, height: 8, position: 'absolute' }}
          animate={{ opacity: [0.3, 1, 0.3], scale: [1, 1.6, 1] }}
          transition={{ duration: 2.5 + i * 0.3, repeat: Infinity, delay: i * 0.2 }}
        />
      ))}
      <div className="pay-success-content">
        <motion.span
          className="pay-success-icon"
          animate={{ rotate: [0, 15, -15, 0] }}
          transition={{ duration: 3, repeat: Infinity, ease: 'easeInOut' }}
        >
          ✦
        </motion.span>
        <h2>{usedMembresia ? '¡Membresía aplicada!' : '¡Pago confirmado!'}</h2>
        <p>Tu cita de <strong>{cita.tipo_consulta?.nombre}</strong> está reservada.</p>
        <div className="wizard-actions">
          <button
            type="button"
            className="btn-secondary"
            onClick={() =>
              generateIcs({
                title: `Consulta: ${cita.tipo_consulta?.nombre ?? 'TarotEstrellas'}`,
                startUtc: cita.inicio_utc,
                endUtc:   cita.fin_utc,
                description: 'Consulta espiritual en TarotEstrellas',
              })
            }
          >
            📅 Agregar al calendario
          </button>
          <Link className="btn-primary btn-shimmer" to="/app/mis-consultas">
            Ver mis consultas
          </Link>
        </div>
      </div>
    </motion.div>
  );
}

// ── Main page ────────────────────────────────────────────────────────────────
export function PagarCitaPage() {
  const { id = '' } = useParams<{ id: string }>();
  const qc = useQueryClient();

  const [method, setMethod]             = useState<'stripe' | 'transfer' | null>(null);
  const [paid, setPaid]                 = useState(false);
  const [usedMembresia, setUsedMembresia] = useState(false);
  const [clientSecret, setClientSecret] = useState('');
  const [citaOverride, setCitaOverride] = useState<Cita | null>(null);

  const { data: citaRaw, isLoading, isError } = useQuery({
    queryKey: ['cita', id],
    queryFn:  () => fetchCita(id),
    enabled:  Boolean(id),
  });

  const { data: metodos = [] } = useQuery({
    queryKey: ['metodos-pago'],
    queryFn:  fetchMetodosPago,
  });

  const intentMutation = useMutation({
    mutationFn: () => createPaymentIntent(id),
    onSuccess:  (data) => setClientSecret(data.client_secret),
  });

  const handleSelectMethod = (m: 'stripe' | 'transfer') => {
    setMethod(m);
    if (m === 'stripe' && !clientSecret) intentMutation.mutate();
  };

  const handleCuponApplied = (updatedCita: Cita) => {
    setCitaOverride(updatedCita);
    qc.setQueryData(['cita', id], updatedCita);
    // Reset intent so new price is used
    setClientSecret('');
    setMethod(null);
  };

  const handleMembresiaUsed = () => {
    setUsedMembresia(true);
    setPaid(true);
  };

  useEffect(() => { document.title = 'Pagar cita | TarotEstrellas'; }, []);

  if (isLoading) return <main className="page-content"><p>Cargando…</p></main>;
  if (isError || !citaRaw) return <main className="page-content"><p className="form-error">No se encontró la cita.</p></main>;

  const cita = citaOverride ?? citaRaw;

  if (cita.estado !== 'pendiente_abono') {
    return (
      <main className="page-content">
        <div className="dash-empty">
          <span className="dash-empty-icon">✦</span>
          <p>Esta cita no requiere pago en este momento.</p>
          <Link className="btn-primary" to="/app/mis-consultas">Ver mis consultas</Link>
        </div>
      </main>
    );
  }

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
        <p className="dash-eyebrow">✦ Abono del 20%</p>
        <h1 className="dash-title">Confirmar pago</h1>
        <p className="dash-subtitle">{cita.tipo_consulta?.nombre}</p>
      </motion.div>

      <AnimatePresence mode="wait">
        {paid ? (
          <SuccessScreen key="success" cita={cita} usedMembresia={usedMembresia} />
        ) : (
          <motion.div
            key="payment"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="pay-wrapper"
          >
            {/* Resumen */}
            <div className="pay-summary-card">
              {cita.primera_consulta && (
                <div className="primera-consulta-badge">
                  🎉 10% de descuento — ¡primera consulta!
                </div>
              )}
              <div className="pay-summary-row">
                <span>Servicio</span>
                <strong>{cita.tipo_consulta?.nombre}</strong>
              </div>
              <div className="pay-summary-row">
                <span>Duración</span>
                <strong>{cita.tipo_consulta?.duracion_minutos} min</strong>
              </div>
              <div className="pay-summary-row">
                <span>Total</span>
                <strong>{formatMoney(cita.precio_total_centavos, cita.moneda)}</strong>
              </div>
              <div className="pay-summary-row pay-abono-row">
                <span>Abono hoy (20%)</span>
                <strong>{formatMoney(cita.precio_final_centavos, cita.moneda)}</strong>
              </div>
            </div>

            {/* Membresía */}
            <MembresiaCheckout citaId={id} onUsed={handleMembresiaUsed} />

            {/* Cupón */}
            <CuponSection
              citaId={id}
              applied={cita.cupon_aplicado}
              onApplied={handleCuponApplied}
            />

            {/* Selector de método */}
            {!method ? (
              <motion.div
                className="pay-methods"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 }}
              >
                <p className="pay-section-label">¿Cómo deseas pagar?</p>
                <div className="pay-method-grid">
                  <button
                    type="button"
                    className="pay-method-card"
                    onClick={() => handleSelectMethod('stripe')}
                  >
                    <span className="pay-method-icon">💳</span>
                    <span className="pay-method-name">Tarjeta de crédito / débito</span>
                    <span className="pay-method-sub">Visa, Mastercard, Amex</span>
                  </button>
                  <button
                    type="button"
                    className="pay-method-card"
                    onClick={() => handleSelectMethod('transfer')}
                  >
                    <span className="pay-method-icon">🏦</span>
                    <span className="pay-method-name">Transferencia bancaria</span>
                    <span className="pay-method-sub">Solo clientes en Chile</span>
                  </button>
                </div>
              </motion.div>
            ) : (
              <motion.div
                key={method}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
              >
                <button
                  type="button"
                  className="btn-secondary"
                  style={{ marginBottom: '1rem', fontSize: '0.85rem' }}
                  onClick={() => setMethod(null)}
                >
                  ← Cambiar método
                </button>

                {method === 'stripe' && clientSecret ? (
                  <Elements stripe={stripePromise} options={{ clientSecret }}>
                    <StripeForm
                      clientSecret={clientSecret}
                      cita={cita}
                      onSuccess={() => setPaid(true)}
                    />
                  </Elements>
                ) : method === 'stripe' && intentMutation.isPending ? (
                  <p>Preparando formulario de pago…</p>
                ) : method === 'stripe' && intentMutation.isError ? (
                  <p className="form-error">No se pudo iniciar el pago. Intenta de nuevo.</p>
                ) : null}

                {method === 'transfer' ? (
                  <TransferPanel cita={cita} metodos={metodos} />
                ) : null}
              </motion.div>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
