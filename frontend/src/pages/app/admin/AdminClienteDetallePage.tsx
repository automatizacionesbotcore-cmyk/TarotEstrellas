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
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

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
      <header className="admin-page-header admin-client-header">
        <div>
          <Link to="/app/admin/clientes" className="admin-back-link">Volver a clientes</Link>
          <p className="dash-eyebrow">Ficha de cliente</p>
          <h1>
            {data.profile?.nombre || data.name}{data.profile?.apellido ? ` ${data.profile.apellido}` : ''}
          </h1>
          <p className="admin-page-subtitle">
            {data.email} · Cliente desde {data.created_at ? new Date(data.created_at).toLocaleDateString() : '—'}
          </p>
        </div>
        {isSuperAdmin && (
          <button
            type="button"
            onClick={() => handleEliminar(false)}
            disabled={deleting}
            className="btn-danger"
          >
            {deleting ? 'Eliminando…' : 'Eliminar cliente'}
          </button>
        )}
      </header>

      <nav className="admin-detail-tabs" aria-label="Detalle de cliente">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            className={tab === t.key ? 'is-active' : ''}
          >
            {t.label}
          </button>
        ))}
      </nav>

      {tab === 'resumen' && <TabResumen data={data} />}
      {tab === 'cronologia' && <TabCronologia data={data} />}
      {tab === 'natal' && <TabNatal data={data} />}
      {tab === 'chat' && (
        <section className="admin-ai-chat-shell">
          <AgenteChat mode="admin" clienteUuid={uuid} />
        </section>
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
    <div className="admin-metric-grid">
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
  if (!data.citas.length) return <div className="admin-empty-card"><p className="text-muted">Sin citas registradas.</p></div>;
  return (
    <div className="admin-card-list">
      {data.citas.map((c) => (
        <article key={c.uuid} className="admin-timeline-card">
          <div className="admin-card-row">
            <div>
              <strong>{c.tipo_consulta?.nombre || '—'}</strong> · {c.duracion_minutos} min
              <div className="admin-cell-muted">
                {c.inicio_utc ? new Date(c.inicio_utc).toLocaleString() : '—'} · {c.codigo_referencia}
              </div>
            </div>
            <div className="admin-timeline-meta">
              <span className={`badge badge-${c.estado}`}>{c.estado}</span>
              {c.precio_final_centavos != null && (
                <div className="admin-cell-muted">
                  {formatMoney(c.precio_final_centavos, c.moneda || 'CLP')} {c.moneda}
                </div>
              )}
            </div>
          </div>
          {c.tema_principal && <p className="admin-page-subtitle">Tema: {c.tema_principal}</p>}
        </article>
      ))}
      {data.resumenes.length > 0 && (
        <section className="admin-section-block">
          <h2 className="admin-section-title">Resúmenes IA recientes</h2>
          <div className="admin-card-list">
            {data.resumenes.map((r) => (
              <article key={r.id} className="admin-timeline-card">
                <header className="admin-cell-muted">
                  {new Date(r.inicio_utc).toLocaleDateString()} · {r.tema_principal || 'sin tema'}
                </header>
                <div style={{ margin: 0 }} dangerouslySetInnerHTML={{ __html: renderMarkdown(r.contenido) }} />
              </article>
            ))}
          </div>
        </section>
      )}
    </div>
  );
}

function TabNatal({ data }: { data: ClienteDetalle }) {
  if (!data.dato_natal) return <div className="admin-empty-card"><p className="text-muted">El cliente no ha registrado sus datos natales.</p></div>;
  const n = data.dato_natal;
  return (
    <dl className="admin-detail-list">
      {Object.entries(n).filter(([k]) => !['id', 'user_id', 'created_at', 'updated_at'].includes(k)).map(([k, v]) => (
        <Pair key={k} label={k.replaceAll('_', ' ')} value={String(v ?? '—')} />
      ))}
    </dl>
  );
}

function TabPagos({ data }: { data: ClienteDetalle }) {
  const columns: Column<ClienteDetalle['citas'][number]>[] = [
    { key: 'codigo_referencia', label: 'Cita', render: (c) => c.codigo_referencia },
    { key: 'inicio_utc', label: 'Fecha', render: (c) => c.inicio_utc ? new Date(c.inicio_utc).toLocaleDateString() : '—' },
    { key: 'estado', label: 'Estado' },
    {
      key: 'monto',
      label: 'Monto',
      render: (c) => c.precio_final_centavos != null ? `${formatMoney(c.precio_final_centavos, c.moneda || 'CLP')} ${c.moneda}` : '—',
    },
  ];

  return (
    <section className="admin-section-block">
      <p className="admin-page-subtitle">
        Resumen de ingresos: {Object.entries(data.stats.ingresos_centavos || {}).map(([m, c]) => `${formatMoney(c, m)} ${m}`).join(' · ') || '—'}
      </p>
      <AdminTable
        columns={columns}
        rows={data.citas}
        rowKey={(c) => c.uuid}
        searchable
        searchPlaceholder="Buscar pago"
        getSearchText={(c) => `${c.codigo_referencia ?? ''} ${c.estado} ${c.moneda ?? ''}`}
        pageSize={5}
        pageSizeOptions={[5, 10, 25]}
        emptyLabel="Sin pagos registrados."
      />
    </section>
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
    <section className="admin-filter-card">
      <p className="admin-page-subtitle">Notas privadas, solo visibles para administradores.</p>
      <textarea
        className="input-field"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        rows={12}
        placeholder="Observaciones, preferencias, contexto del cliente…"
      />
      <div className="admin-filter-actions">
        <button type="button" className="btn-primary" onClick={save} disabled={saving}>
          {saving ? 'Guardando…' : 'Guardar notas'}
        </button>
      </div>
    </section>
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
    <section className="admin-filter-card">
      <p className="admin-page-subtitle">Genera un briefing IA para preparar la próxima sesión: temas recurrentes, última sesión, recomendaciones y datos natales.</p>
      {proximas.length > 0 && (
        <select
          className="input-field"
          value={citaUuid || ''}
          onChange={(e) => setCitaUuid(e.target.value || undefined)}
        >
          <option value="">Sin cita específica (briefing general)</option>
          {proximas.map((c) => (
            <option key={c.uuid} value={c.uuid}>
              {new Date(c.inicio_utc!).toLocaleString()} - {c.tipo_consulta?.nombre}
            </option>
          ))}
        </select>
      )}
      <div className="admin-filter-actions">
        <button type="button" className="btn-primary" onClick={generar} disabled={loading}>
          {loading ? 'Generando…' : briefing ? 'Regenerar briefing' : 'Generar briefing'}
        </button>
      </div>
      {error && <p className="form-error" style={{ marginTop: '0.75rem' }}>{error}</p>}
      {briefing && (
        <article className="admin-timeline-card">
          <header className="admin-cell-muted">
            Modelo {briefing.modelo} · {briefing.sesiones_consideradas} sesiones consideradas · generado {new Date(briefing.generado_en).toLocaleString()}
          </header>
          <div dangerouslySetInnerHTML={{ __html: renderMarkdown(briefing.contenido) }} />
        </article>
      )}
    </section>
  );
}

function Card({ label, value, highlight }: { label: string; value: React.ReactNode; highlight?: 'warn' }) {
  return (
    <div className={`admin-metric-card${highlight === 'warn' ? ' admin-metric-card--warn' : ''}`}>
      <span className="admin-metric-label">{label}</span>
      <strong className="admin-metric-value">{value}</strong>
    </div>
  );
}

function Pair({ label, value }: { label: string; value: string }) {
  return (
    <>
      <dt>{label}</dt>
      <dd>{value}</dd>
    </>
  );
}

function formatMoney(centavos: number, moneda: string): string {
  if (moneda === 'CLP') return Math.round(centavos / 100).toLocaleString('es-CL');
  return (centavos / 100).toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
