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

type PrecioMoneda = {
  moneda: string;
  precio_centavos: number;
  vigente_desde: string;
  vigente_hasta: string | null;
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
const MONEDAS_MULTI = ['CLP', 'USD', 'EUR', 'MXN'] as const;

function formatPrice(centavos: number, moneda: string) {
  const valor = centavos / 100;
  return moneda === 'CLP'
    ? `$${valor.toLocaleString('es-CL', { maximumFractionDigits: 0 })}`
    : `US$${valor.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
}

function centavosToDisplay(centavos: number, moneda: string): string {
  if (centavos === 0) return '';
  const val = centavos / 100;
  return moneda === 'CLP' ? String(Math.round(val)) : val.toFixed(2);
}

function displayToCentavos(display: string): number {
  return Math.round(parseFloat(display || '0') * 100);
}

export function AdminServiciosPage() {
  const queryClient = useQueryClient();
  const [editingTipo, setEditingTipo] = useState<TipoConsulta | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [modalTab, setModalTab] = useState<'info' | 'precios'>('info');
  const [form, setForm] = useState<FormFields>(EMPTY_FORM);
  const [precioPesos, setPrecioPesos] = useState('');
  const [imagenFile, setImagenFile] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [preciosDraft, setPreciosDraft] = useState<Record<string, string>>({});
  const [savingPrecios, setSavingPrecios] = useState(false);

  const query = useQuery({
    queryKey: ['admin', 'tipos-consulta'],
    queryFn: async () => (await api.get<{ data: TipoConsulta[] }>('/admin/tipos-consulta')).data.data,
  });

  const preciosQuery = useQuery<PrecioMoneda[]>({
    queryKey: ['admin', 'tipos-consulta-precios', editingTipo?.id],
    queryFn: async () =>
      (await api.get<{ data: PrecioMoneda[] }>(`/admin/tipos-consulta/${editingTipo!.id}/precios`)).data.data,
    enabled: Boolean(editingTipo?.id) && modalTab === 'precios',
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
    setModalTab('info');
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
    setPreciosDraft({});
    setModalTab('info');
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

  async function handleSavePrecios() {
    if (!editingTipo) return;
    setSavingPrecios(true);
    try {
      const activosPorMoneda: Record<string, number> = {};
      (preciosQuery.data ?? [])
        .filter((p) => p.vigente_hasta === null)
        .forEach((p) => { activosPorMoneda[p.moneda] = p.precio_centavos; });

      const precios = MONEDAS_MULTI.map((m) => {
        const draft = preciosDraft[m];
        const centavos = draft !== undefined
          ? displayToCentavos(draft)
          : (activosPorMoneda[m] ?? 0);
        return { moneda: m, precio_centavos: centavos };
      }).filter((p) => p.precio_centavos > 0);

      await api.put(`/admin/tipos-consulta/${editingTipo.id}/precios`, { precios });
      queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta-precios', editingTipo.id] });
      setPreciosDraft({});
      toast.success('Precios actualizados.');
    } catch {
      toast.error('No se pudieron guardar los precios.');
    } finally {
      setSavingPrecios(false);
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

  function getActivePrecio(moneda: string): number {
    return (preciosQuery.data ?? [])
      .filter((p) => p.moneda === moneda && p.vigente_hasta === null)
      .at(0)?.precio_centavos ?? 0;
  }

  function getDraftValue(moneda: string): string {
    if (preciosDraft[moneda] !== undefined) return preciosDraft[moneda];
    const active = getActivePrecio(moneda);
    return centavosToDisplay(active, moneda);
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
    { key: 'precio', label: 'Precio ref.', render: (r) => formatPrice(r.precio_referencial_centavos, r.moneda) },
    {
      key: 'estado',
      label: 'Estado',
      render: (r) => (
        <button
          className={`service-pill admin-toggle-pill ${r.activo ? 'estado-confirmada' : 'estado-cancelada'}`}
          onClick={(e) => { e.stopPropagation(); toggleMutation.mutate(r.id); }}
          disabled={toggleMutation.isPending}
        >
          {toggleMutation.isPending ? 'Actualizando…' : (r.activo ? 'Activo' : 'Inactivo')}
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
  const hasPreciosDraft = Object.keys(preciosDraft).length > 0;

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

            {/* Tabs — solo en edición */}
            {editingTipo && (
              <div className="booking-tabs" style={{ padding: '0 1.5rem', marginBottom: 0 }}>
                <button
                  className={`booking-tab${modalTab === 'info' ? ' active' : ''}`}
                  onClick={() => setModalTab('info')}
                >
                  Información
                </button>
                <button
                  className={`booking-tab${modalTab === 'precios' ? ' active' : ''}`}
                  onClick={() => setModalTab('precios')}
                >
                  Precios por moneda
                </button>
              </div>
            )}

            <div className="admin-modal-body">
              {/* ── Tab: Información ── */}
              {modalTab === 'info' && (
                <div className="admin-form">
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

                  <div className="admin-form-row">
                    <label>
                      <span className="admin-form-label-text">Duración</span>
                      <select value={form.duracion_minutos} onChange={(e) => setForm({ ...form, duracion_minutos: Number(e.target.value) })}>
                        {DURACIONES.map((d) => <option key={d} value={d}>{d} min</option>)}
                      </select>
                    </label>

                    <label>
                      <span className="admin-form-label-text">Precio referencial ({form.moneda === 'CLP' ? 'pesos' : 'USD'})</span>
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

                  <div className="admin-form-row">
                    <label>
                      <span className="admin-form-label-text">Moneda base</span>
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

                  <label className="admin-checkbox-row">
                    <input
                      type="checkbox"
                      checked={form.requiere_datos_natales}
                      onChange={(e) => setForm({ ...form, requiere_datos_natales: e.target.checked })}
                    />
                    <span>Requiere datos natales del consultante</span>
                  </label>

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
              )}

              {/* ── Tab: Precios por moneda ── */}
              {modalTab === 'precios' && (
                <div className="admin-form">
                  <p className="text-muted" style={{ marginBottom: '1rem', fontSize: '0.875rem' }}>
                    Define precios específicos por moneda. Dejar en blanco una moneda la excluye del catálogo.
                    Al guardar se crea un nuevo precio vigente (el anterior queda en el historial).
                  </p>

                  {preciosQuery.isLoading ? (
                    <p className="text-muted">Cargando precios…</p>
                  ) : (
                    <div className="precios-moneda-grid">
                      {MONEDAS_MULTI.map((m) => (
                        <label key={m} className="precio-moneda-field">
                          <span className="admin-form-label-text">
                            {m}
                            {m === 'CLP' && ' — Pesos chilenos'}
                            {m === 'USD' && ' — Dólares USD'}
                            {m === 'EUR' && ' — Euros'}
                            {m === 'MXN' && ' — Pesos mexicanos'}
                          </span>
                          <div className="precio-moneda-input-wrap">
                            <span className="precio-moneda-symbol">
                              {m === 'CLP' ? '$' : m === 'USD' ? 'US$' : m === 'EUR' ? '€' : 'MX$'}
                            </span>
                            <input
                              type="number"
                              min="0"
                              step={m === 'CLP' || m === 'MXN' ? '1' : '0.01'}
                              placeholder="0"
                              value={getDraftValue(m)}
                              onChange={(e) => setPreciosDraft((prev) => ({ ...prev, [m]: e.target.value }))}
                            />
                          </div>
                          {getActivePrecio(m) > 0 && (
                            <span className="precio-vigente-hint">
                              Vigente: {formatPrice(getActivePrecio(m), m)}
                            </span>
                          )}
                        </label>
                      ))}
                    </div>
                  )}

                  {hasPreciosDraft && (
                    <button
                      type="button"
                      className="btn-primary"
                      style={{ marginTop: '1rem' }}
                      onClick={handleSavePrecios}
                      disabled={savingPrecios}
                    >
                      {savingPrecios ? 'Guardando precios…' : 'Guardar precios'}
                    </button>
                  )}
                </div>
              )}
            </div>

            {modalTab === 'info' && (
              <div className="admin-modal-footer">
                <button type="button" className="btn-secondary" onClick={() => setShowModal(false)} disabled={saving}>Cancelar</button>
                <button type="button" className="btn-primary" onClick={handleSave} disabled={saving}>
                  {saving ? 'Guardando...' : editingTipo ? 'Guardar cambios' : 'Crear servicio'}
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </main>
  );
}
