import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { useAuthStore } from '../../../stores/authStore';

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const MOTIVOS = ['feriado', 'vacaciones', 'descanso', 'emergencia', 'evento', 'otro'] as const;

type HorarioBase = { id: number; dia_semana: number; hora_inicio: string; hora_fin: string; activo: boolean };
type Bloqueo = {
  id: number;
  tipo: 'bloqueo' | 'apertura_extra';
  motivo: string;
  descripcion?: string;
  fecha_inicio_utc: string;
  fecha_fin_utc: string;
  all_day: boolean;
};

function fmt(iso: string) {
  return new Date(iso).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
}

type EspecialistaSimple = { id: number; nombre: string; slug: string | null };

export function AdminDisponibilidadPage() {
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const isSuperAdmin = Array.isArray(user?.roles) && user.roles.includes('super_admin');
  const [tab, setTab] = useState<'horario' | 'bloqueos'>('horario');
  const [selectedEspecialistaId, setSelectedEspecialistaId] = useState<number | null>(null);

  const { data: especialistas = [] } = useQuery<EspecialistaSimple[]>({
    queryKey: ['admin', 'especialistas', 'simple'],
    queryFn: async () => {
      const res = await api.get<{ data: Array<{ id: number; nombre: string; slug: string | null }> }>('/admin/especialistas');
      return res.data.data;
    },
    enabled: isSuperAdmin,
  });

  const especialistaParam = selectedEspecialistaId
    ? { especialista_id: selectedEspecialistaId }
    : {};

  // ── Horario base ─────────────────────────────────────────────────────────
  const { data: horarios = [] } = useQuery<HorarioBase[]>({
    queryKey: ['admin', 'disponibilidad', 'horario', selectedEspecialistaId],
    queryFn: async () => (await api.get<{ data: HorarioBase[] }>('/admin/disponibilidad/horario', { params: especialistaParam })).data.data,
  });

  const [draftHorarios, setDraftHorarios] = useState<Record<number, { hora_inicio: string; hora_fin: string; activo: boolean }>>({});

  const saveHorario = useMutation({
    mutationFn: async () => {
      // eslint-disable-next-line no-unused-vars
      type HorarioSlot = { hora_inicio: string; hora_fin: string; activo: boolean };
      const base: Record<number, HorarioSlot> = Object.fromEntries(horarios.map((h) => [h.dia_semana, { hora_inicio: h.hora_inicio.slice(0, 5), hora_fin: h.hora_fin.slice(0, 5), activo: h.activo }]));
      const merged: Record<number, HorarioSlot> = { ...base, ...draftHorarios };
      const payload = Object.entries(merged).map(([dia, v]) => ({ dia_semana: Number(dia), ...v }));
      await api.put('/admin/disponibilidad/horario', { horarios: payload, ...especialistaParam });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'disponibilidad', 'horario'] });
      setDraftHorarios({});
    },
  });

  function getDia(dia: number) {
    const draft = draftHorarios[dia];
    const base = horarios.find((h) => h.dia_semana === dia);
    return {
      hora_inicio: draft?.hora_inicio ?? base?.hora_inicio?.slice(0, 5) ?? '10:00',
      hora_fin: draft?.hora_fin ?? base?.hora_fin?.slice(0, 5) ?? '18:00',
      activo: draft?.activo ?? base?.activo ?? false,
    };
  }

  function patchDia(dia: number, patch: Partial<{ hora_inicio: string; hora_fin: string; activo: boolean }>) {
    setDraftHorarios((prev) => ({ ...prev, [dia]: { ...getDia(dia), ...patch } }));
  }

  // ── Bloqueos ─────────────────────────────────────────────────────────────
  const { data: bloqueos = [] } = useQuery<Bloqueo[]>({
    queryKey: ['admin', 'disponibilidad', 'bloqueos', selectedEspecialistaId],
    queryFn: async () => (await api.get<{ data: Bloqueo[] }>('/admin/disponibilidad/bloqueos', { params: especialistaParam })).data.data,
  });

  const [newBloqueo, setNewBloqueo] = useState({
    tipo: 'bloqueo' as 'bloqueo' | 'apertura_extra',
    motivo: 'feriado' as typeof MOTIVOS[number],
    descripcion: '',
    fecha_inicio: '',
    fecha_fin: '',
    all_day: false,
  });

  const storeBloqueo = useMutation({
    mutationFn: async () => api.post('/admin/disponibilidad/bloqueos', { ...newBloqueo, ...especialistaParam }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'disponibilidad', 'bloqueos'] });
      setNewBloqueo({ tipo: 'bloqueo', motivo: 'feriado', descripcion: '', fecha_inicio: '', fecha_fin: '', all_day: false });
    },
  });

  const deleteBloqueo = useMutation({
    mutationFn: async (id: number) => api.delete(`/admin/disponibilidad/bloqueos/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'disponibilidad', 'bloqueos'] }),
  });

  const [anioFeriados, setAnioFeriados] = useState<number>(new Date().getFullYear());
  const seedFeriados = useMutation({
    mutationFn: async () => {
      const res = await api.post<{ message: string; creados: number; omitidos_existentes: number; total_feriados: number }>(
        '/admin/disponibilidad/bloqueos/seed-feriados-chile',
        { anio: anioFeriados, ...especialistaParam }
      );
      return res.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'disponibilidad', 'bloqueos'] }),
  });

  const hasDraft = Object.keys(draftHorarios).length > 0;

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Agenda</p>
          <h1>Disponibilidad</h1>
          <p className="admin-page-subtitle">Gestiona horarios base, bloqueos y aperturas extraordinarias.</p>
        </div>
      </header>

      {isSuperAdmin && especialistas.length > 0 && (
        <div style={{ marginBottom: '1.25rem', maxWidth: 320 }}>
          <label className="booking-field-label">
            Especialista
            <select
              className="input-field"
              value={selectedEspecialistaId ?? ''}
              onChange={(e) => setSelectedEspecialistaId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">Todos (primero activo)</option>
              {especialistas.map((e) => (
                <option key={e.id} value={e.id}>{e.nombre}</option>
              ))}
            </select>
          </label>
        </div>
      )}

      <div className="booking-tabs admin-tabs" style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem' }}>
        <button
          className={tab === 'horario' ? 'btn-primary' : 'btn-secondary'}
          onClick={() => setTab('horario')}
          type="button"
        >
          Horario base
        </button>
        <button
          className={tab === 'bloqueos' ? 'btn-primary' : 'btn-secondary'}
          onClick={() => setTab('bloqueos')}
          type="button"
        >
          Bloqueos y excepciones
        </button>
      </div>

      {/* ── Horario base ── */}
      {tab === 'horario' && (
        <section>
          <p className="text-muted" style={{ marginBottom: '1rem' }}>
            Horario semanal recurrente en hora Chile (America/Santiago).
          </p>
          <div className="disponibilidad-grid">
            {[1, 2, 3, 4, 5, 6, 0].map((dia) => {
              const v = getDia(dia);
              return (
                <div key={dia} className={`disponibilidad-card${v.activo ? ' activo' : ' inactivo'}`}>
                  <div className="disponibilidad-dia">
                    <span>{DIAS[dia]}</span>
                    <label className="toggle-label">
                      <input
                        type="checkbox"
                        checked={v.activo}
                        onChange={(e) => patchDia(dia, { activo: e.target.checked })}
                      />
                      <span className="toggle-slider" />
                    </label>
                  </div>
                  {v.activo && (
                    <div className="disponibilidad-horas">
                      <label>
                        Inicio
                        <input type="time" value={v.hora_inicio} onChange={(e) => patchDia(dia, { hora_inicio: e.target.value })} className="input-field" />
                      </label>
                      <label>
                        Fin
                        <input type="time" value={v.hora_fin} onChange={(e) => patchDia(dia, { hora_fin: e.target.value })} className="input-field" />
                      </label>
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          {hasDraft && (
            <button
              className="btn-primary"
              style={{ marginTop: '1.5rem' }}
              onClick={() => saveHorario.mutate()}
              disabled={saveHorario.isPending}
            >
              {saveHorario.isPending ? 'Guardando…' : 'Guardar cambios'}
            </button>
          )}
          {saveHorario.isSuccess && <p style={{ color: 'var(--accent)', marginTop: '0.5rem' }}>Horario actualizado ✓</p>}
        </section>
      )}

      {/* ── Bloqueos ── */}
      {tab === 'bloqueos' && (
        <section>
          {/* Feriados chilenos por defecto */}
          <div className="card" style={{ padding: '1rem 1.25rem', marginBottom: '1.5rem', display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '0.75rem' }}>
            <div style={{ flex: 1, minWidth: 220 }}>
              <strong style={{ display: 'block', marginBottom: '0.25rem' }}>Feriados chilenos</strong>
              <small className="text-muted">Carga los feriados oficiales del año (no se duplican si ya existen).</small>
            </div>
            <select
              className="input-field"
              value={anioFeriados}
              onChange={(e) => setAnioFeriados(Number(e.target.value))}
              style={{ maxWidth: 110 }}
              disabled={seedFeriados.isPending}
            >
              {Array.from({ length: 4 }, (_, i) => new Date().getFullYear() + i).map((y) => (
                <option key={y} value={y}>{y}</option>
              ))}
            </select>
            <button
              className="btn-primary"
              onClick={() => seedFeriados.mutate()}
              disabled={seedFeriados.isPending}
            >
              {seedFeriados.isPending ? 'Cargando…' : `Cargar feriados ${anioFeriados}`}
            </button>
          </div>
          {seedFeriados.isSuccess && seedFeriados.data && (
            <p style={{ color: 'var(--accent)', marginTop: '-0.75rem', marginBottom: '1rem' }}>
              {seedFeriados.data.creados} feriados nuevos · {seedFeriados.data.omitidos_existentes} ya existían
            </p>
          )}

          <h2 style={{ marginBottom: '1rem' }}>Agregar bloqueo o apertura</h2>
          <p className="text-muted" style={{ marginBottom: '1rem', fontSize: '0.85rem' }}>
            Bloquea un día completo (vacaciones, feriado), un rango horario específico (ej: 14:00 – 16:00) o varias horas en un día laboral.
          </p>
          <div className="bloqueo-form">
            <div className="form-row">
              <label className="booking-field-label">
                Tipo
                <select className="input-field" value={newBloqueo.tipo} onChange={(e) => setNewBloqueo((p) => ({ ...p, tipo: e.target.value as 'bloqueo' | 'apertura_extra' }))}>
                  <option value="bloqueo">Bloqueo (no disponible)</option>
                  <option value="apertura_extra">Apertura extra (disponible)</option>
                </select>
              </label>
              <label className="booking-field-label">
                Motivo
                <select className="input-field" value={newBloqueo.motivo} onChange={(e) => setNewBloqueo((p) => ({ ...p, motivo: e.target.value as typeof MOTIVOS[number] }))}>
                  {MOTIVOS.map((m) => <option key={m} value={m}>{m}</option>)}
                </select>
              </label>
            </div>
            <label className="booking-field-label">
              Descripción <span className="booking-optional">(opcional)</span>
              <input className="input-field" value={newBloqueo.descripcion} onChange={(e) => setNewBloqueo((p) => ({ ...p, descripcion: e.target.value }))} placeholder="Ej: Feriado nacional" />
            </label>
            <div className="form-row">
              <label className="booking-field-label">
                Inicio (hora Chile)
                <input type="datetime-local" className="input-field" value={newBloqueo.fecha_inicio} onChange={(e) => setNewBloqueo((p) => ({ ...p, fecha_inicio: e.target.value }))} />
              </label>
              <label className="booking-field-label">
                Fin (hora Chile)
                <input type="datetime-local" className="input-field" value={newBloqueo.fecha_fin} onChange={(e) => setNewBloqueo((p) => ({ ...p, fecha_fin: e.target.value }))} />
              </label>
            </div>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input type="checkbox" checked={newBloqueo.all_day} onChange={(e) => setNewBloqueo((p) => ({ ...p, all_day: e.target.checked }))} />
              Día completo
            </label>
            <button
              className="btn-primary"
              style={{ marginTop: '1rem' }}
              onClick={() => storeBloqueo.mutate()}
              disabled={storeBloqueo.isPending || !newBloqueo.fecha_inicio || !newBloqueo.fecha_fin}
            >
              {storeBloqueo.isPending ? 'Guardando…' : 'Agregar'}
            </button>
          </div>

          <h2 style={{ margin: '2rem 0 1rem' }}>Bloqueos activos</h2>
          {bloqueos.length === 0 ? (
            <p className="text-muted">Sin bloqueos registrados.</p>
          ) : (
            <div className="admin-breakdown-grid">
              {bloqueos.map((b) => (
                <div key={b.id} className={`admin-breakdown-card${b.tipo === 'bloqueo' ? ' admin-breakdown-pendiente' : ''}`} style={{ borderLeftColor: b.tipo === 'bloqueo' ? 'var(--red, #e05555)' : 'var(--green, #4caf6e)', borderLeftWidth: 3, borderLeftStyle: 'solid' }}>
                  <span className={`badge ${b.tipo === 'bloqueo' ? 'badge-danger' : 'badge-success'}`}>
                    {b.tipo === 'bloqueo' ? 'Bloqueo' : 'Apertura'}
                  </span>
                  <span className="admin-breakdown-label">{b.motivo}{b.descripcion ? ` · ${b.descripcion}` : ''}</span>
                  <span className="admin-breakdown-amount">{fmt(b.fecha_inicio_utc)} → {fmt(b.fecha_fin_utc)}</span>
                  <button
                    onClick={() => deleteBloqueo.mutate(b.id)}
                    disabled={deleteBloqueo.isPending}
                    style={{ marginTop: '0.5rem', background: 'transparent', border: '1px solid var(--border-subtle)', borderRadius: '6px', padding: '0.2rem 0.6rem', cursor: 'pointer', color: 'var(--text-muted)', fontSize: '0.75rem' }}
                  >
                    {deleteBloqueo.isPending ? 'Eliminando…' : 'Eliminar'}
                  </button>
                </div>
              ))}
            </div>
          )}
        </section>
      )}
    </main>
  );
}
