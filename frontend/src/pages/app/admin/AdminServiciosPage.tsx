import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import { toast } from '../../../stores/toastStore';

type TipoConsulta = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_referencial_centavos: number;
  moneda: string;
  imagen_url: string | null;
  color_hex: string | null;
  requiere_datos_natales: boolean;
  orden_visualizacion: number;
  activo: boolean;
};

type FormFields = {
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_referencial_centavos: number;
  moneda: string;
  color_hex: string;
  requiere_datos_natales: boolean;
};

const EMPTY_FORM: FormFields = {
  nombre: '',
  descripcion: '',
  duracion_minutos: 60,
  precio_referencial_centavos: 0,
  moneda: 'CLP',
  color_hex: '#6C3FA0',
  requiere_datos_natales: false,
};

function formatPrice(centavos: number, moneda: string) {
  const valor = centavos / 100;
  return moneda === 'CLP'
    ? `$${valor.toLocaleString('es-CL', { maximumFractionDigits: 0 })}`
    : `US$${valor.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
}

export function AdminServiciosPage() {
  const queryClient = useQueryClient();
  const [editingTipo, setEditingTipo] = useState<TipoConsulta | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<FormFields>(EMPTY_FORM);
  const [precioPesos, setPrecioPesos] = useState('');
  const [imagenFile, setImagenFile] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const query = useQuery({
    queryKey: ['admin', 'tipos-consulta'],
    queryFn: async () => (await api.get<{ data: TipoConsulta[] }>('/admin/tipos-consulta')).data.data,
  });

  const toggleMutation = useMutation({
    mutationFn: (id: number) => api.patch(`/admin/tipos-consulta/${id}/toggle`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] }),
  });

  const reorderMutation = useMutation({
    mutationFn: (ids: number[]) => api.patch('/admin/tipos-consulta/reorder', { ids }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] }),
  });

  const tipos = query.data ?? [];

  function openCreate() {
    setEditingTipo(null);
    setForm(EMPTY_FORM);
    setPrecioPesos('');
    setImagenFile(null);
    setErrors({});
    setShowModal(true);
  }

  function openEdit(tipo: TipoConsulta) {
    setEditingTipo(tipo);
    setForm({
      nombre: tipo.nombre,
      descripcion: tipo.descripcion,
      duracion_minutos: tipo.duracion_minutos,
      precio_referencial_centavos: tipo.precio_referencial_centavos,
      moneda: tipo.moneda,
      color_hex: tipo.color_hex ?? '#6C3FA0',
      requiere_datos_natales: tipo.requiere_datos_natales,
    });
    setPrecioPesos(String(tipo.precio_referencial_centavos / 100));
    setImagenFile(null);
    setErrors({});
    setShowModal(true);
  }

  async function handleSave() {
    setSaving(true);
    setErrors({});
    const payload = { ...form, precio_referencial_centavos: Math.round(Number(precioPesos) * 100) };

    try {
      if (editingTipo) {
        await api.put(`/admin/tipos-consulta/${editingTipo.id}`, payload);
        if (imagenFile) {
          const fd = new FormData();
          fd.append('imagen', imagenFile);
          await api.post(`/admin/tipos-consulta/${editingTipo.id}/imagen`, fd);
        }
        toast.success('Servicio actualizado.');
      } else {
        const res = await api.post('/admin/tipos-consulta', payload);
        const newId = (res.data as { data: TipoConsulta }).data.id;
        if (imagenFile) {
          const fd = new FormData();
          fd.append('imagen', imagenFile);
          await api.post(`/admin/tipos-consulta/${newId}/imagen`, fd);
        }
        toast.success('Servicio creado.');
      }
      setShowModal(false);
      queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] });
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (axiosErr.response?.data?.errors) {
        setErrors(axiosErr.response.data.errors);
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleDeleteImagen() {
    if (!editingTipo) return;
    await api.delete(`/admin/tipos-consulta/${editingTipo.id}/imagen`);
    setEditingTipo({ ...editingTipo, imagen_url: null });
    queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] });
    toast.success('Imagen eliminada.');
  }

  function moveRow(index: number, direction: -1 | 1) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= tipos.length) return;
    const newOrder = [...tipos];
    [newOrder[index], newOrder[newIndex]] = [newOrder[newIndex], newOrder[index]];
    reorderMutation.mutate(newOrder.map((t) => t.id));
  }

  const columns: Column<TipoConsulta>[] = [
    {
      key: 'orden',
      label: 'Orden',
      render: (row) => {
        const idx = tipos.findIndex((t) => t.id === row.id);
        return (
          <span style={{ display: 'flex', gap: '0.25rem' }}>
            <button className="btn-icon" disabled={idx === 0} onClick={(e) => { e.stopPropagation(); moveRow(idx, -1); }} aria-label="Subir">{'▲'}</button>
            <button className="btn-icon" disabled={idx === tipos.length - 1} onClick={(e) => { e.stopPropagation(); moveRow(idx, 1); }} aria-label="Bajar">{'▼'}</button>
          </span>
        );
      },
    },
    { key: 'nombre', label: 'Nombre' },
    { key: 'duracion', label: 'Duración', render: (r) => `${r.duracion_minutos} min` },
    { key: 'precio', label: 'Precio', render: (r) => formatPrice(r.precio_referencial_centavos, r.moneda) },
    {
      key: 'estado',
      label: 'Estado',
      render: (r) => (
        <button
          className={`service-pill ${r.activo ? 'estado-confirmada' : 'estado-cancelada'}`}
          onClick={(e) => { e.stopPropagation(); toggleMutation.mutate(r.id); }}
          style={{ cursor: 'pointer' }}
        >
          {r.activo ? 'Activo' : 'Inactivo'}
        </button>
      ),
    },
    {
      key: 'acciones',
      label: '',
      render: (r) => (
        <button className="btn-secondary" onClick={(e) => { e.stopPropagation(); openEdit(r); }}>
          Editar
        </button>
      ),
    },
  ];

  const DURACIONES = [15, 30, 45, 60, 90, 120, 180, 240];

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <h1>Servicios (Tipos de Consulta)</h1>
        <button className="btn-primary" onClick={openCreate}>Nuevo servicio</button>
      </header>

      <AdminTable
        columns={columns}
        rows={tipos}
        loading={query.isLoading}
        rowKey={(r) => r.id}
      />

      {showModal && (
        <div className="confirm-dialog-backdrop" role="dialog" aria-modal="true">
          <div className="confirm-dialog" style={{ maxWidth: '540px', width: '100%' }}>
            <h3>{editingTipo ? 'Editar servicio' : 'Nuevo servicio'}</h3>

            {editingTipo && (
              <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: '0.5rem' }}>
                Slug: <code>{editingTipo.slug}</code>
              </p>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <label className="form-label">
                Nombre
                <input className="form-input" value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} maxLength={100} />
                {errors.nombre && <span className="form-error">{errors.nombre[0]}</span>}
              </label>

              <label className="form-label">
                Descripción
                <textarea className="form-input" rows={3} value={form.descripcion} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} maxLength={500} />
                {errors.descripcion && <span className="form-error">{errors.descripcion[0]}</span>}
              </label>

              <label className="form-label">
                Duración (minutos)
                <select className="form-input" value={form.duracion_minutos} onChange={(e) => setForm({ ...form, duracion_minutos: Number(e.target.value) })}>
                  {DURACIONES.map((d) => <option key={d} value={d}>{d} min</option>)}
                </select>
              </label>

              <label className="form-label">
                Precio ({form.moneda === 'CLP' ? 'pesos' : 'dólares'})
                <input className="form-input" type="number" min="0" value={precioPesos} onChange={(e) => setPrecioPesos(e.target.value)} />
                {errors.precio_referencial_centavos && <span className="form-error">{errors.precio_referencial_centavos[0]}</span>}
              </label>

              <label className="form-label">
                Moneda
                <select className="form-input" value={form.moneda} onChange={(e) => setForm({ ...form, moneda: e.target.value })}>
                  <option value="CLP">CLP</option>
                  <option value="USD">USD</option>
                </select>
              </label>

              <label className="form-label">
                Color
                <input type="color" value={form.color_hex} onChange={(e) => setForm({ ...form, color_hex: e.target.value })} />
              </label>

              <label className="form-label" style={{ flexDirection: 'row', gap: '0.5rem', alignItems: 'center' }}>
                <input type="checkbox" checked={form.requiere_datos_natales} onChange={(e) => setForm({ ...form, requiere_datos_natales: e.target.checked })} />
                Requiere datos natales
              </label>

              <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: '0.75rem' }}>
                <p style={{ fontWeight: 600, marginBottom: '0.5rem' }}>Imagen</p>
                {editingTipo?.imagen_url && (
                  <div style={{ marginBottom: '0.5rem' }}>
                    <img
                      src={editingTipo.imagen_url}
                      alt={editingTipo.nombre}
                      style={{ maxWidth: '120px', borderRadius: '8px' }}
                    />
                    <button className="btn-secondary" style={{ marginLeft: '0.5rem' }} onClick={handleDeleteImagen}>Eliminar imagen</button>
                  </div>
                )}
                <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setImagenFile(e.target.files?.[0] ?? null)} />
              </div>
            </div>

            <div className="confirm-dialog-actions" style={{ marginTop: '1rem' }}>
              <button type="button" className="btn-secondary" onClick={() => setShowModal(false)} disabled={saving}>Cancelar</button>
              <button type="button" className="btn-primary" onClick={handleSave} disabled={saving}>
                {saving ? 'Guardando…' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
