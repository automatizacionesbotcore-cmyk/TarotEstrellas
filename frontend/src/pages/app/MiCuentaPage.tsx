import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { sanitizeNombre, sanitizeDigits } from '../../lib/formValidators';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';
import { TwoFactorSection } from '../../components/cuenta/TwoFactorSection';
import { ConsentimientosSection } from '../../components/cuenta/ConsentimientosSection';
import { ExportarDatosSection } from '../../components/cuenta/ExportarDatosSection';

type NotifPrefs = {
  email_recordatorios:    boolean;
  email_marketing:        boolean;
  whatsapp_recordatorios: boolean;
  whatsapp_marketing:     boolean;
  canal_preferido:        string | null;
};

const NOTIF_LABELS: { key: keyof Omit<NotifPrefs, 'canal_preferido'>; label: string; group: string }[] = [
  { key: 'email_recordatorios',    label: 'Recordatorios por correo',   group: 'Correo' },
  { key: 'email_marketing',        label: 'Novedades por correo',       group: 'Correo' },
  { key: 'whatsapp_recordatorios', label: 'Recordatorios por WhatsApp', group: 'WhatsApp' },
  { key: 'whatsapp_marketing',     label: 'Novedades por WhatsApp',     group: 'WhatsApp' },
];

function NotificacionesSection() {
  const qc = useQueryClient();
  const [prefs, setPrefs] = useState<NotifPrefs | null>(null);

  const { data, isLoading, isError } = useQuery<NotifPrefs>({
    queryKey: ['notif-prefs'],
    queryFn: () => api.get('/preferencias-notificacion').then((r) => (r.data as { data: NotifPrefs }).data),
  });

  useEffect(() => { if (data) setPrefs(data); }, [data]);

  const mutation = useMutation({
    mutationFn: (p: Omit<NotifPrefs, 'canal_preferido'>) => api.put('/preferencias-notificacion', p),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notif-prefs'] });
      toast.success('Preferencias guardadas');
    },
    onError: () => toast.error('No se pudieron guardar las preferencias'),
  });

  const toggle = (key: keyof Omit<NotifPrefs, 'canal_preferido'>) =>
    setPrefs((p) => p ? { ...p, [key]: !p[key] } : p);

  const groups = ['Correo', 'WhatsApp'];

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title">Notificaciones</h2>
      {isLoading ? (
        <p className="cuenta-coming-soon">Cargando preferencias...</p>
      ) : isError ? (
        <p className="form-error">No se pudieron cargar las preferencias.</p>
      ) : !prefs ? null : (
        <div className="notif-prefs">
          {groups.map((group) => (
            <div key={group} className="notif-group">
              <p className="notif-group-label">{group}</p>
              {NOTIF_LABELS.filter((n) => n.group === group).map(({ key, label }) => (
                <label key={key} className="notif-toggle">
                  <span>{label}</span>
                  <input
                    type="checkbox"
                    checked={prefs[key] as boolean}
                    onChange={() => toggle(key)}
                  />
                  <span className="toggle-track">
                    <span className="toggle-thumb" />
                  </span>
                </label>
              ))}
            </div>
          ))}
          <button
            type="button"
            className="btn-primary"
            disabled={mutation.isPending}
            onClick={() => prefs && mutation.mutate({
              email_recordatorios:    prefs.email_recordatorios,
              email_marketing:        prefs.email_marketing,
              whatsapp_recordatorios: prefs.whatsapp_recordatorios,
              whatsapp_marketing:     prefs.whatsapp_marketing,
            })}
          >
            {mutation.isPending ? 'Guardando...' : 'Guardar preferencias'}
          </button>
        </div>
      )}
    </motion.section>
  );
}

function CambiarPasswordSection() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword,     setNewPassword]     = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [submitting,      setSubmitting]      = useState(false);
  const [errorMsg,        setErrorMsg]        = useState<string | null>(null);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setErrorMsg(null);

    if (newPassword !== confirmPassword) {
      setErrorMsg('Las contraseñas no coinciden.');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/account/password', {
        current_password:      currentPassword,
        password:              newPassword,
        password_confirmation: confirmPassword,
      });
      toast.success('Contraseña actualizada correctamente.');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'No se pudo cambiar la contraseña.';
      setErrorMsg(msg);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title">Cambiar contraseña</h2>
      <form className="auth-form cuenta-form" onSubmit={handleSubmit}>
        <label>
          Contraseña actual
          <input
            type="password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            required
            autoComplete="current-password"
          />
        </label>
        <label>
          Nueva contraseña
          <input
            type="password"
            value={newPassword}
            onChange={(e) => setNewPassword(e.target.value)}
            required
            minLength={8}
            autoComplete="new-password"
          />
        </label>
        <label>
          Confirmar nueva contraseña
          <input
            type="password"
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
            required
            minLength={8}
            autoComplete="new-password"
          />
        </label>
        {errorMsg && <p className="form-error">{errorMsg}</p>}
        <button className="btn-primary" type="submit" disabled={submitting}>
          {submitting ? 'Cambiando...' : 'Cambiar contraseña'}
        </button>
      </form>
    </motion.section>
  );
}

const GENERO_OPTS = [
  { value: '', label: 'Prefiero no indicar' },
  { value: 'masculino', label: 'Masculino' },
  { value: 'femenino', label: 'Femenino' },
  { value: 'no_binario', label: 'No binario' },
  { value: 'prefiero_no_decir', label: 'Otro / Prefiero no decir' },
];

const TIMEZONES = [
  'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/Bogota',
  'America/Lima', 'America/Mexico_City', 'America/New_York', 'Europe/Madrid',
  'Europe/London', 'UTC',
];

function EliminarCuentaSection() {
  const navigate      = useNavigate();
  const clearSession  = useAuthStore((s) => s.clearSession);
  const [fase, setFase] = useState<'idle' | 'confirmar' | 'pending'>('idle');

  const handleEliminar = async () => {
    setFase('pending');
    try {
      await api.delete('/me/account', { data: { confirmacion: 'ELIMINAR' } });
      clearSession();
      navigate('/', { replace: true });
      toast.success('Tu cuenta ha sido eliminada.');
    } catch {
      setFase('confirmar');
      toast.error('No se pudo eliminar la cuenta. Intenta de nuevo.');
    }
  };

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title" style={{ color: 'var(--red, #e05555)' }}>Zona de peligro</h2>
      <p style={{ fontSize: '0.875rem', color: 'var(--text-muted)', marginBottom: '1rem' }}>
        Al eliminar tu cuenta se desactivará el acceso. Tus datos no se borran permanentemente
        y podrás reactivarla contactándonos.
      </p>

      {fase === 'idle' && (
        <button
          type="button"
          className="btn-secondary"
          style={{ borderColor: 'var(--red, #e05555)', color: 'var(--red, #e05555)' }}
          onClick={() => setFase('confirmar')}
        >
          Eliminar mi cuenta
        </button>
      )}

      {fase === 'confirmar' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          <p style={{ fontSize: '0.875rem', fontWeight: 600, color: 'var(--red, #e05555)' }}>
            ¿Segura/o que deseas eliminar tu cuenta? Esta acción desactivará tu acceso.
          </p>
          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <button
              type="button"
              className="btn-primary"
              style={{ background: 'var(--red, #e05555)', borderColor: 'var(--red, #e05555)' }}
              onClick={handleEliminar}
            >
              Sí, eliminar
            </button>
            <button type="button" className="btn-secondary" onClick={() => setFase('idle')}>
              Cancelar
            </button>
          </div>
        </div>
      )}

      {fase === 'pending' && <p style={{ color: 'var(--text-muted)', fontSize: '0.875rem' }}>Eliminando cuenta…</p>}
    </motion.section>
  );
}

const stagger = {
  hidden:  {},
  visible: { transition: { staggerChildren: 0.1 } },
};

const fadeUp = {
  hidden:  { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0  },
};

export function MiCuentaPage() {
  const user = useAuthStore((s) => s.user);

  const [nombre,     setNombre]     = useState(user?.nombre ?? '');
  const [apellido,   setApellido]   = useState('');
  const [telefono,   setTelefono]   = useState('');
  const [zonaTz,     setZonaTz]     = useState('America/Santiago');
  const [genero,     setGenero]     = useState('');
  const [fechaNac,   setFechaNac]   = useState('');
  const [biografia,  setBiografia]  = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [profileLoaded, setProfileLoaded] = useState(false);

  useEffect(() => {
    if (profileLoaded) return;
    api.get('/user').then((r: any) => {
      const p = r.data?.profile;
      if (!p) return;
      if (p.nombre)     setNombre(p.nombre);
      if (p.apellido)   setApellido(p.apellido);
      if (p.telefono)   setTelefono(p.telefono);
      if (p.zona_horaria) setZonaTz(p.zona_horaria);
      if (p.genero)     setGenero(p.genero);
      if (p.fecha_nacimiento_publica) setFechaNac(p.fecha_nacimiento_publica.slice(0, 10));
      if (p.biografia)  setBiografia(p.biografia);
      setProfileLoaded(true);
    }).catch(() => {});
  }, [profileLoaded]);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put('/account/profile', {
        nombre,
        apellido:                 apellido || undefined,
        telefono:                 telefono || undefined,
        zona_horaria:             zonaTz || undefined,
        genero:                   genero || undefined,
        fecha_nacimiento_publica: fechaNac || undefined,
        biografia:                biografia || undefined,
      });
      toast.success('Perfil actualizado correctamente.');
    } catch {
      toast.error('No se pudo guardar el perfil. Intenta de nuevo.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="page-content">
      <motion.div initial="hidden" animate="visible" variants={stagger}>

        <motion.div variants={fadeUp} transition={{ duration: 0.5 }}>
          <p className="dash-eyebrow">Tu espacio personal</p>
          <h1 className="dash-title">Mi Cuenta</h1>
        </motion.div>

        <div className="cuenta-grid">
          <div className="cuenta-column">
            <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
              <h2 className="dash-section-title">Perfil</h2>
              <form className="auth-form cuenta-form" onSubmit={handleSubmit}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                  <label>
                    Nombre
                    <input type="text" value={nombre}
                      onChange={(e) => setNombre(sanitizeNombre(e.target.value, 60))}
                      maxLength={60}
                      pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'\-]{2,60}"
                      title="Solo letras"
                      autoComplete="given-name" required />
                  </label>
                  <label>
                    Apellido
                    <input type="text" value={apellido}
                      onChange={(e) => setApellido(sanitizeNombre(e.target.value, 60))}
                      maxLength={60}
                      pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'\-]{0,60}"
                      title="Solo letras"
                      autoComplete="family-name" />
                  </label>
                </div>
                <label>
                  Correo
                  <input type="email" value={user?.email ?? ''} disabled />
                </label>
                <label>
                  Teléfono
                  <input type="tel" inputMode="numeric" value={telefono}
                    onChange={(e) => setTelefono(sanitizeDigits(e.target.value, 15))}
                    maxLength={15}
                    pattern="[0-9]{7,15}"
                    title="Solo dígitos (7 a 15)"
                    autoComplete="tel" placeholder="912345678" />
                </label>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                  <label>
                    Género
                    <select value={genero} onChange={(e) => setGenero(e.target.value)}>
                      {GENERO_OPTS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                  </label>
                  <label>
                    Fecha de nacimiento
                    <input type="date" value={fechaNac} onChange={(e) => setFechaNac(e.target.value)} max={new Date().toISOString().slice(0, 10)} />
                  </label>
                </div>
                <label>
                  Zona horaria
                  <select value={zonaTz} onChange={(e) => setZonaTz(e.target.value)}>
                    {TIMEZONES.map((tz) => <option key={tz} value={tz}>{tz}</option>)}
                  </select>
                </label>
                <label>
                  Biografía <span className="booking-optional">(opcional, máx. 500 car.)</span>
                  <textarea
                    value={biografia}
                    onChange={(e) => setBiografia(e.target.value)}
                    maxLength={500}
                    rows={3}
                    style={{ resize: 'vertical' }}
                    placeholder="Cuéntanos un poco sobre ti..."
                  />
                </label>
                <button className="btn-primary" type="submit" disabled={submitting}>
                  {submitting ? 'Guardando...' : 'Guardar cambios'}
                </button>
              </form>
            </motion.section>

            <CambiarPasswordSection />

            <TwoFactorSection />
          </div>

          <div className="cuenta-column">
            <NotificacionesSection />

            <ConsentimientosSection />

            <ExportarDatosSection />

            <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
              <h2 className="dash-section-title">Datos natales</h2>
              <p className="cuenta-coming-soon">
                Pronto podrás ingresar tu fecha, hora y lugar de nacimiento para lecturas de carta astral personalizadas.
              </p>
            </motion.section>

            <EliminarCuentaSection />
          </div>
        </div>

      </motion.div>
    </main>
  );
}
