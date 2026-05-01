import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { toast } from '../../stores/toastStore';

type Status = { enabled: boolean; confirmed_at: string | null; recovery_remaining: number };
type Setup = { secret: string; otpauth_uri: string; qr_png_b64: string | null };

const fadeUp = { initial: { opacity: 0, y: 8 }, animate: { opacity: 1, y: 0 } };

export function TwoFactorSection() {
  const [status, setStatus] = useState<Status | null>(null);
  const [setup, setSetup] = useState<Setup | null>(null);
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState<string[] | null>(null);
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  const refresh = () =>
    api.get<Status>('/me/2fa/status').then((r) => setStatus(r.data)).catch(() => null);

  useEffect(() => { refresh(); }, []);

  const startSetup = async () => {
    setBusy(true);
    try {
      const r = await api.post<Setup>('/me/2fa/setup');
      setSetup(r.data);
      setRecovery(null);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally { setBusy(false); }
  };

  const confirmCode = async () => {
    setBusy(true);
    try {
      const r = await api.post<{ recovery_codes: string[] }>('/me/2fa/confirm', { code });
      setRecovery(r.data.recovery_codes);
      setSetup(null);
      setCode('');
      toast.success('2FA activado.');
      refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Código inválido.');
    } finally { setBusy(false); }
  };

  const regenerate = async () => {
    if (!window.confirm('¿Generar nuevos códigos? Los anteriores dejarán de funcionar.')) return;
    setBusy(true);
    try {
      const r = await api.post<{ recovery_codes: string[] }>('/me/2fa/recovery-codes/regenerate');
      setRecovery(r.data.recovery_codes);
      toast.success('Códigos regenerados.');
      refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally { setBusy(false); }
  };

  const disable = async () => {
    setBusy(true);
    try {
      await api.post('/me/2fa/disable', { password });
      setPassword('');
      setRecovery(null);
      toast.success('2FA desactivado.');
      refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally { setBusy(false); }
  };

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title">Autenticación en dos pasos (2FA)</h2>
      <p className="cuenta-coming-soon">
        Añade una capa extra de seguridad usando una app de autenticación (Google Authenticator, 1Password, Authy…).
      </p>

      {status?.enabled ? (
        <div style={{ display: 'grid', gap: '0.75rem' }}>
          <p>✅ 2FA activo desde {status.confirmed_at && new Date(status.confirmed_at).toLocaleDateString()}.</p>
          <p style={{ fontSize: '0.9em', color: 'var(--text-muted)' }}>
            Te quedan <strong>{status.recovery_remaining}</strong> códigos de recuperación.
          </p>
          {recovery && <RecoveryList codes={recovery} />}
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
            <button type="button" onClick={regenerate} disabled={busy}>Regenerar códigos</button>
          </div>
          <details>
            <summary>Desactivar 2FA</summary>
            <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
              <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Tu contraseña" />
              <button type="button" onClick={disable} disabled={busy || !password} style={{ color: 'var(--danger)' }}>Desactivar</button>
            </div>
          </details>
        </div>
      ) : setup ? (
        <div style={{ display: 'grid', gap: '0.75rem' }}>
          {setup.qr_png_b64 && (
            <img src={`data:image/png;base64,${setup.qr_png_b64}`} alt="QR 2FA" style={{ width: 220, height: 220, alignSelf: 'center' }} />
          )}
          <p style={{ fontSize: '0.85em' }}>
            ¿No puedes escanear? Usa este código manualmente: <code style={{ userSelect: 'all' }}>{setup.secret}</code>
          </p>
          <label>Ingresa el código de 6 dígitos
            <input value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))} inputMode="numeric" maxLength={6} pattern="[0-9]{6}" />
          </label>
          <button type="button" className="btn-primary" onClick={confirmCode} disabled={busy || code.length !== 6}>Activar 2FA</button>
        </div>
      ) : (
        <button type="button" className="btn-primary" onClick={startSetup} disabled={busy}>Activar 2FA</button>
      )}
    </motion.section>
  );
}

function RecoveryList({ codes }: { codes: string[] }) {
  return (
    <div className="card" style={{ padding: '0.75rem', background: 'rgba(255,200,0,0.08)' }}>
      <strong>Códigos de recuperación</strong>
      <p style={{ fontSize: '0.85em', margin: '0.25rem 0' }}>
        Guárdalos en un lugar seguro. Cada código solo puede usarse una vez.
      </p>
      <pre style={{ fontFamily: 'monospace', margin: 0, userSelect: 'all' }}>
        {codes.join('\n')}
      </pre>
    </div>
  );
}
