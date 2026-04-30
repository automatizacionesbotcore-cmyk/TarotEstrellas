import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';

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
  const [telefonoPais, setTelefonoPais] = useState('+57');
  const [telefono, setTelefono] = useState('');
  const [paisResidencia, setPaisResidencia] = useState('');
  const [fechaNacimiento, setFechaNacimiento] = useState('');
  const [genero, setGenero] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    if (user?.nombre && !nombre) setNombre(user.nombre);
  }, [user]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});
    try {
      await api.post('/me/completar-perfil', {
        nombre,
        apellido,
        telefono,
        telefono_pais: telefonoPais,
        pais_residencia: paisResidencia,
        fecha_nacimiento_publica: fechaNacimiento,
        genero: genero || null,
      });
      await refreshUser();
      toast.success('¡Perfil completado! ✨');
      navigate('/app', { replace: true });
    } catch (err: any) {
      if (err?.response?.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        toast.error('No pudimos guardar tu perfil. Intenta de nuevo.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  const nombreCorto = (user?.nombre ?? '').split(' ')[0] || 'viajero estelar';

  return (
    <main className="page-content" style={{ maxWidth: 720, margin: '0 auto', padding: '2rem 1rem' }}>
      <div
        style={{
          background: 'var(--card)',
          border: '1px solid var(--border-subtle)',
          borderRadius: 16,
          padding: '1.5rem',
          marginBottom: '1.5rem',
          display: 'flex',
          alignItems: 'center',
          gap: '1rem',
        }}
      >
        <div
          style={{
            width: 56,
            height: 56,
            borderRadius: '50%',
            background: 'var(--gradient-gold, linear-gradient(135deg, #fbbf24, #f59e0b))',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexShrink: 0,
            fontSize: 28,
          }}
        >
          ✨
        </div>
        <div>
          <h2 style={{ margin: 0, fontSize: '1.1rem' }}>Hola {nombreCorto}, soy Astrea ✨</h2>
          <p style={{ margin: '0.25rem 0 0', color: 'var(--text-muted)', fontSize: '0.9rem' }}>
            Necesito unos datos más para acompañarte mejor en tu camino. Solo tomará un minuto.
          </p>
        </div>
      </div>

      <form
        onSubmit={handleSubmit}
        style={{
          background: 'var(--card)',
          border: '1px solid var(--border-subtle)',
          borderRadius: 16,
          padding: '1.5rem',
          display: 'grid',
          gap: '1rem',
        }}
      >
        <Field label="Nombre" required error={errors.nombre?.[0]}>
          <input value={nombre} onChange={(e) => setNombre(e.target.value)} required maxLength={100} />
        </Field>

        <Field label="Apellido" required error={errors.apellido?.[0]}>
          <input value={apellido} onChange={(e) => setApellido(e.target.value)} required maxLength={120} />
        </Field>

        <Field label="Teléfono" required error={errors.telefono?.[0] ?? errors.telefono_pais?.[0]}>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <select value={telefonoPais} onChange={(e) => setTelefonoPais(e.target.value)} style={{ width: 130 }}>
              {CODIGOS_PAIS.map((c) => (
                <option key={c.code} value={c.code}>{c.label}</option>
              ))}
            </select>
            <input
              type="tel"
              value={telefono}
              onChange={(e) => setTelefono(e.target.value)}
              required
              maxLength={30}
              placeholder="3001234567"
              style={{ flex: 1 }}
            />
          </div>
        </Field>

        <Field label="País de residencia" required error={errors.pais_residencia?.[0]}>
          <select value={paisResidencia} onChange={(e) => setPaisResidencia(e.target.value)} required>
            <option value="">— Selecciona —</option>
            {PAISES_RESIDENCIA.map((p) => (
              <option key={p} value={p}>{p}</option>
            ))}
          </select>
        </Field>

        <Field label="Fecha de nacimiento" required error={errors.fecha_nacimiento_publica?.[0]}>
          <input
            type="date"
            value={fechaNacimiento}
            onChange={(e) => setFechaNacimiento(e.target.value)}
            required
            max={new Date().toISOString().slice(0, 10)}
          />
        </Field>

        <Field label="Género (opcional)" error={errors.genero?.[0]}>
          <select value={genero} onChange={(e) => setGenero(e.target.value)}>
            <option value="">— Prefiero no decir —</option>
            <option value="femenino">Femenino</option>
            <option value="masculino">Masculino</option>
            <option value="no_binario">No binario</option>
            <option value="prefiero_no_decir">Prefiero no decir</option>
          </select>
        </Field>

        <button
          type="submit"
          disabled={submitting}
          className="btn-primary"
          style={{ marginTop: '0.5rem' }}
        >
          {submitting ? 'Guardando…' : 'Completar perfil ✨'}
        </button>
      </form>
    </main>
  );
}

function Field({
  label,
  required,
  error,
  children,
}: {
  label: string;
  required?: boolean;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <label style={{ display: 'grid', gap: '0.35rem' }}>
      <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
        {label} {required && <span style={{ color: 'var(--accent)' }}>*</span>}
      </span>
      {children}
      {error && <span style={{ fontSize: '0.8rem', color: '#ef4444' }}>{error}</span>}
    </label>
  );
}
