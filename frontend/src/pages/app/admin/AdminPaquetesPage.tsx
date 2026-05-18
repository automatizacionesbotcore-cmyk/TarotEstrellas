import { useEffect, useState } from 'react';
import {
  actualizarPaquete, crearPaquete, eliminarPaquete, listPaquetes, togglePaquete,
  type Paquete, type PaquetePayload,
} from '../../../lib/paquetesAdminApi';
import { toast } from '../../../stores/toastStore';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

export function AdminPaquetesPage() {
  const [items, setItems] = useState<Paquete[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [q, setQ] = useState('');
  const [tipo, setTipo] = useState<'all' | 'paquete' | 'membresia'>('all');
  const [activo, setActivo] = useState<'all' | 'true' | 'false'>('all');
  const [editing, setEditing] = useState<Paquete | null>(null);
  const [creating, setCreating] = useState(false);
  const [busyIds, setBusyIds] = useState<Set<number>>(new Set());

  const load = async () => {
    setLoading(true);
    try {
      const r = await listPaquetes({
        q: q || undefined,
        tipo: tipo === 'all' ? undefined : tipo,
        activo: activo === 'all' ? undefined : activo === 'true',
        page,
      });
      setItems(r.data.data);
      setLastPage(r.data.last_page);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error cargando paquetes.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page]);

  const onToggle = async (p: Paquete) => {
    setBusyIds((prev) => new Set([...prev, p.id]));
    try {
      await togglePaquete(p.id);
      toast.success(`Paquete ${p.activo ? 'desactivado' : 'activado'}.`);
      load();
    } catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setBusyIds((prev) => { const next = new Set(prev); next.delete(p.id); return next; }); }
  };

  const onDelete = async (p: Paquete) => {
    if (!confirm(`¿Eliminar paquete ${p.nombre}?`)) return;
    setBusyIds((prev) => new Set([...prev, p.id]));
    try { await eliminarPaquete(p.id); toast.success('Paquete eliminado.'); load(); }
    catch (e: any) { toast.error(e?.response?.data?.message || 'Error.'); }
    finally { setBusyIds((prev) => { const next = new Set(prev); next.delete(p.id); return next; }); }
  };

  const columns: Column<Paquete>[] = [
    {
      key: 'nombre',
      label: 'Nombre',
      render: (p) => <><strong>{p.nombre}</strong><br /><small>{p.slug}</small></>,
    },
    { key: 'tipo', label: 'Tipo', render: (p) => <span className="badge">{p.tipo}</span> },
    { key: 'consultas', label: 'Consultas', render: (p) => p.consultas_incluidas },
    { key: 'vigencia', label: 'Vigencia', render: (p) => p.vigencia_dias ? `${p.vigencia_dias} días` : '—' },
    { key: 'precio', label: 'Precio', render: (p) => `${(p.precio_centavos / 100).toLocaleString()} ${p.moneda}` },
    {
      key: 'estado',
      label: 'Estado',
      render: (p) => (
        <>
          <span className={`badge ${p.activo ? 'badge-success' : 'badge-muted'}`}>{p.activo ? 'Activo' : 'Inactivo'}</span>
          {p.destacado && <span className="badge badge-warning" style={{ marginLeft: 4 }}>★</span>}
        </>
      ),
    },
    {
      key: 'acciones',
      label: 'Acciones',
      render: (p) => (
        <div className="admin-row-actions">
          <button type="button" className="btn-secondary" onClick={() => setEditing(p)} disabled={busyIds.has(p.id)}>Editar</button>
          <button type="button" className="btn-secondary" onClick={() => onToggle(p)} disabled={busyIds.has(p.id)}>
            {busyIds.has(p.id) ? 'Procesando…' : (p.activo ? 'Desactivar' : 'Activar')}
          </button>
          <button type="button" className="btn-danger" onClick={() => onDelete(p)} disabled={busyIds.has(p.id)}>
            {busyIds.has(p.id) ? 'Eliminando…' : 'Eliminar'}
          </button>
        </div>
      ),
    },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Oferta</p>
          <h1>Paquetes y membresías</h1>
          <p className="admin-page-subtitle">Gestiona productos recurrentes, destacados y vigencias.</p>
        </div>
        <button type="button" className="btn-primary" onClick={() => setCreating(true)}>Nuevo paquete</button>
      </header>

      <form onSubmit={(e) => { e.preventDefault(); setPage(1); load(); }}
        className="admin-filter-card">
        <div className="admin-filter-grid">
          <label>
            Buscar
            <input className="form-input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nombre o slug" />
          </label>
          <label>
            Tipo
            <select className="form-input" value={tipo} onChange={(e) => setTipo(e.target.value as any)}>
              <option value="all">Todos los tipos</option>
              <option value="paquete">Paquetes</option>
              <option value="membresia">Membresías</option>
            </select>
          </label>
          <label>
            Estado
            <select className="form-input" value={activo} onChange={(e) => setActivo(e.target.value as any)}>
              <option value="all">Todos</option>
              <option value="true">Activos</option>
              <option value="false">Inactivos</option>
            </select>
          </label>
        </div>
        <div className="admin-filter-actions">
          <button type="submit" className="btn-primary">Filtrar</button>
        </div>
      </form>

      <AdminTable
        columns={columns}
        rows={items}
        loading={loading}
        emptyLabel="Sin paquetes."
        rowKey={(p) => p.id}
        searchable={false}
        pagination={{ currentPage: page, lastPage, onPageChange: setPage }}
      />

      {(creating || editing) && (
        <PaqueteModal initial={editing} onClose={() => { setCreating(false); setEditing(null); }}
          onSaved={() => { setCreating(false); setEditing(null); load(); }} />
      )}
    </main>
  );
}

function PaqueteModal({ initial, onClose, onSaved }: { initial: Paquete | null; onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState<PaquetePayload>(() => initial ? {
    slug: initial.slug, nombre: initial.nombre, tipo: initial.tipo,
    consultas_incluidas: initial.consultas_incluidas, vigencia_dias: initial.vigencia_dias,
    precio_centavos: initial.precio_centavos, moneda: initial.moneda,
    descuento_porcentaje: initial.descuento_porcentaje, activo: initial.activo,
    destacado: initial.destacado, orden_visualizacion: initial.orden_visualizacion,
    imagen_url: initial.imagen_url,
  } : {
    nombre: '', tipo: 'paquete', consultas_incluidas: 1, vigencia_dias: 90,
    precio_centavos: 1000000, moneda: 'CLP', activo: true, destacado: false, orden_visualizacion: 0,
  });
  const [saving, setSaving] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (initial) {
        await actualizarPaquete(initial.id, form);
        toast.success('Paquete actualizado.');
      } else {
        await crearPaquete(form);
        toast.success('Paquete creado.');
      }
      onSaved();
    } catch (e: any) {
      const msg = e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(' · ')
        : e?.response?.data?.message || 'Error.';
      toast.error(msg);
    } finally { setSaving(false); }
  };

  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
      <div onClick={(e) => e.stopPropagation()} className="card" style={{ background: 'var(--bg, #fff)', maxWidth: 600, width: '100%', padding: '1.5rem', maxHeight: '90vh', overflow: 'auto' }}>
        <h2>{initial ? `Editar ${initial.nombre}` : 'Nuevo paquete'}</h2>
        <form onSubmit={submit} style={{ display: 'grid', gap: '0.75rem' }}>
          <label>Nombre <input required maxLength={120} value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} /></label>
          <label>Slug (opcional) <input maxLength={140} value={form.slug || ''} onChange={(e) => setForm({ ...form, slug: e.target.value })} /></label>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
            <label>Tipo
              <select value={form.tipo} onChange={(e) => setForm({ ...form, tipo: e.target.value as any })}>
                <option value="paquete">Paquete</option>
                <option value="membresia">Membresía</option>
              </select>
            </label>
            <label>Moneda <input value={form.moneda} maxLength={3} onChange={(e) => setForm({ ...form, moneda: e.target.value.toUpperCase() })} /></label>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.5rem' }}>
            <label>Consultas <input type="number" min={1} required value={form.consultas_incluidas} onChange={(e) => setForm({ ...form, consultas_incluidas: Number(e.target.value) })} /></label>
            <label>Vigencia (días) <input type="number" min={0} value={form.vigencia_dias ?? ''} onChange={(e) => setForm({ ...form, vigencia_dias: e.target.value ? Number(e.target.value) : null })} /></label>
            <label>Precio (¢) <input type="number" min={0} required value={form.precio_centavos} onChange={(e) => setForm({ ...form, precio_centavos: Number(e.target.value) })} /></label>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
            <label>Descuento % <input type="number" min={0} max={100} value={form.descuento_porcentaje ?? ''} onChange={(e) => setForm({ ...form, descuento_porcentaje: e.target.value ? Number(e.target.value) : null })} /></label>
            <label>Orden <input type="number" value={form.orden_visualizacion ?? 0} onChange={(e) => setForm({ ...form, orden_visualizacion: Number(e.target.value) })} /></label>
          </div>
          <label>Imagen URL <input type="url" value={form.imagen_url || ''} onChange={(e) => setForm({ ...form, imagen_url: e.target.value || null })} /></label>
          <label><input type="checkbox" checked={!!form.activo} onChange={(e) => setForm({ ...form, activo: e.target.checked })} /> Activo</label>
          <label><input type="checkbox" checked={!!form.destacado} onChange={(e) => setForm({ ...form, destacado: e.target.checked })} /> Destacado</label>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '0.5rem' }}>
            <button type="button" onClick={onClose}>Cancelar</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Guardando…' : 'Guardar'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}
