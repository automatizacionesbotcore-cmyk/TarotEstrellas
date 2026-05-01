import { useEffect, useState } from 'react';
import {
  actualizarCupon,
  crearCupon,
  eliminarCupon,
  listCupones,
  toggleCupon,
  type Cupon,
  type CuponPayload,
} from '../../../lib/cuponesAdminApi';
import { toast } from '../../../stores/toastStore';

export function AdminCuponesPage() {
  const [items, setItems] = useState<Cupon[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [q, setQ] = useState('');
  const [vigentes, setVigentes] = useState(false);
  const [activo, setActivo] = useState<'all' | 'true' | 'false'>('all');
  const [editing, setEditing] = useState<Cupon | null>(null);
  const [creating, setCreating] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const r = await listCupones({
        q: q || undefined,
        vigentes: vigentes || undefined,
        activo: activo === 'all' ? undefined : activo === 'true',
        page,
      });
      setItems(r.data.data);
      setLastPage(r.data.last_page);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error cargando cupones.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page]);

  const handleSubmit = () => {
    setPage(1);
    load();
  };

  const onToggle = async (c: Cupon) => {
    try {
      await toggleCupon(c.codigo);
      toast.success(`Cupón ${c.codigo} ${c.activo ? 'desactivado' : 'activado'}.`);
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    }
  };

  const onDelete = async (c: Cupon) => {
    if (!confirm(`¿Eliminar el cupón ${c.codigo}?`)) return;
    try {
      await eliminarCupon(c.codigo);
      toast.success('Cupón eliminado.');
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    }
  };

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h1>Cupones</h1>
        <button type="button" className="btn-primary" onClick={() => setCreating(true)}>+ Nuevo cupón</button>
      </header>

      <form
        onSubmit={(e) => { e.preventDefault(); handleSubmit(); }}
        style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1rem' }}
      >
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar código o descripción" style={{ flex: 1, minWidth: 200 }} />
        <select value={activo} onChange={(e) => setActivo(e.target.value as any)}>
          <option value="all">Todos</option>
          <option value="true">Activos</option>
          <option value="false">Inactivos</option>
        </select>
        <label style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
          <input type="checkbox" checked={vigentes} onChange={(e) => setVigentes(e.target.checked)} /> Solo vigentes
        </label>
        <button type="submit">Filtrar</button>
      </form>

      {loading ? <p>Cargando…</p> : (
        <table className="data-table" style={{ width: '100%' }}>
          <thead>
            <tr>
              <th>Código</th>
              <th>Descripción</th>
              <th>Descuento</th>
              <th>Vigencia</th>
              <th>Usos</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {items.map((c) => (
              <tr key={c.id}>
                <td><strong>{c.codigo}</strong></td>
                <td>{c.descripcion || '—'}</td>
                <td>
                  {c.tipo_descuento === 'porcentaje' ? `${c.valor_descuento}%` : `${c.valor_descuento} ${c.moneda || ''}`}
                </td>
                <td style={{ fontSize: '0.85em' }}>
                  {new Date(c.vigente_desde).toLocaleDateString()}
                  {c.vigente_hasta && ` — ${new Date(c.vigente_hasta).toLocaleDateString()}`}
                </td>
                <td>{c.usos_totales}{c.uso_maximo_total ? ` / ${c.uso_maximo_total}` : ''}</td>
                <td>
                  <span className={`badge ${c.activo ? 'badge-success' : 'badge-muted'}`}>
                    {c.activo ? 'Activo' : 'Inactivo'}
                  </span>
                </td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button type="button" onClick={() => setEditing(c)}>Editar</button>{' '}
                  <button type="button" onClick={() => onToggle(c)}>{c.activo ? 'Desactivar' : 'Activar'}</button>{' '}
                  <button type="button" onClick={() => onDelete(c)} style={{ color: 'var(--danger)' }}>Eliminar</button>
                </td>
              </tr>
            ))}
            {!items.length && <tr><td colSpan={7} style={{ textAlign: 'center', padding: '2rem' }}>Sin cupones.</td></tr>}
          </tbody>
        </table>
      )}

      <nav style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', marginTop: '1rem' }}>
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button>
        <span>Página {page} de {lastPage}</span>
        <button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>›</button>
      </nav>

      {(creating || editing) && (
        <CuponModal
          initial={editing}
          onClose={() => { setCreating(false); setEditing(null); }}
          onSaved={() => { setCreating(false); setEditing(null); load(); }}
        />
      )}
    </main>
  );
}

function CuponModal({ initial, onClose, onSaved }: { initial: Cupon | null; onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState<CuponPayload>(() => initial ? {
    codigo: initial.codigo,
    descripcion: initial.descripcion,
    tipo_descuento: initial.tipo_descuento,
    valor_descuento: initial.valor_descuento,
    moneda: initial.moneda,
    uso_maximo_total: initial.uso_maximo_total,
    uso_maximo_por_cliente: initial.uso_maximo_por_cliente,
    vigente_desde: initial.vigente_desde.slice(0, 16),
    vigente_hasta: initial.vigente_hasta?.slice(0, 16) || null,
    monto_minimo_centavos: initial.monto_minimo_centavos,
    solo_primera_consulta: initial.solo_primera_consulta,
    activo: initial.activo,
  } : {
    codigo: '',
    descripcion: '',
    tipo_descuento: 'porcentaje',
    valor_descuento: 10,
    vigente_desde: new Date().toISOString().slice(0, 16),
    uso_maximo_por_cliente: 1,
    activo: true,
    solo_primera_consulta: false,
  });
  const [saving, setSaving] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: CuponPayload = {
        ...form,
        codigo: form.codigo.toUpperCase().trim(),
        valor_descuento: Number(form.valor_descuento),
        vigente_desde: new Date(form.vigente_desde).toISOString(),
        vigente_hasta: form.vigente_hasta ? new Date(form.vigente_hasta).toISOString() : null,
      };
      if (initial) {
        await actualizarCupon(initial.codigo, payload);
        toast.success('Cupón actualizado.');
      } else {
        await crearCupon(payload);
        toast.success('Cupón creado.');
      }
      onSaved();
    } catch (e: any) {
      const msg = e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(' · ')
        : e?.response?.data?.message || 'Error.';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
      <div onClick={(e) => e.stopPropagation()} className="card" style={{ background: 'var(--bg, #fff)', maxWidth: 600, width: '100%', padding: '1.5rem', maxHeight: '90vh', overflow: 'auto' }}>
        <h2>{initial ? `Editar ${initial.codigo}` : 'Nuevo cupón'}</h2>
        <form onSubmit={submit} style={{ display: 'grid', gap: '0.75rem' }}>
          <label>Código <input required value={form.codigo} onChange={(e) => setForm({ ...form, codigo: e.target.value.toUpperCase() })} maxLength={30} disabled={!!initial} /></label>
          <label>Descripción <input value={form.descripcion || ''} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} maxLength={255} /></label>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.5rem' }}>
            <label>Tipo
              <select value={form.tipo_descuento} onChange={(e) => setForm({ ...form, tipo_descuento: e.target.value as any })}>
                <option value="porcentaje">Porcentaje</option>
                <option value="monto_fijo">Monto fijo</option>
              </select>
            </label>
            <label>Valor <input type="number" required min={0} step="0.01" value={form.valor_descuento} onChange={(e) => setForm({ ...form, valor_descuento: Number(e.target.value) })} /></label>
            <label>Moneda <input value={form.moneda || ''} onChange={(e) => setForm({ ...form, moneda: e.target.value.toUpperCase() })} maxLength={3} placeholder="CLP" /></label>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
            <label>Vigente desde <input type="datetime-local" required value={form.vigente_desde} onChange={(e) => setForm({ ...form, vigente_desde: e.target.value })} /></label>
            <label>Vigente hasta <input type="datetime-local" value={form.vigente_hasta || ''} onChange={(e) => setForm({ ...form, vigente_hasta: e.target.value || null })} /></label>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.5rem' }}>
            <label>Uso máx total <input type="number" min={1} value={form.uso_maximo_total ?? ''} onChange={(e) => setForm({ ...form, uso_maximo_total: e.target.value ? Number(e.target.value) : null })} /></label>
            <label>Uso máx/cliente <input type="number" min={1} value={form.uso_maximo_por_cliente ?? ''} onChange={(e) => setForm({ ...form, uso_maximo_por_cliente: e.target.value ? Number(e.target.value) : null })} /></label>
            <label>Monto mín (¢) <input type="number" min={0} value={form.monto_minimo_centavos ?? ''} onChange={(e) => setForm({ ...form, monto_minimo_centavos: e.target.value ? Number(e.target.value) : null })} /></label>
          </div>
          <label><input type="checkbox" checked={!!form.solo_primera_consulta} onChange={(e) => setForm({ ...form, solo_primera_consulta: e.target.checked })} /> Solo primera consulta</label>
          <label><input type="checkbox" checked={!!form.activo} onChange={(e) => setForm({ ...form, activo: e.target.checked })} /> Activo</label>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '0.5rem' }}>
            <button type="button" onClick={onClose}>Cancelar</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Guardando…' : 'Guardar'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}
