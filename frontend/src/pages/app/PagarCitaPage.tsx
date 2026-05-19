import { AnimatePresence, motion } from 'framer-motion';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api';
import { generateIcs } from '../../lib/ics';
import { toast } from '../../stores/toastStore';

const ConstellationPortal = lazy(() => import('../../components/3d/ConstellationPortal'));

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

type CuentaBancaria = {
  banco: string;
  tipo_cuenta: string;
  numero_cuenta: string;
  nombre_titular: string;
  rut_titular: string;
  orden: number;
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

// ── API helpers ──────────────────────────────────────────────────────────────
const fetchCita = (id: string) =>
  api.get(`/citas/${id}`).then((r) => (r.data as { data: Cita }).data);

const fetchDatosTransferencia = (citaId: string) =>
  api.get(`/citas/${citaId}/pagar/transferencia/datos`)
     .then((r) => (r.data as { data: { cuentas_bancarias: CuentaBancaria[] } }).data.cuentas_bancarias);

const createFlowPayment = (citaId: string) =>
  api.post(`/citas/${citaId}/pagar/flow`).then((r) => r.data as { data: { redirect_url: string } });

const createPaypalPayment = (citaId: string) =>
  api.post(`/citas/${citaId}/pagar/paypal`).then((r) => r.data as { data: { approval_url: string } });

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

const PORCENTAJE_ABONO = 0.20;
function calcAbono(cents: number) {
  return Math.round(cents * PORCENTAJE_ABONO);
}
function calcSaldo(cents: number) {
  return Math.max(0, cents - calcAbono(cents));
}

function getRemainingSeconds(iso: string) {
  return Math.max(0, Math.floor((new Date(iso).getTime() - Date.now()) / 1000));
}

function fmtCountdown(s: number) {
  const m   = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  return `${m}:${sec}`;
}

async function copyToClipboard(text: string, label: string) {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', 'true');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }
    toast.success(`${label} copiado.`);
  } catch {
    toast.error('No se pudo copiar. Intenta seleccionar el texto manualmente.');
  }
}

function buildTransferText(cita: Cita, cuentas: CuentaBancaria[]) {
  const monto = formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda);
  const cuentasText = cuentas.map((cuenta, i) => [
    cuentas.length > 1 ? `Cuenta ${i + 1}` : 'Cuenta bancaria',
    `Banco: ${cuenta.banco}`,
    `Titular: ${cuenta.nombre_titular}`,
    `N° de cuenta: ${cuenta.numero_cuenta}`,
    `Tipo de cuenta: ${cuenta.tipo_cuenta}`,
    cuenta.rut_titular ? `RUT: ${cuenta.rut_titular}` : null,
  ].filter(Boolean).join('\n')).join('\n\n');

  return [
    'Datos para transferencia TarotEstrellas',
    `Código de referencia: ${cita.codigo_referencia}`,
    `Monto a transferir (abono 20%): ${monto}`,
    cuentasText,
  ].join('\n');
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

// ── Transfer panel ───────────────────────────────────────────────────────────
function TransferPanel({ cita, cuentasBancarias }: { cita: Cita; cuentasBancarias: CuentaBancaria[] | null }) {
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
      const res = await extenderReserva(String(cita.uuid));
      setSeconds(getRemainingSeconds(res.reservada_hasta));
      setExtended(true);
    } catch { /* silencioso */ }
  };

  const handleUpload = async () => {
    if (!file) return;
    setUploading(true);
    setUploadError('');
    try {
      await uploadComprobante(cita.uuid, file);
      setUploaded(true);
    } catch {
      setUploadError('No se pudo subir el comprobante. Intenta de nuevo.');
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="transfer-panel">
      {!uploaded ? (
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
      ) : null}

      <details className="payment-accordion" open>
        <summary>Código de referencia</summary>
        <div className="ref-code-box">
          <span className="pay-section-label">Incluirlo en la transferencia</span>
          <strong className="ref-code">{cita.codigo_referencia}</strong>
          <button
            type="button"
            className="copy-data-btn"
            onClick={() => copyToClipboard(cita.codigo_referencia, 'Código de referencia')}
          >
            Copiar código
          </button>
        </div>
      </details>

      {cuentasBancarias && cuentasBancarias.length > 0 ? (
        <details className="payment-accordion" open={!uploaded}>
          <summary>Datos para transferir</summary>
          <div className="bank-details">
            <div className="copy-data-toolbar">
              <button
                type="button"
                className="copy-data-btn copy-data-btn--primary"
                onClick={() => copyToClipboard(buildTransferText(cita, cuentasBancarias), 'Datos de transferencia')}
              >
                Copiar todos los datos
              </button>
            </div>
            {cuentasBancarias.map((cuenta, i) => (
              <div key={i} style={{ marginBottom: i < cuentasBancarias.length - 1 ? '1rem' : 0 }}>
                {cuentasBancarias.length > 1 && (
                  <p style={{ fontWeight: 600, marginBottom: '0.35rem', fontSize: '0.9rem' }}>
                    Cuenta {i + 1}
                  </p>
                )}
                <dl>
                  <dt>Banco</dt>        <dd>{cuenta.banco}</dd>
                  <dt>Titular</dt>      <dd>{cuenta.nombre_titular}</dd>
                  <dt>N° de cuenta</dt>
                  <dd className="copyable-bank-value">
                    <span>{cuenta.numero_cuenta}</span>
                    <button
                      type="button"
                      className="copy-inline-btn"
                      onClick={() => copyToClipboard(cuenta.numero_cuenta, 'Número de cuenta')}
                    >
                      Copiar
                    </button>
                  </dd>
                  <dt>Tipo de cuenta</dt><dd>{cuenta.tipo_cuenta}</dd>
                  {cuenta.rut_titular ? (
                    <>
                      <dt>RUT</dt>
                      <dd className="copyable-bank-value">
                        <span>{cuenta.rut_titular}</span>
                        <button
                          type="button"
                          className="copy-inline-btn"
                          onClick={() => copyToClipboard(cuenta.rut_titular, 'RUT')}
                        >
                          Copiar
                        </button>
                      </dd>
                    </>
                  ) : null}
                </dl>
              </div>
            ))}
            <p className="pay-abono-note">
              Monto a transferir (abono 20%): <strong>{formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda)}</strong>
            </p>
            <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
              El saldo restante de {formatMoney(calcSaldo(cita.precio_final_centavos), cita.moneda)} se paga el día de la consulta.
            </p>
          </div>
        </details>
      ) : cuentasBancarias === null ? (
        <p className="pay-section-label">Cargando datos bancarios…</p>
      ) : (
        <p className="pay-section-label" style={{ color: 'var(--text-muted)' }}>
          El especialista aún no ha configurado sus cuentas bancarias. Contáctanos para completar el pago.
        </p>
      )}

      {!uploaded ? (
        <details className="payment-accordion" open>
          <summary>Adjuntar comprobante</summary>
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
              {file ? file.name : 'Seleccionar archivo'}
            </button>
            {file ? (
              <button type="button" className="btn-primary" disabled={uploading} onClick={handleUpload}>
                {uploading ? 'Subiendo…' : 'Enviar comprobante'}
              </button>
            ) : null}
            {uploadError ? <p className="form-error">{uploadError}</p> : null}
          </div>
        </details>
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
        <Suspense fallback={null}>
          <ConstellationPortal />
        </Suspense>
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
            Agregar al calendario
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

  const [method, setMethod]             = useState<'flow' | 'paypal' | 'transfer' | null>(null);
  const [paid, setPaid]                 = useState(false);
  const [usedMembresia, setUsedMembresia] = useState(false);
  const [redirectError, setRedirectError] = useState('');
  const [citaOverride, setCitaOverride] = useState<Cita | null>(null);

  const { data: citaRaw, isLoading, isError } = useQuery({
    queryKey: ['cita', id],
    queryFn:  () => fetchCita(id),
    enabled:  Boolean(id),
  });

  const { data: cuentasBancarias = null } = useQuery<CuentaBancaria[] | null>({
    queryKey: ['datos-transferencia', id],
    queryFn:  () => fetchDatosTransferencia(id),
    enabled:  Boolean(id) && method === 'transfer',
    retry:    false,
  });

  const flowMutation = useMutation({
    mutationFn: () => createFlowPayment(id),
    onSuccess:  (data) => { window.location.href = data.data.redirect_url; },
    onError:    () => setRedirectError('No se pudo iniciar el pago con Flow. Intenta de nuevo.'),
  });

  const paypalMutation = useMutation({
    mutationFn: () => createPaypalPayment(id),
    onSuccess:  (data) => { window.location.href = data.data.approval_url; },
    onError:    () => setRedirectError('No se pudo iniciar el pago con PayPal. Intenta de nuevo.'),
  });

  const handleSelectMethod = (m: 'flow' | 'paypal' | 'transfer') => {
    setMethod(m);
    setRedirectError('');
  };

  const handleCuponApplied = (updatedCita: Cita) => {
    setCitaOverride(updatedCita);
    qc.setQueryData(['cita', id], updatedCita);
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
    <main className="page-content payment-page">
      <motion.div className="payment-header" initial={{ opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
        <p className="dash-eyebrow">Abono del 20%</p>
        <h1 className="dash-title">Confirmar pago</h1>
        <p className="dash-subtitle">Reserva tu sesión pagando el abono inicial. El saldo queda claro antes de confirmar.</p>
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
                  10% de descuento por primera consulta
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
                <span>Precio total</span>
                <strong>{formatMoney(cita.precio_total_centavos, cita.moneda)}</strong>
              </div>
              {cita.precio_final_centavos !== cita.precio_total_centavos && (
                <div className="pay-summary-row" style={{ color: 'var(--accent, #7c3aed)' }}>
                  <span>Descuento aplicado</span>
                  <strong>−{formatMoney(cita.precio_total_centavos - cita.precio_final_centavos, cita.moneda)}</strong>
                </div>
              )}
              <div className="pay-summary-row">
                <span>Subtotal a pagar</span>
                <strong>{formatMoney(cita.precio_final_centavos, cita.moneda)}</strong>
              </div>
              <div className="pay-summary-row pay-abono-row">
                <span>Abono hoy (20% mínimo)</span>
                <strong>{formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda)}</strong>
              </div>
              <div className="pay-summary-row" style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                <span>Saldo restante (80%)</span>
                <span>{formatMoney(calcSaldo(cita.precio_final_centavos), cita.moneda)}</span>
              </div>
              <p style={{
                marginTop: '0.6rem', fontSize: '0.8rem', color: 'var(--text-muted)',
                lineHeight: 1.4, padding: '0.5rem 0.75rem', borderRadius: '8px',
                background: 'rgba(124,58,237,0.08)', border: '1px solid rgba(124,58,237,0.2)',
              }}>
                Para confirmar tu reserva debes pagar al menos el <strong>20%</strong> del valor total.
                El saldo restante (80%) se cancela el día de la consulta.
              </p>
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
                <div className="pay-methods-header">
                  <p className="pay-section-label">Método de pago</p>
                  <p>Elige PayPal o transferencia. Ambas opciones mantienen el mismo abono.</p>
                </div>
                <div className="pay-method-grid">
                  {/* Flow.cl — TODO FASE 2: reactivar cuando se integre Webpay/Flow Chile
                  <div className="pay-method-card pay-method-card--disabled" title="Próximamente disponible">
                    <span className="pay-method-icon">💳</span>
                    <span className="pay-method-name">Tarjeta chilena (Flow)</span>
                    <span className="pay-method-sub">Webpay, débito y crédito Chile</span>
                    <span style={{
                      fontSize: '0.65rem', padding: '2px 8px', borderRadius: '99px',
                      background: 'var(--accent, #7c3aed)', color: '#fff', marginTop: '0.3rem',
                    }}>Próximamente</span>
                  </div>
                  */}
                  <button
                    type="button"
                    className="pay-method-card"
                    onClick={() => handleSelectMethod('paypal')}
                  >
                    <span className="pay-method-name">PayPal</span>
                    <span className="pay-method-sub">Tarjeta o saldo PayPal</span>
                  </button>
                  <button
                    type="button"
                    className="pay-method-card"
                    onClick={() => handleSelectMethod('transfer')}
                  >
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
                  onClick={() => { setMethod(null); setRedirectError(''); }}
                >
                  Cambiar método
                </button>

                {method === 'flow' ? (
                  <div className="pay-redirect-panel">
                    <p className="pay-abono-note">
                      Abono hoy (20%): <strong>{formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda)}</strong>
                    </p>
                    {redirectError ? <p className="form-error">{redirectError}</p> : null}
                    <button
                      type="button"
                      className="btn-primary btn-shimmer pay-submit"
                      disabled={flowMutation.isPending}
                      onClick={() => flowMutation.mutate()}
                    >
                      {flowMutation.isPending ? 'Redirigiendo…' : `Pagar ${formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda)} con Flow`}
                    </button>
                  </div>
                ) : null}

                {method === 'paypal' ? (
                  <div className="pay-redirect-panel">
                    <p className="pay-abono-note">
                      Abono hoy (20%): <strong>{formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda)}</strong>
                    </p>
                    <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', margin: '0 0 0.75rem' }}>
                      El saldo restante de {formatMoney(calcSaldo(cita.precio_final_centavos), cita.moneda)} se paga el día de la consulta.
                    </p>
                    {redirectError ? <p className="form-error">{redirectError}</p> : null}
                    <button
                      type="button"
                      className="btn-primary btn-shimmer pay-submit"
                      disabled={paypalMutation.isPending}
                      onClick={() => paypalMutation.mutate()}
                    >
                      {paypalMutation.isPending ? 'Redirigiendo…' : `Pagar ${formatMoney(calcAbono(cita.precio_final_centavos), cita.moneda)} con PayPal`}
                    </button>
                  </div>
                ) : null}

                {method === 'transfer' ? (
                  <TransferPanel cita={cita} cuentasBancarias={cuentasBancarias} />
                ) : null}
              </motion.div>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
