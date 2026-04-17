import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';

type Status = 'verifying' | 'success' | 'error';

export function VerifyEmailPage() {
  const [searchParams] = useSearchParams();
  const [status,  setStatus]  = useState<Status>('verifying');
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    const id        = searchParams.get('id');
    const hash      = searchParams.get('hash');
    const expires   = searchParams.get('expires');
    const signature = searchParams.get('signature');

    if (!id || !hash) {
      setStatus('error');
      setMessage('El enlace de verificación no es válido o está incompleto.');
      return;
    }

    api
      .get(`/auth/email/verify/${id}/${hash}`, {
        params: { expires, signature },
      })
      .then(() => {
        setStatus('success');
      })
      .catch((err: any) => {
        const msg =
          err?.response?.data?.message ??
          'No se pudo verificar el correo. El enlace puede haber expirado.';
        setStatus('error');
        setMessage(msg);
      });
  }, [searchParams]);

  return (
    <div className="auth-card verify-email-card">
      {status === 'verifying' ? (
        <>
          <p className="verify-email-icon">✉️</p>
          <h1 className="auth-form-title">Verificando correo…</h1>
          <p className="auth-form-subtitle">Un momento, estamos confirmando tu cuenta.</p>
        </>
      ) : status === 'success' ? (
        <>
          <p className="verify-email-icon">✅</p>
          <h1 className="auth-form-title">¡Correo verificado!</h1>
          <p className="auth-form-subtitle">Tu cuenta está activa. Ya puedes iniciar sesión.</p>
          <Link className="btn-primary" to="/auth/login">Iniciar sesión</Link>
        </>
      ) : (
        <>
          <p className="verify-email-icon">⚠️</p>
          <h1 className="auth-form-title">Verificación fallida</h1>
          <p className="auth-form-subtitle">{message}</p>
          <Link className="btn-secondary" to="/">Volver al inicio</Link>
        </>
      )}
    </div>
  );
}
