import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';

type CuentaBancaria = {
  id: number;
  banco: string;
  tipo_cuenta: string;
  numero_cuenta: string;
  nombre_titular: string;
  rut_titular: string;
  orden: number;
  activa: boolean;
};

type FormState = {
  banco: string;
  tipo_cuenta: string;
  numero_cuenta: string;
  nombre_titular: string;
  rut_titular: string;
};

const EMPTY_FORM: FormState = {
  banco: '',
  tipo_cuenta: 'Corriente',
  numero_cuenta: '',
  nombre_titular: '',
  rut_titular: '',
};

const TIPO_CUENTA_OPTIONS = ['Corriente', 'Vista', 'Ahorro', 'RUT', 'Chequera Electrónica'];

function fetchCuentas() {
  return api.get<{ data: CuentaBancaria[] }>('/admin/cuentas-bancarias').then((r) => r.data.data);
}

export function AdminCuentasBancariasPage() {
  const qc = useQueryClient();
  const [editing, setEditing]   = useState<CuentaBancaria | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm]         = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState('');

  const { data: cuentas = [], isLoading } = useQuery({
    queryKey: ['admin', 'cuentas-bancarias'],
    queryFn:  fetchCuentas,
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: ['admin', 'cuentas-bancarias'] });

  const createMutation = useMutation({
    mutationFn: (data: FormState) => api.post('/admin/cuentas-bancarias', data),
    onSuccess:  () => { setCreating(false); setForm(EMPTY_FORM); setFormError(''); invalidate(); },
    onError:    (e: any) => setFormError(e?.response?.data?.message ?? 'Error al crear la cuenta.'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<FormState> }) =>
      api.put(`/admin/cuentas-bancarias/${id}`, data),
    onSuccess:  () => { setEditing(null); setForm(EMPTY_FORM); setFormError(''); invalidate(); },
    onError:    (e: any) => setFormError(e?.response?.data?.message ?? 'Error al actualizar.'),
  });

  const toggleMutation = useMutation({
    mutationFn: (cuenta: CuentaBancaria) =>
      api.put(`/admin/cuentas-bancarias/${cuenta.id}`, { activa: !cuenta.activa }),
    onSuccess:  invalidate,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/cuentas-bancarias/${id}`),
    onSuccess:  invalidate,
  });

  const handleEdit = (cuenta: CuentaBancaria) => {
    setEditing(cuenta);
    setCreating(false);
    setFormError('');
    setForm({
      banco:          cuenta.banco,
      tipo_cuenta:    cuenta.tipo_cuenta,
      numero_cuenta:  cuenta.numero_cuenta,
      nombre_titular: cuenta.nombre_titular,
      rut_titular:    cuenta.rut_titular,
    });
  };

  const handleCreate = () => {
    setEditing(null);
    setCreating(true);
    setFormError('');
    setForm(EMPTY_FORM);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    if (!form.banco || !form.numero_cuenta || !form.nombre_titular || !form.rut_titular) {
      setFormError('Todos los campos son obligatorios.');
      return;
    }
    if (editing) {
      updateMutation.mutate({ id: editing.id, data: form });
    } else {
      createMutation.mutate(form);
    }
  };

  const isSubmitting = createMutation.isPending || updateMutation.isPending;

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">✦ Pagos</p>
          <h1>Cuentas bancarias</h1>
          <p className="text-muted" style={{ fontSize: '0.9rem' }}>
            Configura hasta 3 cuentas para recibir transferencias de clientes chilenos.
          </p>
        </div>
        {cuentas.length < 3 && !creating && !editing && (
          <button type="button" className="btn-primary" onClick={handleCreate}>
            + Agregar cuenta
          </button>
        )}
      </header>

      {/* Formulario crear/editar */}
      {(creating || editing) && (
        <form
          onSubmit={handleSubmit}
          className="card"
          style={{ padding: '1.5rem', marginBottom: '1.5rem' }}
        >
          <h3 style={{ marginTop: 0, marginBottom: '1rem' }}>
            {editing ? 'Editar cuenta' : 'Nueva cuenta bancaria'}
          </h3>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>Banco *</span>
              <input
                className="input-field"
                value={form.banco}
                onChange={(e) => setForm((f) => ({ ...f, banco: e.target.value }))}
                placeholder="BancoEstado, Santander…"
                maxLength={100}
              />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>Tipo de cuenta *</span>
              <select
                className="input-field"
                value={form.tipo_cuenta}
                onChange={(e) => setForm((f) => ({ ...f, tipo_cuenta: e.target.value }))}
              >
                {TIPO_CUENTA_OPTIONS.map((t) => <option key={t}>{t}</option>)}
              </select>
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>N° de cuenta *</span>
              <input
                className="input-field"
                value={form.numero_cuenta}
                onChange={(e) => setForm((f) => ({ ...f, numero_cuenta: e.target.value }))}
                placeholder="0000000000"
                maxLength={40}
              />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>Nombre titular *</span>
              <input
                className="input-field"
                value={form.nombre_titular}
                onChange={(e) => setForm((f) => ({ ...f, nombre_titular: e.target.value }))}
                placeholder="Nombre completo"
                maxLength={120}
              />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>RUT titular *</span>
              <input
                className="input-field"
                value={form.rut_titular}
                onChange={(e) => setForm((f) => ({ ...f, rut_titular: e.target.value }))}
                placeholder="12.345.678-9"
                maxLength={20}
              />
            </label>
          </div>

          {formError && <p className="form-error" style={{ marginTop: '0.75rem' }}>{formError}</p>}

          <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.25rem' }}>
            <button type="submit" className="btn-primary" disabled={isSubmitting}>
              {isSubmitting ? 'Guardando…' : (editing ? 'Guardar cambios' : 'Crear cuenta')}
            </button>
            <button
              type="button"
              className="btn-secondary"
              onClick={() => { setEditing(null); setCreating(false); setFormError(''); }}
            >
              Cancelar
            </button>
          </div>
        </form>
      )}

      {/* Lista de cuentas */}
      {isLoading ? (
        <p className="text-muted">Cargando…</p>
      ) : cuentas.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <span style={{ fontSize: '2rem' }}>🏦</span>
          <p className="text-muted">No hay cuentas bancarias configuradas.</p>
          {!creating && (
            <button type="button" className="btn-primary" onClick={handleCreate}>
              Agregar primera cuenta
            </button>
          )}
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {cuentas.map((cuenta, index) => (
            <div
              key={cuenta.id}
              className="card"
              style={{
                padding: '1.25rem',
                border: cuenta.activa ? '1px solid var(--accent)' : '1px solid var(--border)',
                opacity: cuenta.activa ? 1 : 0.6,
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem' }}>
                    <span style={{ fontWeight: 700, fontSize: '1rem' }}>
                      Cuenta {index + 1} — {cuenta.banco}
                    </span>
                    <span
                      style={{
                        fontSize: '0.7rem',
                        padding: '2px 8px',
                        borderRadius: '99px',
                        background: cuenta.activa ? 'var(--accent)' : 'var(--border)',
                        color: cuenta.activa ? '#fff' : 'var(--text-muted)',
                      }}
                    >
                      {cuenta.activa ? 'Activa' : 'Inactiva'}
                    </span>
                  </div>
                  <dl style={{ margin: 0, display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '0.15rem 0.75rem', fontSize: '0.9rem' }}>
                    <dt style={{ color: 'var(--text-muted)' }}>Tipo</dt>     <dd style={{ margin: 0 }}>{cuenta.tipo_cuenta}</dd>
                    <dt style={{ color: 'var(--text-muted)' }}>N° cuenta</dt><dd style={{ margin: 0 }}>{cuenta.numero_cuenta}</dd>
                    <dt style={{ color: 'var(--text-muted)' }}>Titular</dt>  <dd style={{ margin: 0 }}>{cuenta.nombre_titular}</dd>
                    <dt style={{ color: 'var(--text-muted)' }}>RUT</dt>      <dd style={{ margin: 0 }}>{cuenta.rut_titular}</dd>
                  </dl>
                </div>
                <div style={{ display: 'flex', gap: '0.5rem', flexShrink: 0 }}>
                  <button
                    type="button"
                    className="btn-secondary"
                    style={{ fontSize: '0.8rem', padding: '0.3rem 0.7rem' }}
                    onClick={() => handleEdit(cuenta)}
                    disabled={editing?.id === cuenta.id}
                  >
                    Editar
                  </button>
                  <button
                    type="button"
                    className="btn-secondary"
                    style={{ fontSize: '0.8rem', padding: '0.3rem 0.7rem' }}
                    onClick={() => toggleMutation.mutate(cuenta)}
                    disabled={toggleMutation.isPending}
                  >
                    {cuenta.activa ? 'Desactivar' : 'Activar'}
                  </button>
                  <button
                    type="button"
                    className="btn-danger"
                    style={{ fontSize: '0.8rem', padding: '0.3rem 0.7rem' }}
                    onClick={() => { if (window.confirm('¿Eliminar esta cuenta?')) deleteMutation.mutate(cuenta.id); }}
                    disabled={deleteMutation.isPending}
                  >
                    Eliminar
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {cuentas.length >= 3 && !creating && !editing && (
        <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '1rem' }}>
          Máximo 3 cuentas permitidas. Elimina una para agregar otra.
        </p>
      )}
    </main>
  );
}
