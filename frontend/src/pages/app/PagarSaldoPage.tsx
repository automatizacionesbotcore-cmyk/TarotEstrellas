import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { loadStripe, type StripeCardElement } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { api } from '../../lib/api';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY ?? '');

// ── Types ────────────────────────────────────────────────────────────────────
type Cita = {
  id: number;
  uuid: string;
  estado: string;
  inicio_utc: string;
  fin_utc: string;
  precio_total_centavos: number;
  precio_final_centavos: number;
  saldo_centavos: number;
  moneda: string;
  codigo_referencia: string;
  tipo_consulta: { nombre: string; duracion_minutos: number } | null;
};

type MetodoPago = {
  id: number;
  banco: string;
  titular: string;
  numero_cuenta: string;
  tipo_cuenta: string;
  rut?: string;
  email?: string;
};

type PaymentIntentResponse = { client_secret: string };

// ── API ───────────────────────────────────────────────────────────────────────
const fetchCita        = (id: string) =>
  api.get(`/citas/${id}`).then((r) => (r.data as { data: Cita }).data);

const fetchMetodosPago = () =>
  api.get('/metodos-pago').then((r) => (r.data as { data: MetodoPago[] }).data);

const createSaldoIntent = (citaId: string) =>
  api.post(`/citas/${citaId}/pagos/stripe-saldo`).then((r) => r.data as PaymentIntentResponse);

const uploadComprobanteSaldo = (citaId: string, file: File) => {
  const form = new FormData();
  form.append('comprobante', file);
  return api.post(`/citas/${citaId}/pagos/comprobante-saldo`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'CLP' ? 0 : 2,
  }).format(cents / 100);
}

function getSaldoCentavos(cita: Cita): number {
  return cita.saldo_centavos ?? (cita.precio_total_centavos - cita.precio_final_centavos);
}

// ── Stripe form ───────────────────────────────────────────────────────────────
function SaldoStripeForm({
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
  const [error, setError]     = useState('');
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

  const saldo = getSaldoCentavos(cita);

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
        Saldo a cobrar: <strong>{formatMoney(saldo, cita.moneda)}</strong>
      </p>
      <button className="btn-primary btn-shimmer pay-submit" type="submit" disabled={loading || !stripe}>
        {loading ? 'Procesando…' : `Pagar ${formatMoney(saldo, cita.moneda)}`}
      </button>
    </form>
  );
}

// ── Transfer saldo panel ──────────────────────────────────────────────────────
function SaldoTransferPanel({ cita, metodos }: { cita: Cita; metodos: MetodoPago[] }) {
  const [file, setFile]           = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [uploaded, setUploaded]   = useState(false);
  const [uploadError, setUploadError] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);

  const handleUpload = async () => {
    if (!file) return;
    setUploading(true);
    setUploadError('');
    try {
      await uploadComprobanteSaldo(String(cita.id), file);
      setUploaded(true);
    } catch {
      setUploadError('No se pudo subir el comprobante. Intenta de nuevo.');
    } finally {
      setUploading(false);
    }
  };

  const banco  = metodos[0];
  const saldo  = getSaldoCentavos(cita);

  return (
    <div className="transfer-panel">
      <div className="ref-code-box">
        <span className="pay-section-label">Código de referencia</span>
        <strong className="ref-code">{cita.codigo_referencia}-SALDO</strong>
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
            Monto a transferir: <strong>{formatMoney(saldo, cita.moneda)}</strong>
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
          <button type="button" className="btn-secondary" onClick={() => fileRef.current?.click()}>
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
        <motion.div className="upload-success" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
          <span>✦</span>
          <p>Comprobante recibido. Lo verificaremos antes de tu sesión.</p>
        </motion.div>
      )}
    </div>
  );
}

// ── Success screen ────────────────────────────────────────────────────────────
function SaldoSuccess({ cita }: { cita: Cita }) {
  return (
    <motion.div
      className="pay-success"
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.5 }}
    >
      <div className="pay-success-content">
        <motion.span
          className="pay-success-icon"
          animate={{ rotate: [0, 15, -15, 0] }}
          transition={{ duration: 3, repeat: Infinity, ease: 'easeInOut' }}
        >
          ✦
        </motion.span>
        <h2>¡Saldo pagado!</h2>
        <p>El pago completo de <strong>{cita.tipo_consulta?.nombre}</strong> está confirmado.</p>
        <Link className="btn-primary btn-shimmer" to="/app/mis-consultas" style={{ marginTop: '1rem' }}>
          Ver mis consultas
        </Link>
      </div>
    </motion.div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────
export function PagarSaldoPage() {
  const { id = '' } = useParams<{ id: string }>();
  const [method, setMethod]         = useState<'stripe' | 'transfer' | null>(null);
  const [paid, setPaid]             = useState(false);
  const [clientSecret, setClientSecret] = useState('');

  const { data: cita, isLoading, isError } = useQuery({
    queryKey: ['cita', id],
    queryFn:  () => fetchCita(id),
    enabled:  Boolean(id),
  });

  const { data: metodos = [] } = useQuery({
    queryKey: ['metodos-pago'],
    queryFn:  fetchMetodosPago,
  });

  const intentMutation = useMutation({
    mutationFn: () => createSaldoIntent(id),
    onSuccess:  (data) => setClientSecret(data.client_secret),
  });

  const handleSelectMethod = (m: 'stripe' | 'transfer') => {
    setMethod(m);
    if (m === 'stripe' && !clientSecret) intentMutation.mutate();
  };

  useEffect(() => { document.title = 'Pagar saldo | TarotEstrellas'; }, []);

  if (isLoading) return <main className="page-content"><p>Cargando…</p></main>;
  if (isError || !cita) return (
    <main className="page-content">
      <p className="form-error">No se encontró la cita.</p>
      <Link className="btn-secondary" to="/app/mis-consultas">Volver</Link>
    </main>
  );

  if (cita.estado !== 'reservada') {
    return (
      <main className="page-content">
        <div className="dash-empty">
          <span className="dash-empty-icon">✦</span>
          <p>Esta cita no tiene saldo pendiente en este momento.</p>
          <Link className="btn-primary" to="/app/mis-consultas">Ver mis consultas</Link>
        </div>
      </main>
    );
  }

  const saldo = getSaldoCentavos(cita);

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
        <p className="dash-eyebrow">✦ Saldo restante (80%)</p>
        <h1 className="dash-title">Pagar saldo</h1>
        <p className="dash-subtitle">{cita.tipo_consulta?.nombre}</p>
      </motion.div>

      <AnimatePresence mode="wait">
        {paid ? (
          <SaldoSuccess key="success" cita={cita} />
        ) : (
          <motion.div
            key="payment"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="pay-wrapper"
          >
            <div className="pay-summary-card">
              <div className="pay-summary-row">
                <span>Servicio</span>
                <strong>{cita.tipo_consulta?.nombre}</strong>
              </div>
              <div className="pay-summary-row">
                <span>Abono pagado</span>
                <strong>{formatMoney(cita.precio_final_centavos, cita.moneda)}</strong>
              </div>
              <div className="pay-summary-row">
                <span>Total</span>
                <strong>{formatMoney(cita.precio_total_centavos, cita.moneda)}</strong>
              </div>
              <div className="pay-summary-row pay-abono-row">
                <span>Saldo a pagar (80%)</span>
                <strong>{formatMoney(saldo, cita.moneda)}</strong>
              </div>
            </div>

            {!method ? (
              <motion.div
                className="pay-methods"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 }}
              >
                <p className="pay-section-label">¿Cómo deseas pagar el saldo?</p>
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
                    <SaldoStripeForm
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
                  <SaldoTransferPanel cita={cita} metodos={metodos} />
                ) : null}
              </motion.div>
            )}

            <div style={{ marginTop: '1.5rem' }}>
              <Link className="btn-secondary" to="/app/mis-consultas">← Volver a mis consultas</Link>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
