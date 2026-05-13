import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  actualizarNotasCliente,
  eliminarCliente,
  getClienteBriefing,
  getClienteDetalle,
  type BriefingResponse,
  type ClienteDetalle,
} from '../../../lib/clientesAdminApi';
import { AgenteChat } from '../../../components/agente/AgenteChat';
import { toast } from '../../../stores/toastStore';
import { useAuthStore } from '../../../stores/authStore';

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function renderMarkdown(text: string): string {
  let html = escapeHtml(text);
  html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
  html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
  html = html.replace(/^### (.+)$/gm, '<h4>$1</h4>');
  html = html.replace(/^## (.+)$/gm, '<h3>$1</h3>');
  html = html.replace(/\n/g, '<br/>');
  return html;
}

type TabKey = 'resumen' | 'cronologia' | 'natal' | 'chat' | 'pagos' | 'notas' | 'briefing';

const TABS: Array<{ key: TabKey; label: string }> = [
  { key: 'resumen', label: 'Resumen' },
  { key: 'cronologia', label: 'Cronología' },
  { key: 'natal', label: 'Datos natales' },
  { key: 'chat', label: 'Chat IA' },
  { key: 'pagos', label: 'Pagos' },
  { key: 'notas', label: 'Notas privadas' },
  { key: 'briefing', label: 'Pre-consulta' },
];

export function AdminClienteDetallePage() {
  const { uuid } = useParams<{ uuid: string }>();
  const navigate = useNavigate();
  const authUser = useAuthStore((s) => s.user);
  const isSuperAdmin = Array.isArray(authUser?.roles) && authUser.roles.includes('super_admin');
  const [data, setData] = useState<ClienteDetalle | null>(null);
  const [tab, setTab] = useState<TabKey>('resumen');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);

  async function handleEliminar(force = false) {
    if (!uuid || !data) return;
    const nombre = data.profile?.nombre || data.name;
    const msg = force
      ? `⚠️ ELIMINACIÓN EN CASCADA\n\nEsto borrará TODAS las citas, pagos, resúmenes y datos de "${nombre}". ¿Continuar?`
      : `¿Eliminar definitivamente al cliente "${nombre}" (${data.email})? Esta acción no se puede deshacer.`;
    if (!window.confirm(msg)) return;
    setDeleting(true);
    try {
      const res = await eliminarCliente(uuid, force);
      toast.success(res.message || 'Cliente eliminado.');
      navigate('/app/admin/clientes');
    } catch (e: any) {
      const status = e?.response?.status;
      const payload = e?.response?.data;
      if (status === 409 && payload?.requires_force) {
        if (window.confirm(`${payload.message}\n\n¿Forzar eliminación?`)) {
          setDeleting(false);
          return handleEliminar(true);
        }
      } else {
        toast.error(payload?.message || 'No se pudo eliminar el cliente.');
      }
    } finally {
      setDeleting(false);
    }
  }

  useEffect(() => {
    if (!uuid) return;
    setLoading(true);
    setError(null);
    getClienteDetalle(uuid)
      .then((r) => setData(r.data))
      .catch((e) => setError(e?.response?.data?.message || 'Error cargando cliente'))
      .finally(() => setLoading(false));
  }, [uuid]);

  if (loading) return <main className="page-content"><p>Cargando…</p></main>;
  if (error) return <main className="page-content"><p style={{ color: 'var(--danger)' }}>{error}</p></main>;
  if (!data || !uuid) return <main className="page-content"><p>Cliente no encontrado.</p></main>;

  return (
    <main className="page-content">
      <header style={{ marginBottom: '1rem' }}>
        <Link to="/app/admin/clientes" style={{ fontSize: '0.9em' }}>← Volver a clientes</Link>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '0.5rem', marginTop: '0.5rem' }}>
          <div>
            <h1 style={{ margin: 0 }}>
              {data.profile?.nombre || data.name}{data.profile?.apellido ? ` ${data.profile.apellido}` : ''}
            </h1>
            <p style={{ color: 'var(--text-muted)', margin: '0.25rem 0 0' }}>
              {data.email} · Cliente desde {data.created_at ? new Date(data.created_at).toLocaleDateString() : '—'}
            </p>
          </div>
          {isSuperAdmin && (
            <button
              type="button"
              onClick={() => handleEliminar(false)}
              disabled={deleting}
              className="btn-danger"
              style={{ padding: '0.5rem 0.9rem', background: '#c0392b', color: '#fff', border: 'none', borderRadius: '8px', cursor: 'pointer', fontWeight: 600 }}
            >
              {deleting ? 'Eliminando…' : '🗑 Eliminar cliente'}
            </button>
          )}
        </div>
      </header>

      <nav style={{ display: 'flex', gap: '0.25rem', borderBottom: '1px solid var(--border)', marginBottom: '1rem', overflowX: 'auto' }}>
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            style={{
              padding: '0.5rem 1rem',
              border: 'none',
              background: 'transparent',
              borderBottom: tab === t.key ? '2px solid var(--accent)' : '2px solid transparent',
              cursor: 'pointer',
              fontWeight: tab === t.key ? 600 : 400,
              color: tab === t.key ? 'var(--accent)' : 'var(--text)',
              whiteSpace: 'nowrap',
            }}
          >
            {t.label}
          </button>
        ))}
      </nav>

      {tab === 'resumen' && <TabResumen data={data} />}
      {tab === 'cronologia' && <TabCronologia data={data} />}
      {tab === 'natal' && <TabNatal data={data} />}
      {tab === 'chat' && (
        <div style={{ minHeight: '60vh' }}>
          <AgenteChat mode="admin" clienteUuid={uuid} />
        </div>
      )}
      {tab === 'pagos' && <TabPagos data={data} />}
      {tab === 'notas' && <TabNotas uuid={uuid} initial={data.notas_admin} onSaved={(notas) => setData({ ...data, notas_admin: notas })} />}
      {tab === 'briefing' && <TabBriefing uuid={uuid} citas={data.citas} />}
    </main>
  );
}

function TabResumen({ data }: { data: ClienteDetalle }) {
  const s = data.stats;
  return (
    <div style={{ display: 'grid', gap: '1rem', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
      <Card label="Consultas completadas" value={s.total_completadas} />
      <Card label="Confirmadas pendientes" value={s.total_confirmadas} />
      <Card label="Canceladas" value={s.total_canceladas} />
      <Card label="No-show" value={s.total_no_show} highlight={s.total_no_show > 0 ? 'warn' : undefined} />
      <Card label="Tipo favorito" value={s.tipo_favorito || '—'} />
      <Card label="Primera consulta" value={s.primera_consulta ? new Date(s.primera_consulta).toLocaleDateString() : '—'} />
      <Card label="Última consulta" value={s.ultima_consulta ? new Date(s.ultima_consulta).toLocaleDateString() : '—'} />
      <Card
        label="Ingresos"
        value={Object.entries(s.ingresos_centavos || {}).map(([m, c]) => `${formatMoney(c, m)} ${m}`).join(' · ') || '—'}
      />
    </div>
  );
}

function TabCronologia({ data }: { data: ClienteDetalle }) {
  if (!data.citas.length) return <p className="text-muted">Sin citas registradas.</p>;
  return (
    <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: '0.75rem' }}>
      {data.citas.map((c) => (
        <li key={c.uuid} className="card" style={{ padding: '0.75rem 1rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
            <div>
              <strong>{c.tipo_consulta?.nombre || '—'}</strong> · {c.duracion_minutos} min
              <div style={{ fontSize: '0.85em', color: 'var(--text-muted)' }}>
                {c.inicio_utc ? new Date(c.inicio_utc).toLocaleString() : '—'} · {c.codigo_referencia}
              </div>
            </div>
            <div style={{ textAlign: 'right' }}>
              <span className={`badge badge-${c.estado}`}>{c.estado}</span>
              {c.precio_final_centavos != null && (
                <div style={{ fontSize: '0.85em', color: 'var(--text-muted)' }}>
                  {formatMoney(c.precio_final_centavos, c.moneda || 'CLP')} {c.moneda}
                </div>
              )}
            </div>
          </div>
          {c.tema_principal && <p style={{ marginTop: '0.5rem', color: 'var(--text-muted)' }}>Tema: {c.tema_principal}</p>}
        </li>
      ))}
      {data.resumenes.length > 0 && (
        <li>
          <h3 style={{ marginTop: '1.5rem' }}>Resúmenes IA recientes</h3>
          <div style={{ display: 'grid', gap: '0.75rem' }}>
            {data.resumenes.map((r) => (
              <article key={r.id} className="card" style={{ padding: '0.75rem' }}>
                <header style={{ fontSize: '0.85em', color: 'var(--text-muted)', marginBottom: '0.25rem' }}>
                  {new Date(r.inicio_utc).toLocaleDateString()} · {r.tema_principal || 'sin tema'}
                </header>
                <div style={{ margin: 0 }} dangerouslySetInnerHTML={{ __html: renderMarkdown(r.contenido) }} />
              </article>
            ))}
          </div>
        </li>
      )}
    </ul>
  );
}

function TabNatal({ data }: { data: ClienteDetalle }) {
  if (!data.dato_natal) return <p className="text-muted">El cliente no ha registrado sus datos natales.</p>;
  const n = data.dato_natal;
  return (
    <dl style={{ display: 'grid', gap: '0.5rem 1rem', gridTemplateColumns: 'auto 1fr' }}>
      {Object.entries(n).filter(([k]) => !['id', 'user_id', 'created_at', 'updated_at'].includes(k)).map(([k, v]) => (
        <Pair key={k} label={k.replaceAll('_', ' ')} value={String(v ?? '—')} />
      ))}
    </dl>
  );
}

function TabPagos({ data }: { data: ClienteDetalle }) {
  return (
    <div>
      <p className="text-muted" style={{ marginBottom: '1rem' }}>
        Resumen de ingresos: {Object.entries(data.stats.ingresos_centavos || {}).map(([m, c]) => `${formatMoney(c, m)} ${m}`).join(' · ') || '—'}
      </p>
      <table className="admin-table" style={{ width: '100%' }}>
        <thead>
          <tr><th>Cita</th><th>Fecha</th><th>Estado</th><th style={{ textAlign: 'right' }}>Monto</th></tr>
        </thead>
        <tbody>
          {data.citas.map((c) => (
            <tr key={c.uuid}>
              <td>{c.codigo_referencia}</td>
              <td>{c.inicio_utc ? new Date(c.inicio_utc).toLocaleDateString() : '—'}</td>
              <td>{c.estado}</td>
              <td style={{ textAlign: 'right' }}>{c.precio_final_centavos != null ? `${formatMoney(c.precio_final_centavos, c.moneda || 'CLP')} ${c.moneda}` : '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TabNotas({ uuid, initial, onSaved }: { uuid: string; initial: string | null; onSaved: (n: string | null) => void }) {
  const [value, setValue] = useState(initial || '');
  const [saving, setSaving] = useState(false);

  const save = async () => {
    setSaving(true);
    try {
      const r = await actualizarNotasCliente(uuid, value || null);
      onSaved(r.data.notas_admin);
      toast.success('Notas guardadas.');
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudieron guardar las notas.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <p className="text-muted">Notas privadas, solo visibles para administradores.</p>
      <textarea
        className="input-field"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        rows={12}
        style={{ width: '100%', resize: 'vertical' }}
        placeholder="Observaciones, preferencias, contexto del cliente…"
      />
      <button type="button" className="btn-primary" onClick={save} disabled={saving} style={{ marginTop: '0.5rem' }}>
        {saving ? 'Guardando…' : 'Guardar notas'}
      </button>
    </div>
  );
}

function TabBriefing({ uuid, citas }: { uuid: string; citas: ClienteDetalle['citas'] }) {
  const [citaUuid, setCitaUuid] = useState<string | undefined>(undefined);
  const [briefing, setBriefing] = useState<BriefingResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const proximas = citas.filter((c) => c.estado === 'confirmada' && c.inicio_utc && new Date(c.inicio_utc) > new Date());

  const generar = async () => {
    setLoading(true);
    setError(null);
    try {
      const r = await getClienteBriefing(uuid, citaUuid);
      setBriefing(r.data);
    } catch (e: any) {
      setError(e?.response?.data?.detalle || e?.response?.data?.message || 'No se pudo generar el briefing.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <p className="text-muted">
        Genera un briefing IA para preparar la próxima sesión: temas recurrentes, última sesión, recomendaciones y datos natales.
      </p>
      {proximas.length > 0 && (
        <select
          className="input-field"
          value={citaUuid || ''}
          onChange={(e) => setCitaUuid(e.target.value || undefined)}
          style={{ marginBottom: '0.5rem' }}
        >
          <option value="">Sin cita específica (briefing general)</option>
          {proximas.map((c) => (
            <option key={c.uuid} value={c.uuid}>
              {new Date(c.inicio_utc!).toLocaleString()} — {c.tipo_consulta?.nombre}
            </option>
          ))}
        </select>
      )}
      <div>
        <button type="button" className="btn-primary" onClick={generar} disabled={loading}>
          {loading ? 'Generando…' : briefing ? 'Regenerar briefing' : 'Generar briefing'}
        </button>
      </div>
      {error && <p className="form-error" style={{ marginTop: '0.75rem' }}>{error}</p>}
      {briefing && (
        <article className="card" style={{ marginTop: '1rem', padding: '1rem' }}>
          <header className="text-muted" style={{ fontSize: '0.85rem', marginBottom: '0.5rem' }}>
            Modelo {briefing.modelo} · {briefing.sesiones_consideradas} sesiones consideradas · generado {new Date(briefing.generado_en).toLocaleString()}
          </header>
          <div dangerouslySetInnerHTML={{ __html: renderMarkdown(briefing.contenido) }} />
        </article>
      )}
    </div>
  );
}

function Card({ label, value, highlight }: { label: string; value: React.ReactNode; highlight?: 'warn' }) {
  return (
    <div className="card" style={{ padding: '1rem', borderLeft: highlight === 'warn' ? '3px solid var(--warning, #d68910)' : undefined }}>
      <div style={{ fontSize: '0.85em', color: 'var(--text-muted)' }}>{label}</div>
      <div style={{ fontSize: '1.5rem', fontWeight: 600, marginTop: '0.25rem' }}>{value}</div>
    </div>
  );
}

function Pair({ label, value }: { label: string; value: string }) {
  return (
    <>
      <dt style={{ fontWeight: 600, textTransform: 'capitalize' }}>{label}</dt>
      <dd style={{ margin: 0 }}>{value}</dd>
    </>
  );
}

function formatMoney(centavos: number, moneda: string): string {
  if (moneda === 'CLP') return Math.round(centavos / 100).toLocaleString('es-CL');
  return (centavos / 100).toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
