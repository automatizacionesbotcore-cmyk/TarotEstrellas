import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLocation, useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';

function googleAuthUrl() {
  return `${api.defaults.baseURL}/auth/google`;
}

type LoginResponse = {
  token: string;
  user: {
    uuid: string;
    email: string;
    nombre?: string | null;
    email_verified_at?: string | null;
    roles: string[];
  };
};

type Props = {
  onSuccess?: () => void;
  onSwitchMode?: () => void;
  onForgotPassword?: () => void;
};

export function LoginForm({ onSuccess, onSwitchMode, onForgotPassword }: Props) {
  const navigate   = useNavigate();
  const location   = useLocation();
  const setSession = useAuthStore((state) => state.setSession);

  const [email,        setEmail]        = useState('');
  const [password,     setPassword]     = useState('');
  const [submitting,   setSubmitting]   = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const fromPath = (location.state as { from?: string } | null)?.from ?? '/app';

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitting(true);
    setErrorMessage(null);

    try {
      const response = await api.post<LoginResponse>('/auth/login', {
        email,
        password,
        device_name: navigator.userAgent,
      });

      setSession(response.data.token, response.data.user);
      if (onSuccess) onSuccess();
      else navigate(fromPath, { replace: true });
    } catch (error: any) {
      const backendMessage    = error?.response?.data?.message;
      const validationMessage = error?.response?.data?.errors?.email?.[0];
      setErrorMessage(
        backendMessage ?? validationMessage ?? 'No se pudo iniciar sesión. Revisa tus credenciales.',
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <h2 className="auth-form-title">Iniciar sesión</h2>
      <p className="auth-form-subtitle">Ingreso rápido para clientes y administración.</p>

      <a href={googleAuthUrl()} className="btn-google" role="button">
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615Z"/>
          <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18Z"/>
          <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332Z"/>
          <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58Z"/>
        </svg>
        Continuar con Google
      </a>

      <div className="auth-divider"><span>o</span></div>

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

        <label>
          Contraseña
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="current-password"
          />
        </label>

        {errorMessage ? <p className="form-error">{errorMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={submitting}>
          {submitting ? 'Ingresando...' : 'Ingresar'}
        </button>

        {onForgotPassword ? (
          <button type="button" className="auth-link-inline" onClick={onForgotPassword}>
            Olvidé mi contraseña
          </button>
        ) : (
          <Link to="/auth/forgot-password" className="auth-link-inline">
            Olvidé mi contraseña
          </Link>
        )}
        <Link to="/soporte" className="auth-link-inline">
          ¿Problemas con tu cuenta o con la plataforma?
        </Link>
      </form>

      <p className="auth-footer-action">
        {onSwitchMode ? (
          <button type="button" className="btn-secondary auth-secondary-cta" onClick={onSwitchMode}>
            Crear cuenta
          </button>
        ) : (
          <Link to="/auth/register" className="btn-secondary auth-secondary-cta">
            Crear cuenta
          </Link>
        )}
      </p>
    </>
  );
}
