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
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

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
  const [busyCodigos, setBusyCodigos] = useState<Set<string>>(new Set());

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
    setBusyCodigos((prev) => new Set([...prev, c.codigo]));
    try {
      await toggleCupon(c.codigo);
      toast.success(`Cupón ${c.codigo} ${c.activo ? 'desactivado' : 'activado'}.`);
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally {
      setBusyCodigos((prev) => { const next = new Set(prev); next.delete(c.codigo); return next; });
    }
  };

  const onDelete = async (c: Cupon) => {
    if (!confirm(`¿Eliminar el cupón ${c.codigo}?`)) return;
    setBusyCodigos((prev) => new Set([...prev, c.codigo]));
    try {
      await eliminarCupon(c.codigo);
      toast.success('Cupón eliminado.');
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally {
      setBusyCodigos((prev) => { const next = new Set(prev); next.delete(c.codigo); return next; });
    }
  };

  const columns: Column<Cupon>[] = [
    { key: 'codigo', label: 'Código', render: (c) => <strong>{c.codigo}</strong> },
    { key: 'descripcion', label: 'Descripción', render: (c) => c.descripcion || '—' },
    {
      key: 'descuento',
      label: 'Descuento',
      render: (c) => c.tipo_descuento === 'porcentaje' ? `${c.valor_descuento}%` : `${c.valor_descuento} ${c.moneda || ''}`,
    },
    {
      key: 'vigencia',
      label: 'Vigencia',
      render: (c) => (
        <span style={{ fontSize: '0.85em' }}>
          {new Date(c.vigente_desde).toLocaleDateString()}
          {c.vigente_hasta && ` — ${new Date(c.vigente_hasta).toLocaleDateString()}`}
        </span>
      ),
    },
    { key: 'usos', label: 'Usos', render: (c) => `${c.usos_totales}${c.uso_maximo_total ? ` / ${c.uso_maximo_total}` : ''}` },
    {
      key: 'estado',
      label: 'Estado',
      render: (c) => <span className={`badge ${c.activo ? 'badge-success' : 'badge-muted'}`}>{c.activo ? 'Activo' : 'Inactivo'}</span>,
    },
    {
      key: 'acciones',
      label: 'Acciones',
      render: (c) => (
        <div className="admin-row-actions">
          <button type="button" className="btn-secondary" onClick={() => setEditing(c)} disabled={busyCodigos.has(c.codigo)}>Editar</button>
          <button type="button" className="btn-secondary" onClick={() => onToggle(c)} disabled={busyCodigos.has(c.codigo)}>
            {busyCodigos.has(c.codigo) ? 'Procesando…' : (c.activo ? 'Desactivar' : 'Activar')}
          </button>
          <button type="button" className="btn-danger" onClick={() => onDelete(c)} disabled={busyCodigos.has(c.codigo)}>
            {busyCodigos.has(c.codigo) ? 'Eliminando…' : 'Eliminar'}
          </button>
        </div>
      ),
    },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Ventas</p>
          <h1>Cupones</h1>
          <p className="admin-page-subtitle">Administra descuentos, vigencia y límites de uso.</p>
        </div>
        <button type="button" className="btn-primary" onClick={() => setCreating(true)}>Nuevo cupón</button>
      </header>

      <form
        onSubmit={(e) => { e.preventDefault(); handleSubmit(); }}
        className="admin-filter-card"
      >
        <div className="admin-filter-grid">
          <label>
            Buscar
            <input className="form-input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Código o descripción" />
          </label>
          <label>
            Estado
            <select className="form-input" value={activo} onChange={(e) => setActivo(e.target.value as any)}>
              <option value="all">Todos</option>
              <option value="true">Activos</option>
              <option value="false">Inactivos</option>
            </select>
          </label>
          <label className="admin-checkbox-row admin-filter-check">
            <input type="checkbox" checked={vigentes} onChange={(e) => setVigentes(e.target.checked)} />
            <span>Solo vigentes</span>
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
        emptyLabel="Sin cupones."
        rowKey={(c) => c.id}
        searchable={false}
        pagination={{ currentPage: page, lastPage, onPageChange: setPage }}
      />

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
