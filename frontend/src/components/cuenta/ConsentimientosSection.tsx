import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { toast } from '../../stores/toastStore';

type Consent = {
  id: number;
  tipo: string;
  version_documento: string;
  otorgado: boolean;
  otorgado_en: string;
};

type Resp = { data: Consent[]; vigentes: Consent[]; tipos: string[] };

const LABELS: Record<string, string> = {
  terminos_uso: 'Términos de uso',
  politica_privacidad: 'Política de privacidad',
  marketing_email: 'Marketing por email',
  marketing_whatsapp: 'Marketing por WhatsApp',
  cookies_analitica: 'Cookies de analítica',
  almacenamiento_grabacion: 'Almacenamiento de grabaciones de sesión',
};

const fadeUp = { initial: { opacity: 0, y: 8 }, animate: { opacity: 1, y: 0 } };

export function ConsentimientosSection() {
  const [data, setData] = useState<Resp | null>(null);
  const [busy, setBusy] = useState(false);

  const load = () => api.get<Resp>('/me/consentimientos').then((r) => setData(r.data)).catch(() => null);

  useEffect(() => { load(); }, []);

  const setConsent = async (tipo: string, otorgado: boolean) => {
    setBusy(true);
    try {
      await api.post('/me/consentimientos', { tipo, otorgado });
      toast.success('Preferencia registrada.');
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally { setBusy(false); }
  };

  if (!data) return null;

  const vigentes = new Map(data.vigentes.map((c) => [c.tipo, c]));

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title">Privacidad y consentimientos (GDPR)</h2>
      <p className="cuenta-coming-soon">
        Controla qué datos puedes compartir y qué comunicaciones quieres recibir. Cada cambio se registra para tu trazabilidad.
      </p>

      <ul style={{ listStyle: 'none', padding: 0, display: 'grid', gap: '0.75rem' }}>
        {data.tipos.map((tipo) => {
          const vigente = vigentes.get(tipo);
          const otorgado = !!vigente?.otorgado;
          return (
            <li key={tipo} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', padding: '0.5rem 0', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
              <div>
                <strong>{LABELS[tipo] || tipo}</strong>
                {vigente && (
                  <div style={{ fontSize: '0.8em', color: 'var(--text-muted)' }}>
                    Última actualización {new Date(vigente.otorgado_en).toLocaleDateString()} · v{vigente.version_documento}
                  </div>
                )}
              </div>
              <label style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                <input type="checkbox" disabled={busy} checked={otorgado} onChange={(e) => setConsent(tipo, e.target.checked)} />
                {otorgado ? 'Otorgado' : 'Denegado'}
              </label>
            </li>
          );
        })}
      </ul>
    </motion.section>
  );
}
