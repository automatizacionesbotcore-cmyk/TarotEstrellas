import { useState } from 'react';
import type { FormEvent } from 'react';
import { api } from '../../lib/api';

type Props = {
  onBackToLogin?: () => void;
};

export function ForgotPasswordForm({ onBackToLogin }: Props) {
  const [email,          setEmail]          = useState('');
  const [submitting,     setSubmitting]     = useState(false);
  const [errorMessage,   setErrorMessage]   = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    try {
      const res = await api.post<{ message?: string }>('/auth/forgot-password', { email });
      setSuccessMessage(
        res.data?.message ?? 'Si el correo existe, te enviaremos instrucciones para recuperar el acceso.',
      );
    } catch (error: any) {
      const msg = error?.response?.data?.message;
      const val = error?.response?.data?.errors?.email?.[0];
      setErrorMessage(msg ?? val ?? 'No se pudo procesar la solicitud de recuperación.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <h2 className="auth-form-title">Recuperar contraseña</h2>
      <p className="auth-form-subtitle">Te enviaremos un enlace para restablecer tu acceso.</p>

      <form className="auth-form" onSubmit={handleSubmit}>
        <label>
          Correo
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoComplete="email"
          />
        </label>

        {errorMessage   ? <p className="form-error">{errorMessage}</p>     : null}
        {successMessage ? <p className="form-success">{successMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={submitting}>
          {submitting ? 'Enviando...' : 'Enviar enlace'}
        </button>

        {onBackToLogin ? (
          <button type="button" className="auth-link-inline" onClick={onBackToLogin}>
            ← Volver a iniciar sesión
          </button>
        ) : null}
      </form>
    </>
  );
}
