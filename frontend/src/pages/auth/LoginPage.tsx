import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLocation, useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';

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

export function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const setSession = useAuthStore((state) => state.setSession);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
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
      navigate(fromPath, { replace: true });
    } catch (error: any) {
      const backendMessage = error?.response?.data?.message;
      const validationMessage = error?.response?.data?.errors?.email?.[0];
      setErrorMessage(backendMessage ?? validationMessage ?? 'No se pudo iniciar sesion. Revisa tus credenciales.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="auth-card">
      <h1>Iniciar sesion</h1>
      <p>Ingreso rapido para clientes y administracion.</p>

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

        <label>
          Contrasena
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            autoComplete="current-password"
          />
        </label>

        {errorMessage ? <p className="form-error">{errorMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={submitting}>
          {submitting ? 'Ingresando...' : 'Ingresar'}
        </button>

        <Link to="/auth/forgot-password" className="auth-link-inline">
          Olvide mi contrasena
        </Link>
      </form>

      <p className="auth-footer-action">
        <Link to="/auth/register" className="btn-secondary auth-secondary-cta">
          Crear cuenta
        </Link>
      </p>
    </div>
  );
}
