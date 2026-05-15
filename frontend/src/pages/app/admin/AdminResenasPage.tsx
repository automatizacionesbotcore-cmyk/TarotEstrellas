import { useEffect, useState } from 'react';
import {
  eliminarRespuestaResena,
  listResenas,
  responderResena,
  toggleVisibilidadResena,
  type ResenaAdmin,
} from '../../../lib/resenasAdminApi';
import { toast } from '../../../stores/toastStore';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

export function AdminResenasPage() {
  const [items, setItems] = useState<ResenaAdmin[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [q, setQ] = useState('');
  const [puntuacionMax, setPuntuacionMax] = useState<string>('');
  const [sinResponder, setSinResponder] = useState(false);
  const [visible, setVisible] = useState<'all' | 'true' | 'false'>('all');
  const [responding, setResponding] = useState<ResenaAdmin | null>(null);

  const load = async (
    targetPage = page,
    nextFilters = { q, puntuacionMax, sinResponder, visible }
  ) => {
    setLoading(true);
    try {
      const r = await listResenas({
        q: nextFilters.q || undefined,
        puntuacion_max: nextFilters.puntuacionMax ? Number(nextFilters.puntuacionMax) : undefined,
        sin_responder: nextFilters.sinResponder || undefined,
        visible: nextFilters.visible === 'all' ? undefined : nextFilters.visible === 'true',
        page: targetPage,
      });
      setItems(r.data.data);
      setLastPage(r.data.last_page);
      setTotal(r.data.total);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [page]);

  const onToggleVisibilidad = async (r: ResenaAdmin) => {
    try {
      await toggleVisibilidadResena(r.uuid);
      toast.success(`Reseña ${r.visible ? 'oculta' : 'visible'}.`);
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    }
  };

  const onEliminarRespuesta = async (r: ResenaAdmin) => {
    if (!confirm('¿Eliminar la respuesta del admin?')) return;
    try {
      await eliminarRespuestaResena(r.uuid);
      toast.success('Respuesta eliminada.');
      load();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    }
  };

  const resetFilters = () => {
    const cleared = { q: '', puntuacionMax: '', sinResponder: false, visible: 'all' as const };
    setQ('');
    setPuntuacionMax('');
    setSinResponder(false);
    setVisible('all');
    setPage(1);
    load(1, cleared);
  };

  const columns: Column<ResenaAdmin>[] = [
    {
      key: 'resena',
      label: 'Reseña',
      render: (r) => (
        <div className="admin-review-cell">
          <strong className="admin-review-stars">{'★'.repeat(r.puntuacion)}{'☆'.repeat(5 - r.puntuacion)}</strong>
          <span>{r.comentario || 'Sin comentario'}</span>
          {r.respuesta_admin && <span className="admin-cell-muted">Respuesta: {r.respuesta_admin}</span>}
        </div>
      ),
    },
    {
      key: 'cliente',
      label: 'Cliente',
      render: (r) => (
        <div className="admin-cell-stack">
          <span className="admin-cell-title">{r.cliente?.profile?.nombre || `Cliente #${r.cliente_id}`}</span>
          <span className="admin-cell-muted">{r.especialista?.name || `Especialista #${r.especialista_id}`}</span>
        </div>
      ),
    },
    {
      key: 'cita',
      label: 'Cita',
      render: (r) => (
        <div className="admin-cell-stack">
          <code>{r.cita?.codigo_referencia || `#${r.cita_id}`}</code>
          <span className="admin-cell-muted">{new Date(r.created_at).toLocaleDateString('es-CL')}</span>
        </div>
      ),
    },
    {
      key: 'estado',
      label: 'Estado',
      render: (r) => (
        <div className="admin-row-actions">
          <span className={`badge ${r.visible ? 'badge-success' : 'badge-muted'}`}>{r.visible ? 'Visible' : 'Oculta'}</span>
          <span className={`badge ${r.respuesta_admin ? 'badge-success' : 'badge-warning'}`}>{r.respuesta_admin ? 'Respondida' : 'Pendiente'}</span>
        </div>
      ),
    },
    {
      key: 'acciones',
      label: 'Acciones',
      render: (r) => (
        <div className="admin-row-actions">
          <button type="button" className="btn-secondary" onClick={() => setResponding(r)}>{r.respuesta_admin ? 'Editar' : 'Responder'}</button>
          {r.respuesta_admin && <button type="button" className="btn-secondary" onClick={() => onEliminarRespuesta(r)}>Quitar respuesta</button>}
          <button type="button" className="btn-secondary" onClick={() => onToggleVisibilidad(r)}>{r.visible ? 'Ocultar' : 'Mostrar'}</button>
        </div>
      ),
    },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Calidad</p>
          <h1>Reseñas</h1>
          <p className="admin-page-subtitle">Gestiona visibilidad y respuestas del equipo administrativo.</p>
        </div>
        {total > 0 && <span className="admin-total-pill">{total.toLocaleString()} reseñas</span>}
      </header>

      <form
        onSubmit={(e) => { e.preventDefault(); setPage(1); load(1, { q, puntuacionMax, sinResponder, visible }); }}
        className="admin-filter-card"
      >
        <div className="admin-filter-grid">
          <label>
            Comentario
            <input className="form-input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar comentario" />
          </label>
          <label>
            Puntuación
            <select className="form-input" value={puntuacionMax} onChange={(e) => setPuntuacionMax(e.target.value)}>
              <option value="">Todas</option>
              <option value="2">2 o menos</option>
              <option value="3">3 o menos</option>
            </select>
          </label>
          <label>
            Visibilidad
            <select className="form-input" value={visible} onChange={(e) => setVisible(e.target.value as any)}>
              <option value="all">Todas</option>
              <option value="true">Visibles</option>
              <option value="false">Ocultas</option>
            </select>
          </label>
          <label className="admin-checkbox-row admin-filter-check">
            <input type="checkbox" checked={sinResponder} onChange={(e) => setSinResponder(e.target.checked)} />
            <span>Sin responder</span>
          </label>
        </div>
        <div className="admin-filter-actions">
          <button type="submit" className="btn-primary">Filtrar</button>
          <button type="button" className="btn-secondary" onClick={resetFilters}>Limpiar</button>
        </div>
      </form>

      <AdminTable
        columns={columns}
        rows={items}
        loading={loading}
        emptyLabel="Sin reseñas para los filtros seleccionados."
        rowKey={(r) => r.uuid}
        searchable={false}
        pagination={{
          currentPage: page,
          lastPage,
          total,
          onPageChange: setPage,
        }}
      />

      {responding && (
        <ResponderModal
          resena={responding}
          onClose={() => setResponding(null)}
          onSaved={() => { setResponding(null); load(); }}
        />
      )}
    </main>
  );
}

function ResponderModal({ resena, onClose, onSaved }: { resena: ResenaAdmin; onClose: () => void; onSaved: () => void }) {
  const [text, setText] = useState(resena.respuesta_admin || '');
  const [saving, setSaving] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await responderResena(resena.uuid, text);
      toast.success('Respuesta guardada.');
      onSaved();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Error.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
      <div onClick={(e) => e.stopPropagation()} className="card" style={{ background: 'var(--bg, #fff)', maxWidth: 500, width: '100%', padding: '1.5rem' }}>
        <h2>Responder reseña</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.9em' }}>{resena.comentario}</p>
        <form onSubmit={submit}>
          <textarea required maxLength={2000} rows={6} value={text} onChange={(e) => setText(e.target.value)} style={{ width: '100%', fontFamily: 'inherit', padding: '0.5rem' }} />
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '0.5rem' }}>
            <button type="button" onClick={onClose}>Cancelar</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Guardando…' : 'Guardar'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}
