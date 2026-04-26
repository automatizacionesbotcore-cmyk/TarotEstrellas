import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { toast } from '../../../stores/toastStore';

type Especialista = {
  id: number;
  uuid: string;
  email: string;
  nombre: string;
  apellido: string | null;
  telefono: string | null;
  biografia: string | null;
  avatar_url: string | null;
  slug: string | null;
  especialidad: string | null;
  activo: boolean;
  orden: number;
  tiene_perfil: boolean;
};

const EMPTY_FORM = {
  nombre: '', apellido: '', email: '', slug: '', especialidad: '', orden: 0,
};
const EMPTY_EDIT = {
  nombre: '', apellido: '', slug: '', especialidad: '', biografia: '', orden: 0,
};

export function AdminEspecialistasPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<'lista' | 'nuevo'>('lista');
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [editForm, setEditForm] = useState(EMPTY_EDIT);

  const { data: especialistas = [], isLoading } = useQuery<Especialista[]>({
    queryKey: ['admin', 'especialistas'],
    queryFn: async () => (await api.get<{ data: Especialista[] }>('/admin/especialistas')).data.data,
  });

  const createMutation = useMutation({
    mutationFn: () => api.post('/admin/especialistas', { ...form, orden: Number(form.orden) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'especialistas'] });
      setForm(EMPTY_FORM);
      setTab('lista');
      toast.success('Especialista creado. Se envió un correo para que configure su contraseña.');
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Error al crear especialista'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: typeof EMPTY_EDIT }) =>
      api.put(`/admin/especialistas/${id}`, { ...data, orden: Number(data.orden) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'especialistas'] });
      setEditId(null);
      toast.success('Especialista actualizado.');
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Error al actualizar'),
  });

  const toggleMutation = useMutation({
    mutationFn: (id: number) => api.patch(`/admin/especialistas/${id}/toggle`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'especialistas'] }),
    onError: () => toast.error('No se pudo cambiar el estado'),
  });

  function startEdit(e: Especialista) {
    setEditId(e.id);
    setEditForm({
      nombre: e.nombre ?? '',
      apellido: e.apellido ?? '',
      slug: e.slug ?? '',
      especialidad: e.especialidad ?? '',
      biografia: e.biografia ?? '',
      orden: e.orden,
    });
  }

  return (
    <main className="page-content">
      <h1>Especialistas</h1>

      <div className="booking-tabs" style={{ marginBottom: '1.5rem' }}>
        <button className={`booking-tab${tab === 'lista' ? ' active' : ''}`} onClick={() => setTab('lista')}>
          Lista ({especialistas.length})
        </button>
        <button className={`booking-tab${tab === 'nuevo' ? ' active' : ''}`} onClick={() => setTab('nuevo')}>
          + Agregar especialista
        </button>
      </div>

      {/* ── Lista ── */}
      {tab === 'lista' && (
        <section>
          {isLoading && <p className="text-muted">Cargando…</p>}
          {!isLoading && especialistas.length === 0 && (
            <p className="text-muted">No hay especialistas registrados.</p>
          )}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {especialistas.map((e) => (
              <div
                key={e.id}
                className="cuenta-section"
                style={{ borderLeft: `3px solid ${e.activo ? 'var(--accent)' : 'var(--border-subtle)'}`, opacity: e.activo ? 1 : 0.65 }}
              >
                {editId === e.id ? (
                  /* ── Formulario edición inline ── */
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                    <h3 style={{ margin: 0 }}>Editando: {e.email}</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                      <label className="booking-field-label">
                        Nombre
                        <input className="input-field" value={editForm.nombre} onChange={(ev) => setEditForm((p) => ({ ...p, nombre: ev.target.value }))} />
                      </label>
                      <label className="booking-field-label">
                        Apellido
                        <input className="input-field" value={editForm.apellido} onChange={(ev) => setEditForm((p) => ({ ...p, apellido: ev.target.value }))} />
                      </label>
                      <label className="booking-field-label">
                        Slug (URL)
                        <input className="input-field" value={editForm.slug} onChange={(ev) => setEditForm((p) => ({ ...p, slug: ev.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') }))} />
                      </label>
                      <label className="booking-field-label">
                        Especialidad
                        <input className="input-field" value={editForm.especialidad} onChange={(ev) => setEditForm((p) => ({ ...p, especialidad: ev.target.value }))} />
                      </label>
                      <label className="booking-field-label" style={{ gridColumn: '1/-1' }}>
                        Biografía
                        <textarea className="input-field" rows={2} value={editForm.biografia} onChange={(ev) => setEditForm((p) => ({ ...p, biografia: ev.target.value }))} style={{ resize: 'vertical' }} />
                      </label>
                      <label className="booking-field-label">
                        Orden de display
                        <input type="number" className="input-field" value={editForm.orden} min={0} onChange={(ev) => setEditForm((p) => ({ ...p, orden: Number(ev.target.value) }))} />
                      </label>
                    </div>
                    <div style={{ display: 'flex', gap: '0.75rem' }}>
                      <button className="btn-primary" disabled={updateMutation.isPending} onClick={() => updateMutation.mutate({ id: e.id, data: editForm })}>
                        {updateMutation.isPending ? 'Guardando…' : 'Guardar'}
                      </button>
                      <button className="btn-secondary" onClick={() => setEditId(null)}>Cancelar</button>
                    </div>
                  </div>
                ) : (
                  /* ── Vista normal ── */
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', flexWrap: 'wrap' }}>
                    <div>
                      <p style={{ fontWeight: 700, fontSize: '1rem', margin: 0 }}>
                        {e.nombre} {e.apellido ?? ''}
                        <span style={{ marginLeft: '0.5rem', fontSize: '0.75rem', color: 'var(--text-muted)' }}>#{e.id}</span>
                      </p>
                      <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '0.15rem 0' }}>{e.email}</p>
                      {e.especialidad && <p style={{ fontSize: '0.85rem', margin: '0.15rem 0' }}>{e.especialidad}</p>}
                      {e.slug && <p style={{ fontSize: '0.78rem', color: 'var(--accent)', margin: 0 }}>/{e.slug} · orden {e.orden}</p>}
                      <span style={{ display: 'inline-block', marginTop: '0.4rem', padding: '0.15rem 0.6rem', borderRadius: '99px', fontSize: '0.72rem', fontWeight: 600, background: e.activo ? 'color-mix(in srgb, var(--accent) 15%, transparent)' : 'var(--border-subtle)', color: e.activo ? 'var(--accent)' : 'var(--text-muted)' }}>
                        {e.activo ? 'Activo' : 'Inactivo'}
                      </span>
                    </div>
                    <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                      <button className="btn-secondary" style={{ fontSize: '0.82rem', padding: '0.3rem 0.8rem' }} onClick={() => startEdit(e)}>
                        Editar
                      </button>
                      <button
                        className="btn-secondary"
                        style={{ fontSize: '0.82rem', padding: '0.3rem 0.8rem', borderColor: e.activo ? 'var(--red, #e05555)' : 'var(--accent)', color: e.activo ? 'var(--red, #e05555)' : 'var(--accent)' }}
                        disabled={toggleMutation.isPending}
                        onClick={() => toggleMutation.mutate(e.id)}
                      >
                        {e.activo ? 'Desactivar' : 'Activar'}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        </section>
      )}

      {/* ── Nuevo especialista ── */}
      {tab === 'nuevo' && (
        <section>
          <div className="bloqueo-form" style={{ maxWidth: 560 }}>
            <h2 style={{ margin: 0, fontSize: '1.1rem' }}>Nuevo especialista</h2>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', margin: 0 }}>
              Se creará la cuenta y se enviará un correo para que configure su contraseña.
            </p>
            <div className="form-row">
              <label className="booking-field-label">
                Nombre *
                <input className="input-field" value={form.nombre} onChange={(e) => setForm((p) => ({ ...p, nombre: e.target.value }))} required />
              </label>
              <label className="booking-field-label">
                Apellido
                <input className="input-field" value={form.apellido} onChange={(e) => setForm((p) => ({ ...p, apellido: e.target.value }))} />
              </label>
            </div>
            <label className="booking-field-label">
              Email *
              <input type="email" className="input-field" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} required />
            </label>
            <div className="form-row">
              <label className="booking-field-label">
                Slug (URL) *
                <input className="input-field" placeholder="ej: chachita" value={form.slug}
                  onChange={(e) => setForm((p) => ({ ...p, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') }))} required />
              </label>
              <label className="booking-field-label">
                Orden de display
                <input type="number" className="input-field" value={form.orden} min={0} onChange={(e) => setForm((p) => ({ ...p, orden: Number(e.target.value) }))} />
              </label>
            </div>
            <label className="booking-field-label">
              Especialidad
              <input className="input-field" placeholder="ej: Tarot, Astrología, Runas" value={form.especialidad}
                onChange={(e) => setForm((p) => ({ ...p, especialidad: e.target.value }))} />
            </label>
            <button
              className="btn-primary"
              disabled={createMutation.isPending || !form.nombre || !form.email || !form.slug}
              onClick={() => createMutation.mutate()}
            >
              {createMutation.isPending ? 'Creando…' : 'Crear especialista'}
            </button>
          </div>
        </section>
      )}
    </main>
  );
}
