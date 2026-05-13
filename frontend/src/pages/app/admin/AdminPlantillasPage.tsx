import { useEffect, useState } from 'react';
import {
  actualizarPlantilla, crearPlantilla, eliminarPlantilla, listPlantillas, previewPlantilla,
  VARIABLES_BASE, type Plantilla, type PlantillaPayload,
} from '../../../lib/plantillasAdminApi';
import { toast } from '../../../stores/toastStore';

export function AdminPlantillasPage() {
  const [items, setItems] = useState<Plantilla[]>([]);
  const [loading, setLoading] = useState(false);
  const [canal, setCanal] = useState<'all' | 'email' | 'whatsapp' | 'sms'>('all');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [editing, setEditing] = useState<Plantilla | null>(null);
  const [creating, setCreating] = useState(false);
  const [busyIds, setBusyIds] = useState<Set<number>>(new Set());

  const load = async () => {
    setLoading(true);
    try {
      const r = await listPlantillas({ canal: canal === 'all' ? undefined : canal, page });
      setItems(r.data.data); setLastPage(r.data.last_page);
    } catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page, canal]);

  const onDelete = async (p: Plantilla) => {
    if (!confirm(`¿Eliminar plantilla ${p.clave}?`)) return;
    setBusyIds((prev) => new Set([...prev, p.id]));
    try { await eliminarPlantilla(p.id); toast.success('Eliminada.'); load(); }
    catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setBusyIds((prev) => { const next = new Set(prev); next.delete(p.id); return next; }); }
  };

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h1>Plantillas de notificación</h1>
        <button type="button" className="btn-primary" onClick={() => setCreating(true)}>+ Nueva</button>
      </header>

      <div style={{ marginBottom: '1rem' }}>
        <label>Canal{' '}
          <select value={canal} onChange={(e) => { setCanal(e.target.value as any); setPage(1); }}>
            <option value="all">Todos</option>
            <option value="email">Email</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="sms">SMS</option>
          </select>
        </label>
      </div>

      {loading ? <p>Cargando…</p> : (
        <table className="data-table" style={{ width: '100%' }}>
          <thead><tr><th>Clave</th><th>Canal</th><th>Asunto</th><th>Versión</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            {items.map((p) => (
              <tr key={p.id}>
                <td><code>{p.clave}</code></td>
                <td><span className="badge">{p.canal}</span></td>
                <td>{p.asunto || '—'}</td>
                <td>v{p.version}</td>
                <td><span className={`badge ${p.activo ? 'badge-success' : 'badge-muted'}`}>{p.activo ? 'Activo' : 'Inactivo'}</span></td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button type="button" onClick={() => setEditing(p)} disabled={busyIds.has(p.id)}>Editar</button>{' '}
                  <button type="button" onClick={() => onDelete(p)} disabled={busyIds.has(p.id)} style={{ color: 'var(--danger)' }}>{busyIds.has(p.id) ? 'Eliminando…' : 'Eliminar'}</button>
                </td>
              </tr>
            ))}
            {!items.length && <tr><td colSpan={6} style={{ textAlign: 'center', padding: '2rem' }}>Sin plantillas.</td></tr>}
          </tbody>
        </table>
      )}

      <nav style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', marginTop: '1rem' }}>
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button>
        <span>Página {page} de {lastPage}</span>
        <button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>›</button>
      </nav>

      {(creating || editing) && (
        <PlantillaModal initial={editing} onClose={() => { setCreating(false); setEditing(null); }}
          onSaved={() => { setCreating(false); setEditing(null); load(); }} />
      )}
    </main>
  );
}

function PlantillaModal({ initial, onClose, onSaved }: { initial: Plantilla | null; onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState<PlantillaPayload>(() => initial ? {
    clave: initial.clave, canal: initial.canal, asunto: initial.asunto,
    cuerpo: initial.cuerpo, variables: initial.variables, activo: initial.activo,
  } : { clave: '', canal: 'email', asunto: '', cuerpo: '', variables: [], activo: true });
  const [preview, setPreview] = useState<{ asunto: string | null; cuerpo: string } | null>(null);
  const [saving, setSaving] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (initial) { await actualizarPlantilla(initial.id, form); toast.success('Actualizada (nueva versión si cambió contenido).'); }
      else { await crearPlantilla(form); toast.success('Creada.'); }
      onSaved();
    } catch (e: any) {
      const msg = e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' · ') : e?.response?.data?.message || 'Error.';
      toast.error(msg);
    } finally { setSaving(false); }
  };

  const doPreview = async () => {
    if (!initial) { toast.info('Guarda primero para usar preview.'); return; }
    const sample: Record<string, string> = {};
    VARIABLES_BASE.forEach((v) => { sample[v] = `[${v}]`; });
    try { const r = await previewPlantilla(initial.id, sample); setPreview(r.data); }
    catch (e: any) { toast.error(e?.response?.data?.message || 'Error preview.'); }
  };

  const insertVar = (v: string) => setForm({ ...form, cuerpo: form.cuerpo + `{{${v}}}` });

  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
      <div onClick={(e) => e.stopPropagation()} className="card" style={{ background: 'var(--bg, #fff)', maxWidth: 900, width: '100%', padding: '1.5rem', maxHeight: '90vh', overflow: 'auto' }}>
        <h2>{initial ? `Editar ${initial.clave}` : 'Nueva plantilla'}</h2>
        <form onSubmit={submit} style={{ display: 'grid', gridTemplateColumns: preview ? '1fr 1fr' : '1fr', gap: '1rem' }}>
          <div style={{ display: 'grid', gap: '0.75rem' }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
              <label>Clave <input required maxLength={100} value={form.clave} onChange={(e) => setForm({ ...form, clave: e.target.value })} disabled={!!initial} /></label>
              <label>Canal
                <select value={form.canal} onChange={(e) => setForm({ ...form, canal: e.target.value as any })}>
                  <option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option>
                </select>
              </label>
            </div>
            {form.canal === 'email' && (
              <label>Asunto <input maxLength={200} value={form.asunto || ''} onChange={(e) => setForm({ ...form, asunto: e.target.value })} /></label>
            )}
            <label>Cuerpo
              <textarea required rows={10} value={form.cuerpo} onChange={(e) => setForm({ ...form, cuerpo: e.target.value })} style={{ fontFamily: 'monospace', width: '100%' }} />
            </label>
            <div>
              <small><strong>Variables:</strong></small>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginTop: 4 }}>
                {VARIABLES_BASE.map((v) => (
                  <button key={v} type="button" onClick={() => insertVar(v)} style={{ fontSize: '0.75em' }}>{`{{${v}}}`}</button>
                ))}
              </div>
            </div>
            <label><input type="checkbox" checked={!!form.activo} onChange={(e) => setForm({ ...form, activo: e.target.checked })} /> Activa</label>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
              <button type="button" onClick={doPreview}>Preview</button>
              <button type="button" onClick={onClose}>Cancelar</button>
              <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Guardando…' : 'Guardar'}</button>
            </div>
          </div>
          {preview && (
            <div style={{ background: 'var(--bg-secondary)', padding: '1rem', borderRadius: 4 }}>
              <h3 style={{ marginTop: 0 }}>Preview</h3>
              {preview.asunto && <div><strong>Asunto:</strong> {preview.asunto}</div>}
              <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit' }}>{preview.cuerpo}</pre>
            </div>
          )}
        </form>
      </div>
    </div>
  );
}
