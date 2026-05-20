import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { sanitizeNombre, sanitizeDigits, validateTelefonoCL, validateTelefonoGenerico } from '../../lib/formValidators';
import { LegalConsentBlock } from './LegalConsentBlock';

function googleAuthUrl() {
  return `${api.defaults.baseURL}/auth/google`;
}

type CountryConfig = { code: string; name: string; dialCode: string };

const COUNTRY_OPTIONS: CountryConfig[] = [
  { code: 'CL', name: 'Chile',          dialCode: '+56'  },
  { code: 'AR', name: 'Argentina',       dialCode: '+54'  },
  { code: 'BO', name: 'Bolivia',         dialCode: '+591' },
  { code: 'BR', name: 'Brasil',          dialCode: '+55'  },
  { code: 'CO', name: 'Colombia',        dialCode: '+57'  },
  { code: 'EC', name: 'Ecuador',         dialCode: '+593' },
  { code: 'ES', name: 'España',          dialCode: '+34'  },
  { code: 'MX', name: 'México',          dialCode: '+52'  },
  { code: 'PE', name: 'Perú',            dialCode: '+51'  },
  { code: 'US', name: 'Estados Unidos',  dialCode: '+1'   },
  { code: 'UY', name: 'Uruguay',         dialCode: '+598' },
  { code: 'VE', name: 'Venezuela',       dialCode: '+58'  },
];

const COUNTRY_BY_CODE = new Map(COUNTRY_OPTIONS.map((c) => [c.code, c]));

type RegisterPayload = {
  nombre: string; apellido?: string; email: string;
  password: string; password_confirmation: string;
  telefono?: string; telefono_pais?: string; pais_residencia?: string;
  acepta_terminos: boolean; acepta_privacidad: boolean; acepta_mayor_18: boolean;
  version_documento_terminos: string; version_documento_privacidad: string;
};

type PasswordRule = { label: string; passed: boolean };

type Props = {
  onSuccess?: () => void;
  onSwitchMode?: () => void;
};

export function RegisterForm({ onSuccess, onSwitchMode }: Props) {
  const navigate = useNavigate();

  const [submitting,     setSubmitting]     = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage,   setErrorMessage]   = useState<string | null>(null);
  const [prefijoCelular, setPrefijoCelular] = useState('+56');
  const [numeroCelular,  setNumeroCelular]  = useState('');

  const [form, setForm] = useState<RegisterPayload>({
    nombre: '', apellido: '', email: '', password: '', password_confirmation: '',
    telefono: '', telefono_pais: 'CL', pais_residencia: 'CL',
    acepta_terminos: false, acepta_privacidad: false, acepta_mayor_18: false,
    version_documento_terminos: 'v3.0 — 2026', version_documento_privacidad: 'v3.0 — 2026',
  });

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) {
      return;
    }

    if (!form.acepta_terminos || !form.acepta_privacidad) {
      const missing = [
        !form.acepta_terminos ? 'términos y condiciones' : null,
        !form.acepta_privacidad ? 'política de privacidad' : null,
      ].filter(Boolean);

      setErrorMessage(`Debes leer y aceptar ${missing.join(' y ')} antes de crear tu cuenta.`);
      return;
    }

    if (!form.acepta_mayor_18) {
      setErrorMessage('Debes confirmar que eres mayor de 18 años para crear tu cuenta.');
      return;
    }

    if (!isPasswordStrong || !passwordsMatch) {
      setErrorMessage('Revisa la seguridad de la contraseña y confirma que ambas coincidan.');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    const numericPhone = numeroCelular.replace(/\D/g, '');
    const payload: any = {
      ...form,
      telefono:      numericPhone ? `${prefijoCelular}${numericPhone}` : '',
      telefono_pais: form.pais_residencia,
      consent_terminos: form.acepta_terminos,
      consent_privacidad: form.acepta_privacidad,
      consent_mayor_18: form.acepta_mayor_18,
      version_documento: form.version_documento_terminos,
    };

    try {
      await api.post('/auth/register', payload);
      setSuccessMessage('Registro exitoso. Ahora inicia sesión para continuar.');
      setTimeout(() => {
        if (onSuccess) onSuccess();
        else navigate('/auth/login', { replace: true });
      }, 1000);
    } catch (error: any) {
      const msg = error?.response?.data?.message;
      const errs = error?.response?.data?.errors;
      const first = errs ? (Object.values(errs)[0] as string[])?.[0] : null;
      setErrorMessage(msg ?? first ?? 'No se pudo crear la cuenta.');
    } finally {
      setSubmitting(false);
    }
  };

  const passwordChecks: PasswordRule[] = [
    { label: 'Mínimo 8 caracteres',            passed: form.password.length >= 8 },
    { label: 'Al menos una mayúscula',          passed: /[A-Z]/.test(form.password) },
    { label: 'Al menos una minúscula',          passed: /[a-z]/.test(form.password) },
    { label: 'Al menos un número',              passed: /\d/.test(form.password) },
    { label: 'Al menos un símbolo (!@#$%^&*)', passed: /[^A-Za-z0-9]/.test(form.password) },
  ];

  const isPasswordStrong   = passwordChecks.every((r) => r.passed);
  const hasPasswordValue   = form.password.length > 0;
  const hasConfirmValue    = form.password_confirmation.length > 0;
  const passwordsMatch     = hasPasswordValue && hasConfirmValue && form.password === form.password_confirmation;
  const canSubmit          = !submitting;

  return (
    <>
      <h2 className="auth-form-title">Crear cuenta</h2>
      <p className="auth-form-subtitle">Crea tu cuenta para agendar y guardar tus consultas.</p>

      <a href={googleAuthUrl()} className="btn-google" role="button">
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615Z"/>
          <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18Z"/>
          <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332Z"/>
          <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58Z"/>
        </svg>
        Registrarse con Google
      </a>

      <div className="auth-divider"><span>o regístrate con email</span></div>

      <form className="auth-form" onSubmit={handleSubmit}>
        <div className="auth-grid">
          <label>
            Nombre
            <input type="text" required maxLength={60}
              pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'\-]{2,60}"
              title="Solo letras (2 a 60 caracteres)"
              value={form.nombre}
              onChange={(e) => setForm((p) => ({ ...p, nombre: sanitizeNombre(e.target.value, 60) }))} />
          </label>

          <label>
            Apellido
            <input type="text" maxLength={60}
              pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'\-]{0,60}"
              title="Solo letras"
              value={form.apellido}
              onChange={(e) => setForm((p) => ({ ...p, apellido: sanitizeNombre(e.target.value, 60) }))} />
          </label>

          <label className="auth-span-2">
            Correo
            <input type="email" required value={form.email}
              onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} />
          </label>

          <label>
            País de residencia
            <select value={form.pais_residencia}
              onChange={(e) => {
                const config = COUNTRY_BY_CODE.get(e.target.value);
                setForm((p) => ({ ...p, pais_residencia: e.target.value, telefono_pais: e.target.value }));
                setPrefijoCelular(config?.dialCode ?? '+56');
              }}>
              {COUNTRY_OPTIONS.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
            </select>
          </label>

          <label>
            Prefijo
            <input type="text" value={prefijoCelular} readOnly />
          </label>

          <label className="auth-phone-field">
            Celular
            <input type="tel" inputMode="numeric"
              placeholder={prefijoCelular === '+56' ? '912345678' : '3001234567'}
              value={numeroCelular}
              minLength={prefijoCelular === '+56' ? 9 : 7}
              maxLength={prefijoCelular === '+56' ? 9 : 15}
              pattern={prefijoCelular === '+56' ? '9[0-9]{8}' : '[0-9]{7,15}'}
              title={prefijoCelular === '+56' ? 'En Chile el celular comienza con 9 y tiene 9 dígitos' : 'Solo dígitos (7 a 15)'}
              onChange={(e) => setNumeroCelular(sanitizeDigits(e.target.value, prefijoCelular === '+56' ? 9 : 15))} />
            {numeroCelular.length > 0 && (() => {
              const err = prefijoCelular === '+56' ? validateTelefonoCL(numeroCelular) : validateTelefonoGenerico(numeroCelular);
              return err ? <span className="field-error">{err}</span> : null;
            })()}
          </label>

          <div className="auth-password-row auth-span-2">
            <label className="auth-password-field">
              Contraseña
              <input type="password" required minLength={8} autoComplete="new-password"
                value={form.password}
                onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))} />
              {hasPasswordValue ? (
                <ul className="password-rules" aria-live="polite">
                  {passwordChecks.map((r) => (
                    <li key={r.label} className={r.passed ? 'password-rule-ok' : 'password-rule-ko'}>
                      {r.passed ? 'Cumple:' : 'Falta:'} {r.label}
                    </li>
                  ))}
                </ul>
              ) : (
                <span className="field-hint">Usa 8+ caracteres, mayúscula, minúscula, número y símbolo.</span>
              )}
            </label>

            <label className="auth-password-field">
              Confirmar contraseña
              <input type="password" required minLength={8} autoComplete="new-password"
                value={form.password_confirmation}
                onChange={(e) => setForm((p) => ({ ...p, password_confirmation: e.target.value }))} />
              {hasConfirmValue ? (
                passwordsMatch
                  ? <span className="field-success">Las contraseñas coinciden.</span>
                  : <span className="field-error">Las contraseñas no coinciden.</span>
              ) : (
                <span className="field-hint">Repite la misma contraseña para confirmar.</span>
              )}
            </label>
          </div>
        </div>

        <LegalConsentBlock
          terminosAceptados={form.acepta_terminos}
          privacidadAceptada={form.acepta_privacidad}
          mayor18Aceptado={form.acepta_mayor_18}
          onTerminosAccepted={(version) => setForm((p) => ({ ...p, acepta_terminos: true, version_documento_terminos: version }))}
          onPrivacidadAccepted={(version) => setForm((p) => ({ ...p, acepta_privacidad: true, version_documento_privacidad: version }))}
          onMayor18Change={(val) => setForm((p) => ({ ...p, acepta_mayor_18: val }))}
        />

        {errorMessage   ? <p className="form-error">{errorMessage}</p>     : null}
        {successMessage ? <p className="form-success">{successMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={!canSubmit} aria-disabled={!canSubmit}>
          {submitting ? 'Creando cuenta...' : 'Crear cuenta'}
        </button>
      </form>

      <p className="auth-footer-action">
        {onSwitchMode ? (
          <button type="button" className="btn-secondary auth-secondary-cta" onClick={onSwitchMode}>
            Ya tengo cuenta
          </button>
        ) : (
          <Link to="/auth/login" className="btn-secondary auth-secondary-cta">
            Ya tengo cuenta
          </Link>
        )}
      </p>
    </>
  );
}
