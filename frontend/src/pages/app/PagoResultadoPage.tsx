import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';

type ConfirmarPaypalResponse = {
  data: { pago_uuid: string; estado: string; monto_centavos: number; moneda: string };
};

async function confirmarPaypal(citaUuid: string, orderId: string): Promise<ConfirmarPaypalResponse> {
  const r = await api.post(`/citas/${citaUuid}/pagar/paypal/confirmar`, { order_id: orderId });
  return r.data as ConfirmarPaypalResponse;
}

// ── Main page ─────────────────────────────────────────────────────────────────
export function PagoResultadoPage() {
  const [params] = useSearchParams();
  const citaUuid = params.get('cita') ?? '';
  const canal    = params.get('canal') ?? 'flow';
  const token    = params.get('token') ?? '';

  const [status, setStatus] = useState<'loading' | 'success' | 'error'>('loading');
  const [errorMsg, setErrorMsg] = useState('');

  useEffect(() => {
    document.title = 'Resultado del pago | TarotEstrellas';

    if (!citaUuid) {
      setErrorMsg('Parámetros de pago inválidos.');
      setStatus('error');
      return;
    }

    if (canal === 'paypal') {
      // PayPal: need to capture the order
      const paypalOrderId = params.get('token') ?? '';
      if (!paypalOrderId) {
        setErrorMsg('No se encontró el ID de orden PayPal.');
        setStatus('error');
        return;
      }
      confirmarPaypal(citaUuid, paypalOrderId)
        .then(() => setStatus('success'))
        .catch((err) => {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          setErrorMsg(msg ?? 'No se pudo confirmar el pago con PayPal.');
          setStatus('error');
        });
    } else {
      // Flow: payment is confirmed via webhook; just show success
      if (token) {
        setStatus('success');
      } else {
        setErrorMsg('No se encontró el token de pago.');
        setStatus('error');
      }
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <main className="page-content payment-page">
      <AnimatePresence mode="wait">
        {status === 'loading' ? (
          <motion.div
            key="loading"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="pay-wrapper payment-result-card"
          >
            <p className="dash-eyebrow">Confirmación</p>
            <p className="dash-subtitle">Confirmando tu pago…</p>
            <p style={{ color: 'var(--color-text-muted, rgba(245,230,211,0.6))', marginTop: '0.5rem' }}>
              Por favor espera un momento.
            </p>
          </motion.div>
        ) : status === 'success' ? (
          <motion.div
            key="success"
            className="pay-success"
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5 }}
          >
            <div className="pay-success-content">
              <h2>¡Pago recibido!</h2>
              <p>Tu pago fue procesado correctamente. Recibirás una confirmación por correo.</p>
              <div className="wizard-actions" style={{ marginTop: '1.5rem' }}>
                <Link className="btn-primary btn-shimmer" to="/app/mis-consultas">
                  Ver mis consultas
                </Link>
              </div>
            </div>
          </motion.div>
        ) : (
          <motion.div
            key="error"
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            className="pay-wrapper payment-result-card"
          >
            <h2 style={{ marginTop: '1rem' }}>No pudimos confirmar tu pago</h2>
            <p className="form-error" style={{ marginTop: '0.5rem' }}>{errorMsg}</p>
            <div style={{ marginTop: '1.5rem', display: 'flex', gap: '1rem', justifyContent: 'center' }}>
              {citaUuid ? (
                <Link className="btn-primary" to={`/app/pagar-cita/${citaUuid}`}>
                  Intentar de nuevo
                </Link>
              ) : null}
              <Link className="btn-secondary" to="/app/mis-consultas">
                Ver mis consultas
              </Link>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
