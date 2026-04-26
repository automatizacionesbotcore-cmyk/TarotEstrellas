import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';

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

type LegalModalType = 'terminos' | 'privacidad';
type LegalDocument  = { title: string; version: string; paragraphs: string[] };
type PasswordRule   = { label: string; passed: boolean };

const LEGAL_BASE_PATH = import.meta.env.PROD
  ? '/tarotEstrella/tarotestrellas/frontend/dist/'
  : '/';

const LEGAL_URLS: Record<LegalModalType, string> = {
  terminos:   `${LEGAL_BASE_PATH}legal/terminos.json`,
  privacidad: `${LEGAL_BASE_PATH}legal/privacidad.json`,
};

type Props = {
  onSuccess?: () => void;
  onSwitchMode?: () => void;
};

export function RegisterForm({ onSuccess, onSwitchMode }: Props) {
  const navigate = useNavigate();

  const [submitting,      setSubmitting]      = useState(false);
  const [successMessage,  setSuccessMessage]  = useState<string | null>(null);
  const [errorMessage,    setErrorMessage]    = useState<string | null>(null);
  const [prefijoCelular,  setPrefijoCelular]  = useState('+56');
  const [numeroCelular,   setNumeroCelular]   = useState('');
  const [activeLegal,     setActiveLegal]     = useState<LegalModalType | null>(null);
  const [scrolledBottom,  setScrolledBottom]  = useState(false);
  const [scrollProgress,  setScrollProgress]  = useState(0);
  const [legalDocs,       setLegalDocs]       = useState<Partial<Record<LegalModalType, LegalDocument>>>({});
  const [legalLoading,    setLegalLoading]    = useState(false);
  const [legalError,      setLegalError]      = useState<string | null>(null);

  const [form, setForm] = useState<RegisterPayload>({
    nombre: '', apellido: '', email: '', password: '', password_confirmation: '',
    telefono: '', telefono_pais: 'CL', pais_residencia: 'CL',
    acepta_terminos: false, acepta_privacidad: false, acepta_mayor_18: false,
    version_documento_terminos: 'v2.0', version_documento_privacidad: 'v2.0',
  });

  useEffect(() => {
    let cancelled = false;
    setLegalLoading(true);
    setLegalError(null);

    Promise.all(
      (Object.keys(LEGAL_URLS) as LegalModalType[]).map(async (type) => {
        const res = await fetch(LEGAL_URLS[type]);
        if (!res.ok) throw new Error(`No se pudo cargar ${type}`);
        return [type, await res.json() as LegalDocument] as const;
      }),
    )
      .then((entries) => {
        if (cancelled) return;
        const next: Partial<Record<LegalModalType, LegalDocument>> = {};
        entries.forEach(([t, d]) => { next[t] = d; });
        setLegalDocs(next);
      })
      .catch(() => { if (!cancelled) setLegalError('No se pudieron cargar los documentos legales.'); })
      .finally(() => { if (!cancelled) setLegalLoading(false); });

    return () => { cancelled = true; };
  }, []);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting || !allConsentAccepted || !isPasswordStrong || !passwordsMatch) {
      setErrorMessage('Revisa la seguridad de la contraseña y confirma que ambas coincidan.');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    const numericPhone = numeroCelular.replace(/\D/g, '');
    const payload: RegisterPayload = {
      ...form,
      telefono:       numericPhone ? `${prefijoCelular}${numericPhone}` : '',
      telefono_pais:  form.pais_residencia,
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

  const openLegal  = (type: LegalModalType) => { setActiveLegal(type); setScrolledBottom(false); setScrollProgress(0); };
  const closeLegal = () => { setActiveLegal(null); setScrolledBottom(false); setScrollProgress(0); };

  const acceptLegal = () => {
    if (!activeLegal || !legalConfig) return;
    if (activeLegal === 'terminos')
      setForm((p) => ({ ...p, acepta_terminos: true, version_documento_terminos: legalConfig.version }));
    if (activeLegal === 'privacidad')
      setForm((p) => ({ ...p, acepta_privacidad: true, version_documento_privacidad: legalConfig.version }));
    closeLegal();
  };

  const legalConfig = activeLegal ? legalDocs[activeLegal] ?? null : null;

  const passwordChecks: PasswordRule[] = [
    { label: 'Mínimo 8 caracteres',               passed: form.password.length >= 8 },
    { label: 'Al menos una mayúscula',             passed: /[A-Z]/.test(form.password) },
    { label: 'Al menos una minúscula',             passed: /[a-z]/.test(form.password) },
    { label: 'Al menos un número',                 passed: /\d/.test(form.password) },
    { label: 'Al menos un símbolo (!@#$%^&*)',    passed: /[^A-Za-z0-9]/.test(form.password) },
  ];

  const isPasswordStrong  = passwordChecks.every((r) => r.passed);
  const hasPasswordValue  = form.password.length > 0;
  const hasConfirmValue   = form.password_confirmation.length > 0;
  const passwordsMatch    = hasPasswordValue && hasConfirmValue && form.password === form.password_confirmation;
  const allConsentAccepted = form.acepta_terminos && form.acepta_privacidad && form.acepta_mayor_18;
  const canSubmit         = allConsentAccepted && isPasswordStrong && passwordsMatch && !submitting;

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
            <input type="text" required value={form.nombre}
              onChange={(e) => setForm((p) => ({ ...p, nombre: e.target.value }))} />
          </label>

          <label>
            Apellido
            <input type="text" value={form.apellido}
              onChange={(e) => setForm((p) => ({ ...p, apellido: e.target.value }))} />
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
            <input type="tel" inputMode="numeric" placeholder="912345678" value={numeroCelular}
              onChange={(e) => setNumeroCelular(e.target.value.replace(/[^\d\s()-]/g, ''))} />
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

        <div className="checkbox-row checkbox-row-legal">
          <input type="checkbox" checked={form.acepta_terminos} readOnly required />
          <button className="legal-trigger" type="button" onClick={() => openLegal('terminos')}>
            {form.acepta_terminos ? 'Términos aceptados' : 'Leer y aceptar términos y condiciones'}
          </button>
        </div>

        <div className="checkbox-row checkbox-row-legal">
          <input type="checkbox" checked={form.acepta_privacidad} readOnly required />
          <button className="legal-trigger" type="button" onClick={() => openLegal('privacidad')}>
            {form.acepta_privacidad ? 'Política aceptada' : 'Leer y aceptar política de privacidad'}
          </button>
        </div>

        <label className="checkbox-row">
          <input type="checkbox" checked={form.acepta_mayor_18} required
            onChange={(e) => setForm((p) => ({ ...p, acepta_mayor_18: e.target.checked }))} />
          Confirmo que soy mayor de 18 años
        </label>

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

      {/* Legal modals — rendered inside the same component so they work in modal context too */}
      {legalConfig ? (
        <div className="legal-modal-backdrop" role="presentation">
          <section className="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title">
            <header className="legal-modal-header">
              <h2 id="legal-modal-title">{legalConfig.title}</h2>
              <button type="button" className="legal-close" onClick={closeLegal} aria-label="Cerrar">Cerrar</button>
            </header>
            <p className="legal-version">Versión {legalConfig.version}</p>
            <div className="legal-scroll"
              onScroll={(e) => {
                const t = e.currentTarget;
                const max = t.scrollHeight - t.clientHeight;
                if (max <= 0) { setScrollProgress(100); setScrolledBottom(true); return; }
                const pct = Math.min(100, Math.round((t.scrollTop / max) * 100));
                setScrollProgress(pct);
                if (pct >= 99) setScrolledBottom(true);
              }}>
              {legalConfig.paragraphs.map((p) => <p key={p}>{p}</p>)}
            </div>
            <footer className="legal-modal-actions">
              <span className="legal-hint">
                {scrolledBottom
                  ? `Lectura completa (${scrollProgress}%).`
                  : `Progreso: ${scrollProgress}%. Desplázate hasta el final para aceptar.`}
              </span>
              <button type="button" className="btn-primary" disabled={!scrolledBottom} onClick={acceptLegal}>
                Aceptar
              </button>
            </footer>
          </section>
        </div>
      ) : null}

      {activeLegal && !legalConfig ? (
        <div className="legal-modal-backdrop" role="presentation">
          <section className="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title-loading">
            <header className="legal-modal-header">
              <h2 id="legal-modal-title-loading">Documento legal</h2>
              <button type="button" className="legal-close" onClick={closeLegal} aria-label="Cerrar">Cerrar</button>
            </header>
            <p className="legal-hint">
              {legalLoading ? 'Cargando documento...' : legalError ?? 'Documento no disponible.'}
            </p>
          </section>
        </div>
      ) : null}
    </>
  );
}
