import { useState } from 'react';
import { motion } from 'framer-motion';
import { api } from '../../lib/api';
import { toast } from '../../stores/toastStore';

const fadeUp = { initial: { opacity: 0, y: 8 }, animate: { opacity: 1, y: 0 } };

export function ExportarDatosSection() {
  const [busy, setBusy] = useState<'json' | 'pdf' | null>(null);

  const exportJson = async () => {
    setBusy('json');
    try {
      const r = await api.get('/me/export');
      const blob = new Blob([JSON.stringify(r.data, null, 2)], { type: 'application/json' });
      download(blob, `mis-datos-${new Date().toISOString().slice(0, 10)}.json`);
      toast.success('Exportación JSON descargada.');
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error exportando.');
    } finally { setBusy(null); }
  };

  const exportPdf = async () => {
    setBusy('pdf');
    try {
      const r = await api.get('/me/consultas/export/pdf', { responseType: 'blob' });
      download(r.data as Blob, `mis-consultas-${new Date().toISOString().slice(0, 10)}.pdf`);
      toast.success('Historial PDF descargado.');
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error generando PDF.');
    } finally { setBusy(null); }
  };

  return (
    <motion.section className="cuenta-section" variants={fadeUp} transition={{ duration: 0.45 }}>
      <h2 className="dash-section-title">Exportar mis datos</h2>
      <p className="cuenta-coming-soon">
        Descarga una copia de tu información personal y tu historial de consultas en cualquier momento.
      </p>
      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
        <button type="button" onClick={exportJson} disabled={busy !== null}>
          {busy === 'json' ? 'Generando…' : 'Descargar todos mis datos (JSON)'}
        </button>
        <button type="button" onClick={exportPdf} disabled={busy !== null}>
          {busy === 'pdf' ? 'Generando…' : 'Descargar historial de consultas (PDF)'}
        </button>
      </div>
    </motion.section>
  );
}

function download(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
