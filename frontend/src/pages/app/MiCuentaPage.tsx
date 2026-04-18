import { useState } from 'react';
import type { FormEvent } from 'react';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { toast } from '../../stores/toastStore';

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
        <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
          <h2 className="dash-section-title">Notificaciones</h2>
          <p className="cuenta-coming-soon">
            Configuración de recordatorios por correo y WhatsApp — disponible próximamente.
          </p>
        </motion.section>

      </motion.div>
    </main>
  );
}
