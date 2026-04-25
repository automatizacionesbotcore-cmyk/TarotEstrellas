import { useState, useMemo } from 'react';
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

const DURACIONES = [15, 30, 45, 60, 90, 120, 180, 240];

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

  const imagePreviewUrl = useMemo(() => {
    if (imagenFile) return URL.createObjectURL(imagenFile);
    return null;
  }, [imagenFile]);

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
          <span className="admin-reorder-btns">
            <button className="admin-reorder-btn" disabled={idx === 0} onClick={(e) => { e.stopPropagation(); moveRow(idx, -1); }} aria-label="Subir">&#9650;</button>
            <button className="admin-reorder-btn" disabled={idx === tipos.length - 1} onClick={(e) => { e.stopPropagation(); moveRow(idx, 1); }} aria-label="Bajar">&#9660;</button>
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
          className={`service-pill admin-toggle-pill ${r.activo ? 'estado-confirmada' : 'estado-cancelada'}`}
          onClick={(e) => { e.stopPropagation(); toggleMutation.mutate(r.id); }}
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

  const currentImageUrl = editingTipo?.imagen_url;

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <h1>Servicios</h1>
        <button className="btn-primary btn-shimmer" onClick={openCreate}>+ Nuevo servicio</button>
      </header>

      <AdminTable
        columns={columns}
        rows={tipos}
        loading={query.isLoading}
        rowKey={(r) => r.id}
      />

      {showModal && (
        <div className="admin-modal-backdrop" role="dialog" aria-modal="true" onClick={() => !saving && setShowModal(false)}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()}>
            <div className="admin-modal-header">
              <h2>{editingTipo ? 'Editar servicio' : 'Nuevo servicio'}</h2>
              <button className="admin-modal-close" onClick={() => !saving && setShowModal(false)} aria-label="Cerrar">&times;</button>
            </div>

            {editingTipo && (
              <div className="admin-modal-slug">
                <span className="service-pill">/{editingTipo.slug}</span>
              </div>
            )}

            <div className="admin-modal-body">
              <div className="admin-form">
                {/* Nombre */}
                <label>
                  <span className="admin-form-label-text">Nombre</span>
                  <input
                    type="text"
                    value={form.nombre}
                    onChange={(e) => setForm({ ...form, nombre: e.target.value })}
                    maxLength={100}
                    placeholder="Ej: Lectura de Tarot"
                  />
                  {errors.nombre && <span className="admin-field-error">{errors.nombre[0]}</span>}
                </label>

                {/* Descripcion */}
                <label>
                  <span className="admin-form-label-text">Descripción</span>
                  <textarea
                    rows={3}
                    value={form.descripcion}
                    onChange={(e) => setForm({ ...form, descripcion: e.target.value })}
                    maxLength={500}
                    placeholder="Describe brevemente el servicio..."
                  />
                  {errors.descripcion && <span className="admin-field-error">{errors.descripcion[0]}</span>}
                </label>

                {/* Duracion + Precio en fila */}
                <div className="admin-form-row">
                  <label>
                    <span className="admin-form-label-text">Duración</span>
                    <select value={form.duracion_minutos} onChange={(e) => setForm({ ...form, duracion_minutos: Number(e.target.value) })}>
                      {DURACIONES.map((d) => <option key={d} value={d}>{d} min</option>)}
                    </select>
                  </label>

                  <label>
                    <span className="admin-form-label-text">Precio ({form.moneda === 'CLP' ? 'pesos' : 'USD'})</span>
                    <input
                      type="number"
                      min="0"
                      value={precioPesos}
                      onChange={(e) => setPrecioPesos(e.target.value)}
                      placeholder="45000"
                    />
                    {errors.precio_referencial_centavos && <span className="admin-field-error">{errors.precio_referencial_centavos[0]}</span>}
                  </label>
                </div>

                {/* Moneda + Color en fila */}
                <div className="admin-form-row">
                  <label>
                    <span className="admin-form-label-text">Moneda</span>
                    <select value={form.moneda} onChange={(e) => setForm({ ...form, moneda: e.target.value })}>
                      <option value="CLP">CLP (Pesos chilenos)</option>
                      <option value="USD">USD (Dólares)</option>
                    </select>
                  </label>

                  <label className="admin-color-field">
                    <span className="admin-form-label-text">Color</span>
                    <div className="admin-color-picker-wrap">
                      <input type="color" value={form.color_hex} onChange={(e) => setForm({ ...form, color_hex: e.target.value })} />
                      <span className="admin-color-value">{form.color_hex}</span>
                    </div>
                  </label>
                </div>

                {/* Checkbox datos natales */}
                <label className="admin-checkbox-row">
                  <input
                    type="checkbox"
                    checked={form.requiere_datos_natales}
                    onChange={(e) => setForm({ ...form, requiere_datos_natales: e.target.checked })}
                  />
                  <span>Requiere datos natales del consultante</span>
                </label>

                {/* Imagen */}
                <div className="admin-image-section">
                  <span className="admin-form-label-text">Imagen del servicio</span>

                  {(currentImageUrl || imagePreviewUrl) && (
                    <div className="admin-image-preview">
                      <img
                        src={imagePreviewUrl ?? currentImageUrl!}
                        alt={editingTipo?.nombre ?? 'Vista previa'}
                      />
                      {currentImageUrl && !imagePreviewUrl && (
                        <button type="button" className="admin-image-remove" onClick={handleDeleteImagen} aria-label="Eliminar imagen">&times;</button>
                      )}
                    </div>
                  )}

                  <label className="admin-image-upload-area">
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      onChange={(e) => setImagenFile(e.target.files?.[0] ?? null)}
                      hidden
                    />
                    <span className="admin-upload-icon">&#128247;</span>
                    <span>{imagenFile ? imagenFile.name : 'Seleccionar imagen'}</span>
                    <span className="admin-upload-hint">JPG, PNG o WebP. Máx 2 MB</span>
                  </label>
                </div>
              </div>
            </div>

            <div className="admin-modal-footer">
              <button type="button" className="btn-secondary" onClick={() => setShowModal(false)} disabled={saving}>Cancelar</button>
              <button type="button" className="btn-primary" onClick={handleSave} disabled={saving}>
                {saving ? 'Guardando...' : editingTipo ? 'Guardar cambios' : 'Crear servicio'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
