import { useEffect, useState } from 'react';
import {
  eliminarRespuestaResena,
  listResenas,
  responderResena,
  toggleVisibilidadResena,
  type ResenaAdmin,
} from '../../../lib/resenasAdminApi';
import { toast } from '../../../stores/toastStore';

export function AdminResenasPage() {
  const [items, setItems] = useState<ResenaAdmin[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [q, setQ] = useState('');
  const [puntuacionMax, setPuntuacionMax] = useState<string>('');
  const [sinResponder, setSinResponder] = useState(false);
  const [visible, setVisible] = useState<'all' | 'true' | 'false'>('all');
  const [responding, setResponding] = useState<ResenaAdmin | null>(null);

  const load = async () => {
    setLoading(true);
    try {
      const r = await listResenas({
        q: q || undefined,
        puntuacion_max: puntuacionMax ? Number(puntuacionMax) : undefined,
        sin_responder: sinResponder || undefined,
        visible: visible === 'all' ? undefined : visible === 'true',
        page,
      });
      setItems(r.data.data);
      setLastPage(r.data.last_page);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page]);

  const onToggleVisibilidad = async (r: ResenaAdmin) => {
    try {
      await toggleVisibilidadResena(r.uuid);
      toast.success(`Reseña ${r.visible ? 'oculta' : 'visible'}.`);
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    }
  };

  const onEliminarRespuesta = async (r: ResenaAdmin) => {
    if (!confirm('¿Eliminar la respuesta del admin?')) return;
    try {
      await eliminarRespuestaResena(r.uuid);
      toast.success('Respuesta eliminada.');
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    }
  };

  return (
    <main className="page-content">
      <header style={{ marginBottom: '1rem' }}>
        <h1>Reseñas</h1>
      </header>

      <form
        onSubmit={(e) => { e.preventDefault(); setPage(1); load(); }}
        style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1rem' }}
      >
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar comentario" style={{ flex: 1, minWidth: 200 }} />
        <select value={puntuacionMax} onChange={(e) => setPuntuacionMax(e.target.value)}>
          <option value="">Todas</option>
          <option value="2">≤ 2★</option>
          <option value="3">≤ 3★</option>
        </select>
        <select value={visible} onChange={(e) => setVisible(e.target.value as any)}>
          <option value="all">Todas</option>
          <option value="true">Visibles</option>
          <option value="false">Ocultas</option>
        </select>
        <label style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
          <input type="checkbox" checked={sinResponder} onChange={(e) => setSinResponder(e.target.checked)} /> Sin responder
        </label>
        <button type="submit">Filtrar</button>
      </form>

      {loading ? <p>Cargando…</p> : (
        <ul style={{ listStyle: 'none', padding: 0, display: 'grid', gap: '0.75rem' }}>
          {items.map((r) => (
            <li key={r.uuid} className="card" style={{ padding: '1rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
                <div>
                  <strong>{'★'.repeat(r.puntuacion)}{'☆'.repeat(5 - r.puntuacion)}</strong>{' '}
                  <span style={{ color: 'var(--text-muted)' }}>
                    {r.cliente?.profile?.nombre || `Cliente #${r.cliente_id}`} → {r.especialista?.name || `Especialista #${r.especialista_id}`}
                  </span>
                </div>
                <div style={{ fontSize: '0.85em', color: 'var(--text-muted)' }}>
                  {new Date(r.created_at).toLocaleDateString()} · {r.cita?.codigo_referencia}
                </div>
              </div>
              {r.comentario && <p style={{ marginTop: '0.5rem' }}>{r.comentario}</p>}

              {r.respuesta_admin ? (
                <blockquote style={{ marginTop: '0.5rem', padding: '0.5rem 0.75rem', borderLeft: '3px solid var(--primary, #6c5ce7)', background: 'rgba(108,92,231,0.05)' }}>
                  <strong>Respuesta admin:</strong> {r.respuesta_admin}
                  <div style={{ fontSize: '0.8em', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                    {r.respondida_en && new Date(r.respondida_en).toLocaleString()}
                  </div>
                </blockquote>
              ) : null}

              <div style={{ marginTop: '0.5rem', display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                <button type="button" onClick={() => setResponding(r)}>{r.respuesta_admin ? 'Editar respuesta' : 'Responder'}</button>
                {r.respuesta_admin && <button type="button" onClick={() => onEliminarRespuesta(r)}>Eliminar respuesta</button>}
                <button type="button" onClick={() => onToggleVisibilidad(r)}>{r.visible ? 'Ocultar' : 'Mostrar'}</button>
              </div>
            </li>
          ))}
          {!items.length && <li style={{ textAlign: 'center', padding: '2rem' }}>Sin reseñas.</li>}
        </ul>
      )}

      <nav style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', marginTop: '1rem' }}>
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button>
        <span>Página {page} de {lastPage}</span>
        <button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>›</button>
      </nav>

      {responding && (
        <ResponderModal
          resena={responding}
          onClose={() => setResponding(null)}
          onSaved={() => { setResponding(null); load(); }}
        />
      )}
    </main>
  );
}

function ResponderModal({ resena, onClose, onSaved }: { resena: ResenaAdmin; onClose: () => void; onSaved: () => void }) {
  const [text, setText] = useState(resena.respuesta_admin || '');
  const [saving, setSaving] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await responderResena(resena.uuid, text);
      toast.success('Respuesta guardada.');
      onSaved();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
      <div onClick={(e) => e.stopPropagation()} className="card" style={{ background: 'var(--bg, #fff)', maxWidth: 500, width: '100%', padding: '1.5rem' }}>
        <h2>Responder reseña</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.9em' }}>{resena.comentario}</p>
        <form onSubmit={submit}>
          <textarea required maxLength={2000} rows={6} value={text} onChange={(e) => setText(e.target.value)} style={{ width: '100%', fontFamily: 'inherit', padding: '0.5rem' }} />
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '0.5rem' }}>
            <button type="button" onClick={onClose}>Cancelar</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Guardando…' : 'Guardar'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}
