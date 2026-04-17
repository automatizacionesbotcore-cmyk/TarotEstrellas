import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();

  const [token, setToken] = useState(searchParams.get('token') ?? '');
  const [email, setEmail] = useState(searchParams.get('email') ?? '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const passwordChecks = useMemo(
    () => ({
      minLength: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /\d/.test(password),
      symbol: /[\W_]/.test(password),
    }),
    [password],
  );

  const isPasswordStrong = Object.values(passwordChecks).every(Boolean);
  const passwordsMatch = password.length > 0 && password === passwordConfirmation;

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (submitting) {
      return;
    }

    if (!isPasswordStrong || !passwordsMatch) {
      setErrorMessage('Revisa la seguridad de la contrasena y confirma que ambas coincidan.');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    try {
      const response = await api.post<{ message?: string }>('/auth/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });

      setSuccessMessage(response.data?.message ?? 'Contrasena actualizada correctamente.');
      setPassword('');
      setPasswordConfirmation('');
    } catch (error: any) {
      const backendMessage = error?.response?.data?.message;
      const firstValidationError = Object.values(error?.response?.data?.errors ?? {})[0] as
        | string[]
        | undefined;
      const validationMessage = firstValidationError?.[0];

      setErrorMessage(backendMessage ?? validationMessage ?? 'No se pudo actualizar la contrasena.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="auth-card-content">
      <h1>Nueva contrasena</h1>
      <p>Define una contrasena segura para continuar.</p>

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
          Token de recuperacion
          <input
            type="text"
            value={token}
            onChange={(event) => setToken(event.target.value)}
            required
            autoComplete="off"
          />
        </label>

        <label>
          Nueva contrasena
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            autoComplete="new-password"
          />
          <ul className="password-rules" aria-live="polite">
            <li className={passwordChecks.minLength ? 'password-rule-ok' : 'password-rule-ko'}>
              Minimo 8 caracteres
            </li>
            <li className={passwordChecks.uppercase ? 'password-rule-ok' : 'password-rule-ko'}>
              Al menos una mayuscula
            </li>
            <li className={passwordChecks.lowercase ? 'password-rule-ok' : 'password-rule-ko'}>
              Al menos una minuscula
            </li>
            <li className={passwordChecks.number ? 'password-rule-ok' : 'password-rule-ko'}>
              Al menos un numero
            </li>
            <li className={passwordChecks.symbol ? 'password-rule-ok' : 'password-rule-ko'}>
              Al menos un simbolo
            </li>
          </ul>
        </label>

        <label>
          Confirmar contrasena
          <input
            type="password"
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            required
            autoComplete="new-password"
          />
          {passwordConfirmation.length > 0 ? (
            passwordsMatch ? (
              <span className="field-success">Las contrasenas coinciden.</span>
            ) : (
              <span className="field-error">Las contrasenas no coinciden.</span>
            )
          ) : (
            <span className="field-hint">Repite la nueva contrasena para confirmar.</span>
          )}
        </label>

        {errorMessage ? <p className="form-error">{errorMessage}</p> : null}
        {successMessage ? <p className="form-success">{successMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={submitting || !isPasswordStrong || !passwordsMatch}>
          {submitting ? 'Actualizando...' : 'Actualizar contrasena'}
        </button>

        <Link to="/auth/login" className="auth-link-inline">
          Volver a iniciar sesion
        </Link>
      </form>
    </div>
  );
}
