import { motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '../../lib/api';
import { toast } from '../../stores/toastStore';

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

type DatosBancarios = {
  banco: string;
  titular: string;
  cuenta: string;
  tipo_cuenta: string;
  rut?: string;
  email?: string | null;
};

// ── API ───────────────────────────────────────────────────────────────────────
const fetchCita = (id: string) =>
  api.get(`/citas/${id}`).then((r) => (r.data as { data: Cita }).data);

const fetchDatosTransferencia = (citaId: string) =>
  api.get(`/citas/${citaId}/pagar/transferencia/datos`)
    .then((r) => (r.data as { datos_bancarios: DatosBancarios }).datos_bancarios);

const createFlowSaldoPayment = (citaId: string) =>
  api.post(`/citas/${citaId}/pagar/flow`).then((r) => r.data as { data: { redirect_url: string } });

const createPaypalSaldoPayment = (citaId: string) =>
  api.post(`/citas/${citaId}/pagar/paypal`).then((r) => r.data as { data: { approval_url: string } });

const uploadComprobanteSaldo = (citaUuid: string, file: File) => {
  const form = new FormData();
  form.append('comprobante', file);
  return api.post(`/citas/${citaUuid}/pagar/transferencia/comprobante`, form, {
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

function buildSaldoTransferText(cita: Cita, datos: DatosBancarios, saldo: number) {
  return [
    'Datos para transferencia TarotEstrellas',
    `Código de referencia: ${cita.codigo_referencia}-SALDO`,
    `Monto a transferir: ${formatMoney(saldo, cita.moneda)}`,
    `Banco: ${datos.banco}`,
    `Titular: ${datos.titular}`,
    `N° de cuenta: ${datos.cuenta}`,
    `Tipo de cuenta: ${datos.tipo_cuenta}`,
    datos.rut ? `RUT: ${datos.rut}` : null,
    datos.email ? `Email: ${datos.email}` : null,
  ].filter(Boolean).join('\n');
}

// ── Transfer saldo panel ──────────────────────────────────────────────────────
function SaldoTransferPanel({ cita, datosBancarios }: { cita: Cita; datosBancarios: DatosBancarios | null }) {
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
      await uploadComprobanteSaldo(cita.uuid, file);
      setUploaded(true);
    } catch {
      setUploadError('No se pudo subir el comprobante. Intenta de nuevo.');
    } finally {
      setUploading(false);
    }
  };

  const saldo  = getSaldoCentavos(cita);

  return (
    <div className="transfer-panel">
      <details className="payment-accordion" open>
        <summary>Código de referencia</summary>
        <div className="ref-code-box">
          <span className="pay-section-label">Incluirlo en la transferencia</span>
          <strong className="ref-code">{cita.codigo_referencia}-SALDO</strong>
          <button
            type="button"
            className="copy-data-btn"
            onClick={() => copyToClipboard(`${cita.codigo_referencia}-SALDO`, 'Código de referencia')}
          >
            Copiar código
          </button>
        </div>
      </details>

      {datosBancarios ? (
        <details className="payment-accordion" open={!uploaded}>
          <summary>Datos para transferir</summary>
          <div className="bank-details">
            <div className="copy-data-toolbar">
              <button
                type="button"
                className="copy-data-btn copy-data-btn--primary"
                onClick={() => copyToClipboard(buildSaldoTransferText(cita, datosBancarios, saldo), 'Datos de transferencia')}
              >
                Copiar todos los datos
              </button>
            </div>
            <dl>
              <dt>Banco</dt><dd>{datosBancarios.banco}</dd>
              <dt>Titular</dt><dd>{datosBancarios.titular}</dd>
              <dt>N° de cuenta</dt>
              <dd className="copyable-bank-value">
                <span>{datosBancarios.cuenta}</span>
                <button
                  type="button"
                  className="copy-inline-btn"
                  onClick={() => copyToClipboard(datosBancarios.cuenta, 'Número de cuenta')}
                >
                  Copiar
                </button>
              </dd>
              <dt>Tipo de cuenta</dt><dd>{datosBancarios.tipo_cuenta}</dd>
              {datosBancarios.rut ? (
                <>
                  <dt>RUT</dt>
                  <dd className="copyable-bank-value">
                    <span>{datosBancarios.rut}</span>
                    <button
                      type="button"
                      className="copy-inline-btn"
                      onClick={() => copyToClipboard(datosBancarios.rut ?? '', 'RUT')}
                    >
                      Copiar
                    </button>
                  </dd>
                </>
              ) : null}
              {datosBancarios.email ? (
                <>
                  <dt>Email</dt>
                  <dd className="copyable-bank-value">
                    <span>{datosBancarios.email}</span>
                    <button
                      type="button"
                      className="copy-inline-btn"
                      onClick={() => copyToClipboard(datosBancarios.email ?? '', 'Email')}
                    >
                      Copiar
                    </button>
                  </dd>
                </>
              ) : null}
            </dl>
            <p className="pay-abono-note">
              Monto a transferir: <strong>{formatMoney(saldo, cita.moneda)}</strong>
            </p>
          </div>
        </details>
      ) : (
        <p className="pay-section-label">Cargando datos bancarios…</p>
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
            <button type="button" className="btn-secondary" onClick={() => fileRef.current?.click()}>
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
        <motion.div className="upload-success" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
          <span>✦</span>
          <p>Comprobante recibido. Lo verificaremos antes de tu sesión.</p>
        </motion.div>
      )}
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────
export function PagarSaldoPage() {
  const { id = '' } = useParams<{ id: string }>();
  const [method, setMethod]         = useState<'flow' | 'paypal' | 'transfer' | null>(null);
  const [redirectError, setRedirectError] = useState('');

  const { data: cita, isLoading, isError } = useQuery({
    queryKey: ['cita', id],
    queryFn:  () => fetchCita(id),
    enabled:  Boolean(id),
  });

  const { data: datosBancarios = null } = useQuery({
    queryKey: ['datos-transferencia', id],
    queryFn:  () => fetchDatosTransferencia(id),
    enabled:  Boolean(id) && method === 'transfer',
  });

  const flowMutation = useMutation({
    mutationFn: () => createFlowSaldoPayment(id),
    onSuccess:  (data) => { window.location.href = data.data.redirect_url; },
    onError:    () => setRedirectError('No se pudo iniciar el pago con Flow. Intenta de nuevo.'),
  });

  const paypalMutation = useMutation({
    mutationFn: () => createPaypalSaldoPayment(id),
    onSuccess:  (data) => { window.location.href = data.data.approval_url; },
    onError:    () => setRedirectError('No se pudo iniciar el pago con PayPal. Intenta de nuevo.'),
  });

  const handleSelectMethod = (m: 'flow' | 'paypal' | 'transfer') => {
    setMethod(m);
    setRedirectError('');
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
          <p>Esta cita no tiene saldo pendiente en este momento.</p>
          <Link className="btn-primary" to="/app/mis-consultas">Ver mis consultas</Link>
        </div>
      </main>
    );
  }

  const saldo = getSaldoCentavos(cita);

  return (
    <main className="page-content payment-page">
      <motion.div className="payment-header" initial={{ opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
        <p className="dash-eyebrow">Saldo restante</p>
        <h1 className="dash-title">Pagar saldo</h1>
        <p className="dash-subtitle">{cita.tipo_consulta?.nombre}. Completa el saldo antes de tu sesión.</p>
      </motion.div>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
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
                <div className="pay-methods-header">
                  <p className="pay-section-label">Método de pago</p>
                  <p>Elige una opción para completar el saldo pendiente.</p>
                </div>
                <div className="pay-method-grid">
                  <button
                    type="button"
                    className="pay-method-card"
                    onClick={() => handleSelectMethod('flow')}
                  >
                    <span className="pay-method-name">Tarjeta chilena (Flow)</span>
                    <span className="pay-method-sub">Webpay, débito y crédito Chile</span>
                  </button>
                  <button
                    type="button"
                    className="pay-method-card"
                    onClick={() => handleSelectMethod('paypal')}
                  >
                    <span className="pay-method-name">PayPal</span>
                    <span className="pay-method-sub">Pagos internacionales</span>
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
                      Saldo a cobrar: <strong>{formatMoney(saldo, cita.moneda)}</strong>
                    </p>
                    {redirectError ? <p className="form-error">{redirectError}</p> : null}
                    <button
                      type="button"
                      className="btn-primary btn-shimmer pay-submit"
                      disabled={flowMutation.isPending}
                      onClick={() => flowMutation.mutate()}
                    >
                      {flowMutation.isPending ? 'Redirigiendo…' : `Pagar ${formatMoney(saldo, cita.moneda)} con Flow`}
                    </button>
                  </div>
                ) : null}

                {method === 'paypal' ? (
                  <div className="pay-redirect-panel">
                    <p className="pay-abono-note">
                      Saldo a cobrar: <strong>{formatMoney(saldo, cita.moneda)}</strong>
                    </p>
                    {redirectError ? <p className="form-error">{redirectError}</p> : null}
                    <button
                      type="button"
                      className="btn-primary btn-shimmer pay-submit"
                      disabled={paypalMutation.isPending}
                      onClick={() => paypalMutation.mutate()}
                    >
                      {paypalMutation.isPending ? 'Redirigiendo…' : `Pagar con PayPal`}
                    </button>
                  </div>
                ) : null}

                {method === 'transfer' ? (
                  <SaldoTransferPanel cita={cita} datosBancarios={datosBancarios} />
                ) : null}
              </motion.div>
            )}

            <div style={{ marginTop: '1.5rem' }}>
              <Link className="btn-secondary" to="/app/mis-consultas">Volver a mis consultas</Link>
            </div>
          </motion.div>
    </main>
  );
}
