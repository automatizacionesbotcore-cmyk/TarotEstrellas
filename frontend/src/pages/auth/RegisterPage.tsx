import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';

type CountryConfig = {
  code: string;
  name: string;
  dialCode: string;
};

const COUNTRY_OPTIONS: CountryConfig[] = [
  { code: 'CL', name: 'Chile', dialCode: '+56' },
  { code: 'AR', name: 'Argentina', dialCode: '+54' },
  { code: 'BO', name: 'Bolivia', dialCode: '+591' },
  { code: 'BR', name: 'Brasil', dialCode: '+55' },
  { code: 'CO', name: 'Colombia', dialCode: '+57' },
  { code: 'EC', name: 'Ecuador', dialCode: '+593' },
  { code: 'ES', name: 'Espana', dialCode: '+34' },
  { code: 'MX', name: 'Mexico', dialCode: '+52' },
  { code: 'PE', name: 'Peru', dialCode: '+51' },
  { code: 'US', name: 'Estados Unidos', dialCode: '+1' },
  { code: 'UY', name: 'Uruguay', dialCode: '+598' },
  { code: 'VE', name: 'Venezuela', dialCode: '+58' },
];

const COUNTRY_BY_CODE = new Map(COUNTRY_OPTIONS.map((country) => [country.code, country]));

type RegisterPayload = {
  nombre: string;
  apellido?: string;
  email: string;
  password: string;
  password_confirmation: string;
  telefono?: string;
  telefono_pais?: string;
  pais_residencia?: string;
  acepta_terminos: boolean;
  acepta_privacidad: boolean;
  acepta_mayor_18: boolean;
  version_documento_terminos: string;
  version_documento_privacidad: string;
};

type LegalModalType = 'terminos' | 'privacidad';

type LegalDocument = {
  title: string;
  version: string;
  paragraphs: string[];
};

type PasswordRuleCheck = {
  label: string;
  passed: boolean;
};

const LEGAL_BASE_PATH = import.meta.env.PROD ? '/tarotEstrella/tarotestrellas/frontend/dist/' : '/';

const LEGAL_DOCUMENT_URLS: Record<LegalModalType, string> = {
  terminos: `${LEGAL_BASE_PATH}legal/terminos.json`,
  privacidad: `${LEGAL_BASE_PATH}legal/privacidad.json`,
};

export function RegisterPage() {
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [prefijoCelular, setPrefijoCelular] = useState('+56');
  const [numeroCelular, setNumeroCelular] = useState('');
  const [activeLegalModal, setActiveLegalModal] = useState<LegalModalType | null>(null);
  const [hasScrolledToBottom, setHasScrolledToBottom] = useState(false);
  const [scrollProgress, setScrollProgress] = useState(0);
  const [legalDocuments, setLegalDocuments] = useState<Partial<Record<LegalModalType, LegalDocument>>>({});
  const [legalLoading, setLegalLoading] = useState(false);
  const [legalError, setLegalError] = useState<string | null>(null);

  const [form, setForm] = useState<RegisterPayload>({
    nombre: '',
    apellido: '',
    email: '',
    password: '',
    password_confirmation: '',
    telefono: '',
    telefono_pais: 'CL',
    pais_residencia: 'CL',
    acepta_terminos: false,
    acepta_privacidad: false,
    acepta_mayor_18: false,
    version_documento_terminos: 'v2.0',
    version_documento_privacidad: 'v2.0',
  });

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (submitting || !allConsentAccepted || !isPasswordStrong || !passwordsMatch) {
      setErrorMessage('Revisa la seguridad de la contrasena y confirma que ambas coincidan.');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    const numericPhone = numeroCelular.replace(/\D/g, '');
    const telefonoCompleto = numericPhone ? `${prefijoCelular}${numericPhone}` : '';

    const payload: RegisterPayload = {
      ...form,
      telefono: telefonoCompleto,
      telefono_pais: form.pais_residencia,
    };

    try {
      await api.post('/auth/register', payload);
      setSuccessMessage('Registro exitoso. Ahora inicia sesion para completar tu reserva.');
      setTimeout(() => navigate('/auth/login', { replace: true }), 1000);
    } catch (error: any) {
      const backendMessage = error?.response?.data?.message;
      const validationErrors = error?.response?.data?.errors;

      const firstValidationError = validationErrors
        ? Object.values(validationErrors)[0] instanceof Array
          ? (Object.values(validationErrors)[0] as string[])[0]
          : null
        : null;

      setErrorMessage(backendMessage ?? firstValidationError ?? 'No se pudo crear la cuenta.');
    } finally {
      setSubmitting(false);
    }
  };

  useEffect(() => {
    let cancelled = false;

    const loadLegalDocuments = async () => {
      setLegalLoading(true);
      setLegalError(null);

      try {
        const entries = await Promise.all(
          (Object.keys(LEGAL_DOCUMENT_URLS) as LegalModalType[]).map(async (type) => {
            const response = await fetch(LEGAL_DOCUMENT_URLS[type]);

            if (!response.ok) {
              throw new Error(`No se pudo cargar ${type}`);
            }

            const document = (await response.json()) as LegalDocument;
            return [type, document] as const;
          }),
        );

        if (cancelled) {
          return;
        }

        const nextDocuments: Partial<Record<LegalModalType, LegalDocument>> = {};

        entries.forEach(([type, document]) => {
          nextDocuments[type] = document;
        });

        setLegalDocuments(nextDocuments);
      } catch {
        if (!cancelled) {
          setLegalError('No se pudieron cargar los documentos legales. Intenta nuevamente en unos minutos.');
        }
      } finally {
        if (!cancelled) {
          setLegalLoading(false);
        }
      }
    };

    loadLegalDocuments();

    return () => {
      cancelled = true;
    };
  }, []);

  const openLegalModal = (type: LegalModalType) => {
    setActiveLegalModal(type);
    setHasScrolledToBottom(false);
    setScrollProgress(0);
  };

  const closeLegalModal = () => {
    setActiveLegalModal(null);
    setHasScrolledToBottom(false);
    setScrollProgress(0);
  };

  const acceptLegalDocument = () => {
    if (!activeLegalModal || !legalConfig) {
      return;
    }

    if (activeLegalModal === 'terminos') {
      setForm((prev) => ({
        ...prev,
        acepta_terminos: true,
        version_documento_terminos: legalConfig.version,
      }));
    }

    if (activeLegalModal === 'privacidad') {
      setForm((prev) => ({
        ...prev,
        acepta_privacidad: true,
        version_documento_privacidad: legalConfig.version,
      }));
    }

    closeLegalModal();
  };

  const legalConfig = activeLegalModal ? legalDocuments[activeLegalModal] ?? null : null;
  const passwordChecks: PasswordRuleCheck[] = [
    { label: 'Minimo 8 caracteres', passed: form.password.length >= 8 },
    { label: 'Al menos una mayuscula', passed: /[A-Z]/.test(form.password) },
    { label: 'Al menos una minuscula', passed: /[a-z]/.test(form.password) },
    { label: 'Al menos un numero', passed: /\d/.test(form.password) },
    { label: 'Al menos un simbolo (!@#$%^&*)', passed: /[^A-Za-z0-9]/.test(form.password) },
  ];
  const isPasswordStrong = passwordChecks.every((rule) => rule.passed);
  const hasPasswordValue = form.password.length > 0;
  const hasPasswordConfirmation = form.password_confirmation.length > 0;
  const passwordsMatch = hasPasswordValue && hasPasswordConfirmation && form.password === form.password_confirmation;
  const allConsentAccepted = form.acepta_terminos && form.acepta_privacidad && form.acepta_mayor_18;
  const canSubmit = allConsentAccepted && isPasswordStrong && passwordsMatch && !submitting;

  return (
    <div className="auth-card">
      <h1>Registro</h1>
      <p>Crea tu cuenta para agendar y guardar tus consultas.</p>

      <form className="auth-form" onSubmit={handleSubmit}>
        <div className="auth-grid">
          <label>
            Nombre
            <input
              type="text"
              required
              value={form.nombre}
              onChange={(event) => setForm((prev) => ({ ...prev, nombre: event.target.value }))}
            />
          </label>

          <label>
            Apellido
            <input
              type="text"
              value={form.apellido}
              onChange={(event) => setForm((prev) => ({ ...prev, apellido: event.target.value }))}
            />
          </label>

          <label className="auth-span-2">
            Correo
            <input
              type="email"
              required
              value={form.email}
              onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))}
            />
          </label>

          <label>
            Pais de residencia
            <select
              value={form.pais_residencia}
              onChange={(event) => {
                const selectedCountry = event.target.value;
                const config = COUNTRY_BY_CODE.get(selectedCountry);

                setForm((prev) => ({
                  ...prev,
                  pais_residencia: selectedCountry,
                  telefono_pais: selectedCountry,
                }));

                setPrefijoCelular(config?.dialCode ?? '+56');
              }}
            >
              {COUNTRY_OPTIONS.map((country) => (
                <option key={country.code} value={country.code}>
                  {country.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            Prefijo
            <input type="text" value={prefijoCelular} readOnly />
          </label>

          <label className="auth-phone-field">
            Celular
            <input
              type="tel"
              inputMode="numeric"
              placeholder="912345678"
              value={numeroCelular}
              onChange={(event) => setNumeroCelular(event.target.value.replace(/[^\d\s()-]/g, ''))}
            />
          </label>

          <div className="auth-password-row auth-span-2">
            <label className="auth-password-field">
              Contrasena
              <input
                type="password"
                required
                minLength={8}
                autoComplete="new-password"
                value={form.password}
                onChange={(event) => setForm((prev) => ({ ...prev, password: event.target.value }))}
              />
              {hasPasswordValue ? (
                <ul className="password-rules" aria-live="polite">
                  {passwordChecks.map((rule) => (
                    <li key={rule.label} className={rule.passed ? 'password-rule-ok' : 'password-rule-ko'}>
                      {rule.passed ? 'Cumple:' : 'Falta:'} {rule.label}
                    </li>
                  ))}
                </ul>
              ) : (
                <span className="field-hint">Usa 8+ caracteres, mayuscula, minuscula, numero y simbolo.</span>
              )}
            </label>

            <label className="auth-password-field">
              Confirmar contrasena
              <input
                type="password"
                required
                minLength={8}
                autoComplete="new-password"
                value={form.password_confirmation}
                onChange={(event) => setForm((prev) => ({ ...prev, password_confirmation: event.target.value }))}
              />
              {hasPasswordConfirmation ? (
                passwordsMatch ? (
                  <span className="field-success">Las contrasenas coinciden.</span>
                ) : (
                  <span className="field-error">Las contrasenas no coinciden.</span>
                )
              ) : (
                <span className="field-hint">Repite la misma contrasena para confirmar.</span>
              )}
            </label>
          </div>
        </div>

        <div className="checkbox-row checkbox-row-legal">
          <input
            type="checkbox"
            checked={form.acepta_terminos}
            readOnly
            required
          />
          <button className="legal-trigger" type="button" onClick={() => openLegalModal('terminos')}>
            {form.acepta_terminos ? 'Terminos aceptados' : 'Leer y aceptar terminos y condiciones'}
          </button>
        </div>

        <div className="checkbox-row checkbox-row-legal">
          <input
            type="checkbox"
            checked={form.acepta_privacidad}
            readOnly
            required
          />
          <button className="legal-trigger" type="button" onClick={() => openLegalModal('privacidad')}>
            {form.acepta_privacidad ? 'Politica aceptada' : 'Leer y aceptar politica de privacidad'}
          </button>
        </div>

        <label className="checkbox-row">
          <input
            type="checkbox"
            checked={form.acepta_mayor_18}
            onChange={(event) => setForm((prev) => ({ ...prev, acepta_mayor_18: event.target.checked }))}
            required
          />
          Confirmo que soy mayor de 18 anos
        </label>

        {errorMessage ? <p className="form-error">{errorMessage}</p> : null}
        {successMessage ? <p className="form-success">{successMessage}</p> : null}

        <button className="btn-primary" type="submit" disabled={!canSubmit} aria-disabled={!canSubmit}>
          {submitting ? 'Creando cuenta...' : 'Crear cuenta'}
        </button>
      </form>

      <p className="auth-footer-action">
        <Link to="/auth/login" className="btn-secondary auth-secondary-cta">
          Ya tengo cuenta
        </Link>
      </p>

      {legalConfig ? (
        <div className="legal-modal-backdrop" role="presentation">
          <section
            className="legal-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="legal-modal-title"
          >
            <header className="legal-modal-header">
              <h2 id="legal-modal-title">{legalConfig.title}</h2>
              <button type="button" className="legal-close" onClick={closeLegalModal} aria-label="Cerrar modal">
                Cerrar
              </button>
            </header>

            <p className="legal-version">Version {legalConfig.version}</p>

            <div
              className="legal-scroll"
              onScroll={(event) => {
                const target = event.currentTarget;
                const maxScroll = target.scrollHeight - target.clientHeight;

                if (maxScroll <= 0) {
                  setScrollProgress(100);
                  setHasScrolledToBottom(true);
                  return;
                }

                const nextProgress = Math.min(100, Math.max(0, Math.round((target.scrollTop / maxScroll) * 100)));
                const reachedBottom = nextProgress >= 99;

                setScrollProgress(nextProgress);

                if (reachedBottom) {
                  setHasScrolledToBottom(true);
                }
              }}
            >
              {legalConfig.paragraphs.map((paragraph) => (
                <p key={paragraph}>{paragraph}</p>
              ))}
            </div>

            <footer className="legal-modal-actions">
              <span className="legal-hint">
                {hasScrolledToBottom
                  ? `Lectura completa detectada (${scrollProgress}%).`
                  : `Progreso de lectura: ${scrollProgress}%. Desplazate hasta el final para habilitar Aceptar.`}
              </span>
              <button type="button" className="btn-primary" disabled={!hasScrolledToBottom} onClick={acceptLegalDocument}>
                Aceptar
              </button>
            </footer>
          </section>
        </div>
      ) : null}

      {activeLegalModal && !legalConfig ? (
        <div className="legal-modal-backdrop" role="presentation">
          <section className="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title-loading">
            <header className="legal-modal-header">
              <h2 id="legal-modal-title-loading">Documento legal</h2>
              <button type="button" className="legal-close" onClick={closeLegalModal} aria-label="Cerrar modal">
                Cerrar
              </button>
            </header>
            <p className="legal-hint">{legalLoading ? 'Cargando documento...' : legalError ?? 'Documento no disponible.'}</p>
          </section>
        </div>
      ) : null}
    </div>
  );
}
