import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    try {
      const response = await api.post<{ message?: string }>('/auth/forgot-password', { email });
      setSuccessMessage(response.data?.message ?? 'Si el correo existe, te enviaremos instrucciones para recuperar el acceso.');
    } catch (error: any) {
      const backendMessage = error?.response?.data?.message;
      const validationMessage = error?.response?.data?.errors?.email?.[0];
      setErrorMessage(backendMessage ?? validationMessage ?? 'No se pudo procesar la solicitud de recuperacion.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="auth-card-content">
      <h1>Recuperar contrasena</h1>
      <p>Te enviaremos un enlace para restablecer tu acceso.</p>

      <form className="auth-form" onSubmit={handleSubmit}>
        <label>
          Correo
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            autoComplete="email"
          />
        </label>

        {errorMessage ? <p className="form-error">{errorMessage}</p> : null}
        {successMessage ? <p className="form-success">{successMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={submitting}>
          {submitting ? 'Enviando...' : 'Enviar enlace'}
        </button>

        <Link to="/auth/login" className="auth-link-inline">
          Volver a iniciar sesion
        </Link>
      </form>
    </div>
  );
}
