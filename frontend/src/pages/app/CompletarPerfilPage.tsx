import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';
import {
  sanitizeNombre,
  sanitizeDigits,
  validateTelefonoCL,
  validateTelefonoGenerico,
} from '../../lib/formValidators';
import { LegalConsentBlock } from '../../components/forms/LegalConsentBlock';

const PAISES_RESIDENCIA = [
  'Argentina', 'Bolivia', 'Brasil', 'Chile', 'Colombia', 'Costa Rica', 'Cuba',
  'Ecuador', 'El Salvador', 'España', 'Estados Unidos', 'Guatemala', 'Honduras',
  'México', 'Nicaragua', 'Panamá', 'Paraguay', 'Perú', 'Puerto Rico',
  'República Dominicana', 'Uruguay', 'Venezuela', 'Otro',
];

const CODIGOS_PAIS = [
  { code: '+54', label: '+54 AR' },
  { code: '+591', label: '+591 BO' },
  { code: '+55', label: '+55 BR' },
  { code: '+56', label: '+56 CL' },
  { code: '+57', label: '+57 CO' },
  { code: '+506', label: '+506 CR' },
  { code: '+593', label: '+593 EC' },
  { code: '+503', label: '+503 SV' },
  { code: '+34', label: '+34 ES' },
  { code: '+1', label: '+1 US/CA' },
  { code: '+502', label: '+502 GT' },
  { code: '+504', label: '+504 HN' },
  { code: '+52', label: '+52 MX' },
  { code: '+505', label: '+505 NI' },
  { code: '+507', label: '+507 PA' },
  { code: '+595', label: '+595 PY' },
  { code: '+51', label: '+51 PE' },
  { code: '+1787', label: '+1 PR' },
  { code: '+1809', label: '+1 DO' },
  { code: '+598', label: '+598 UY' },
  { code: '+58', label: '+58 VE' },
];

export function CompletarPerfilPage() {
  const user = useAuthStore((s) => s.user);
  const refreshUser = useAuthStore((s) => s.refreshUser);
  const navigate = useNavigate();

  const [nombre, setNombre] = useState('');
  const [apellido, setApellido] = useState('');
  const [telefonoPais, setTelefonoPais] = useState('+56');
  const [telefono, setTelefono] = useState('');
  const [paisResidencia, setPaisResidencia] = useState('');
  const [fechaNacimiento, setFechaNacimiento] = useState('');
  const [genero, setGenero] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const [terminosAceptados,  setTerminosAceptados]  = useState(false);
  const [privacidadAceptada, setPrivacidadAceptada] = useState(false);
  const [mayor18Aceptado,    setMayor18Aceptado]    = useState(false);
  const [versionDocumento,   setVersionDocumento]   = useState('v3.0 — 2026');

  useEffect(() => {
    if (user?.nombre && !nombre) setNombre(user.nombre);
  }, [user]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    // Validación cliente: teléfono según país
    const telError = telefonoPais === '+56'
      ? validateTelefonoCL(telefono)
      : validateTelefonoGenerico(telefono);
    if (telError) {
      setErrors({ telefono: [telError] });
      setErrorMessage(telError);
      return;
    }

    if (!terminosAceptados || !privacidadAceptada || !mayor18Aceptado) {
      setErrorMessage('Debes aceptar los términos, la política de privacidad y confirmar que eres mayor de 18 años.');
      return;
    }

    setSubmitting(true);
    setErrors({});
    setErrorMessage(null);
    setSuccessMessage(null);
    try {
      await api.post('/me/completar-perfil', {
        nombre,
        apellido,
        telefono,
        telefono_pais: telefonoPais,
        pais_residencia: paisResidencia,
        fecha_nacimiento_publica: fechaNacimiento,
        genero: genero || null,
        consent_terminos: terminosAceptados,
        consent_privacidad: privacidadAceptada,
        version_documento: versionDocumento,
        acepta_mayor_18: mayor18Aceptado,
      });
      await refreshUser();
      setSuccessMessage('¡Perfil completado! Las estrellas ya conocen tu camino ✨');
      toast.success('¡Perfil completado! ✨');
      setTimeout(() => navigate('/app', { replace: true }), 900);
    } catch (err: unknown) {
      const e = err as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } };
      if (e?.response?.data?.errors) {
        setErrors(e.response.data.errors);
        setErrorMessage('Revisa los campos marcados.');
      } else {
        const msg = e?.response?.data?.message
          ?? `No pudimos guardar tu perfil${e?.response?.status ? ` (${e.response.status})` : ''}. Intenta de nuevo.`;
        setErrorMessage(msg);
        toast.error(msg);
      }
    } finally {
      setSubmitting(false);
    }
  }

  const nombreCorto = (user?.nombre ?? '').split(' ')[0];
  const saludo = nombreCorto
    ? `Hola ${nombreCorto}, soy Astrea`
    : 'Hola viajero, soy Astrea';

  return (
    <main className="page-content asistente-page">
      <div className="asistente-page__hero" style={{ marginBottom: '1.5rem' }}>
        <div className="asistente-page__cosmos" aria-hidden="true" />
        <div className="asistente-page__stars" aria-hidden="true">
          <span /><span /><span /><span /><span /><span />
          <span /><span /><span /><span /><span /><span />
        </div>
        <div className="asistente-page__hero-content">
          <div className="asistente-page__avatar" aria-hidden="true">
            <span className="asistente-page__avatar-halo" />
            <span className="asistente-page__avatar-halo asistente-page__avatar-halo--delay" />
            <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
              <path d="M12 2.5l2.6 6.5 7 .6-5.3 4.6 1.7 6.8L12 17.4l-6 3.6 1.7-6.8L2.4 9.6l7-.6L12 2.5z" />
            </svg>
          </div>
          <div>
            <h1 className="asistente-page__title">{saludo} ✨</h1>
            <p className="asistente-page__subtitle">
              Necesito unos datos más para acompañarte mejor en tu camino. Solo tomará un minuto.
            </p>
          </div>
        </div>
      </div>

      <div className="auth-card">
        <div className="auth-card-content">
          {errorMessage && (
            <div className="form-error" role="alert">
              <strong>✦ </strong>{errorMessage}
            </div>
          )}
          {successMessage && (
            <div className="form-success" role="status">
              <strong>✨ </strong>{successMessage}
            </div>
          )}
          <form onSubmit={handleSubmit} className="auth-form" noValidate>
            <div className="auth-grid">
              <label>
                <span>Nombre <span style={{ color: 'var(--accent)' }}>*</span></span>
                <input
                  value={nombre}
                  onChange={(e) => setNombre(sanitizeNombre(e.target.value, 60))}
                  required
                  maxLength={60}
                  pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'\-]{2,60}"
                  title="Solo letras, espacios, apóstrofo o guión (2 a 60 caracteres)"
                />
                {errors.nombre?.[0] && <em className="field-error">{errors.nombre[0]}</em>}
              </label>
              <label>
                <span>Apellido <span style={{ color: 'var(--accent)' }}>*</span></span>
                <input
                  value={apellido}
                  onChange={(e) => setApellido(sanitizeNombre(e.target.value, 60))}
                  required
                  maxLength={60}
                  pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'\-]{2,60}"
                  title="Solo letras, espacios, apóstrofo o guión (2 a 60 caracteres)"
                />
                {errors.apellido?.[0] && <em className="field-error">{errors.apellido[0]}</em>}
              </label>
            </div>

            <label className="auth-phone-field">
              <span>Teléfono <span style={{ color: 'var(--accent)' }}>*</span></span>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <select value={telefonoPais} onChange={(e) => setTelefonoPais(e.target.value)} style={{ width: 130, flexShrink: 0 }}>
                  {CODIGOS_PAIS.map((c) => (
                    <option key={c.code} value={c.code}>{c.label}</option>
                  ))}
                </select>
                <input
                  type="tel"
                  inputMode="numeric"
                  value={telefono}
                  onChange={(e) => {
                    const digits = sanitizeDigits(e.target.value, telefonoPais === '+56' ? 9 : 15);
                    setTelefono(digits);
                    // Validación viva
                    if (telefonoPais === '+56') {
                      const err = validateTelefonoCL(digits);
                      setErrors((prev) => ({ ...prev, telefono: err ? [err] : [] }));
                    } else {
                      setErrors((prev) => ({ ...prev, telefono: [] }));
                    }
                  }}
                  required
                  minLength={telefonoPais === '+56' ? 9 : 7}
                  maxLength={telefonoPais === '+56' ? 9 : 15}
                  placeholder={telefonoPais === '+56' ? '912345678' : '3001234567'}
                  pattern={telefonoPais === '+56' ? '9[0-9]{8}' : '[0-9]{7,15}'}
                  title={telefonoPais === '+56' ? 'En Chile el celular comienza con 9 y tiene 9 dígitos' : 'Solo dígitos (7 a 15)'}
                  style={{ flex: 1, maxWidth: 'none' }}
                />
              </div>
              {(errors.telefono?.[0] || errors.telefono_pais?.[0]) && (
                <em className="field-error">{errors.telefono?.[0] ?? errors.telefono_pais?.[0]}</em>
              )}
            </label>

            <div className="auth-grid">
              <label>
                <span>País de residencia <span style={{ color: 'var(--accent)' }}>*</span></span>
                <select value={paisResidencia} onChange={(e) => setPaisResidencia(e.target.value)} required>
                  <option value="">— Selecciona —</option>
                  {PAISES_RESIDENCIA.map((p) => (
                    <option key={p} value={p}>{p}</option>
                  ))}
                </select>
                {errors.pais_residencia?.[0] && <em className="field-error">{errors.pais_residencia[0]}</em>}
              </label>
              <label>
                <span>Fecha de nacimiento <span style={{ color: 'var(--accent)' }}>*</span></span>
                <input
                  type="date"
                  value={fechaNacimiento}
                  onChange={(e) => setFechaNacimiento(e.target.value)}
                  required
                  max={new Date().toISOString().slice(0, 10)}
                />
                {errors.fecha_nacimiento_publica?.[0] && <em className="field-error">{errors.fecha_nacimiento_publica[0]}</em>}
              </label>
            </div>

            <label>
              <span>Género (opcional)</span>
              <select value={genero} onChange={(e) => setGenero(e.target.value)}>
                <option value="">— Prefiero no decir —</option>
                <option value="femenino">Femenino</option>
                <option value="masculino">Masculino</option>
                <option value="no_binario">No binario</option>
                <option value="prefiero_no_decir">Prefiero no decir</option>
              </select>
              {errors.genero?.[0] && <em className="field-error">{errors.genero[0]}</em>}
            </label>

            <LegalConsentBlock
              terminosAceptados={terminosAceptados}
              privacidadAceptada={privacidadAceptada}
              mayor18Aceptado={mayor18Aceptado}
              onTerminosAccepted={(version) => { setTerminosAceptados(true); setVersionDocumento(version); }}
              onPrivacidadAccepted={(version) => { setPrivacidadAceptada(true); setVersionDocumento(version); }}
              onMayor18Change={setMayor18Aceptado}
            />

            <button type="submit" disabled={submitting || !terminosAceptados || !privacidadAceptada || !mayor18Aceptado} className="btn-primary">
              {submitting ? 'Guardando…' : 'Completar perfil ✨'}
            </button>
          </form>
        </div>
      </div>
    </main>
  );
}
