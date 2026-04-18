import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';

// ── Notification prefs ────────────────────────────────────────────────────────
type NotifPrefs = {
  email_recordatorio_48h:  boolean;
  email_recordatorio_3h:   boolean;
  email_recordatorio_30m:  boolean;
  whatsapp_recordatorio:   boolean;
  email_transcripcion:     boolean;
  whatsapp_transcripcion:  boolean;
};

const NOTIF_LABELS: { key: keyof NotifPrefs; label: string; group: string }[] = [
  { key: 'email_recordatorio_48h', label: 'Recordatorio 48 h antes (correo)',   group: 'Recordatorios' },
  { key: 'email_recordatorio_3h',  label: 'Recordatorio 3 h antes (correo)',    group: 'Recordatorios' },
  { key: 'email_recordatorio_30m', label: 'Recordatorio 30 min antes (correo)', group: 'Recordatorios' },
  { key: 'whatsapp_recordatorio',  label: 'Recordatorio por WhatsApp',          group: 'Recordatorios' },
  { key: 'email_transcripcion',    label: 'Transcripción lista (correo)',        group: 'Post-sesión' },
  { key: 'whatsapp_transcripcion', label: 'Transcripción lista (WhatsApp)',      group: 'Post-sesión' },
];

function NotificacionesSection() {
  const qc = useQueryClient();
  const [prefs, setPrefs] = useState<NotifPrefs | null>(null);

  const { data, isLoading } = useQuery<NotifPrefs>({
    queryKey: ['notif-prefs'],
    queryFn: () => api.get('/preferencias-notificacion').then((r) => (r.data as { data: NotifPrefs }).data),
  });

  useEffect(() => { if (data) setPrefs(data); }, [data]);

  const mutation = useMutation({
    mutationFn: (p: NotifPrefs) => api.put('/preferencias-notificacion', p),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notif-prefs'] });
      toast.success('Preferencias guardadas');
    },
    onError: () => toast.error('No se pudieron guardar las preferencias'),
  });

  const toggle = (key: keyof NotifPrefs) =>
    setPrefs((p) => p ? { ...p, [key]: !p[key] } : p);

  const groups = ['Recordatorios', 'Post-sesión'];

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title">Notificaciones</h2>
      {isLoading || !prefs ? (
        <p className="cuenta-coming-soon">Cargando preferencias…</p>
      ) : (
        <div className="notif-prefs">
          {groups.map((group) => (
            <div key={group} className="notif-group">
              <p className="notif-group-label">{group}</p>
              {NOTIF_LABELS.filter((n) => n.group === group).map(({ key, label }) => (
                <label key={key} className="notif-toggle">
                  <span>{label}</span>
                  <input
                    type="checkbox"
                    checked={prefs[key]}
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
            onClick={() => prefs && mutation.mutate(prefs)}
          >
            {mutation.isPending ? 'Guardando…' : 'Guardar preferencias'}
          </button>
        </div>
      )}
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
  const user   = useAuthStore((s) => s.user);

  const [nombre,     setNombre]     = useState(user?.nombre ?? '');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put('/account/profile', { nombre });
      toast.success('¡Perfil actualizado correctamente!');
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
          <p className="dash-eyebrow">✦ Tu espacio personal</p>
          <h1 className="dash-title">Mi Cuenta</h1>
        </motion.div>

        {/* Perfil */}
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
                placeholder="¿Cómo te llamamos?"
              />
            </label>
            <label>
              Correo
              <input type="email" value={user?.email ?? ''} disabled />
            </label>

            <button className="btn-primary" type="submit" disabled={submitting}>
              {submitting ? 'Guardando…' : 'Guardar cambios'}
            </button>
          </form>
        </motion.section>

        {/* Datos natales */}
        <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
          <h2 className="dash-section-title">Datos natales</h2>
          <p className="cuenta-coming-soon">
            Pronto podrás ingresar tu fecha, hora y lugar de nacimiento para lecturas de carta astral personalizadas.
          </p>
        </motion.section>

        {/* Notificaciones */}
        <NotificacionesSection />

      </motion.div>
    </main>
  );
}
