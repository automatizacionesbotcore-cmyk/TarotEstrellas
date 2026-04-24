import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';

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
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put('/account/profile', { nombre });
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

        <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
          <h2 className="dash-section-title">Perfil</h2>
          <form className="auth-form cuenta-form" onSubmit={handleSubmit}>
            <label>
              Nombre
              <input
                type="text"
                value={nombre}
                onChange={(e) => setNombre(e.target.value)}
                autoComplete="name"
                placeholder="Como te llamamos?"
              />
            </label>
            <label>
              Correo
              <input type="email" value={user?.email ?? ''} disabled />
            </label>
            <button className="btn-primary" type="submit" disabled={submitting}>
              {submitting ? 'Guardando...' : 'Guardar cambios'}
            </button>
          </form>
        </motion.section>

        <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
          <h2 className="dash-section-title">Datos natales</h2>
          <p className="cuenta-coming-soon">
            Pronto podras ingresar tu fecha, hora y lugar de nacimiento para lecturas de carta astral personalizadas.
          </p>
        </motion.section>

        <NotificacionesSection />

        <CambiarPasswordSection />

      </motion.div>
    </main>
  );
}
