import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api } from '../../../lib/api';
import { AdminFilters, type FilterValues } from '../../../components/admin/AdminFilters';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

type Reembolso = {
  uuid:            string;
  estado:          string;
  razon:           string;
  metodo:          string;
  monto_centavos:  number;
  moneda:          string;
  solicitado_en:   string | null;
  procesado_en:    string | null;
  cliente?:        { email: string; nombre?: string | null } | null;
};

type PaginatedReembolsos = {
  data: Reembolso[];
  meta?: { total: number; current_page: number; last_page: number };
};

const ESTADOS = [
  { value: 'pendiente',  label: 'Pendiente'  },
  { value: 'completado', label: 'Completado' },
  { value: 'fallido',    label: 'Fallido'    },
];

const METODOS = [
  { value: 'mismo_medio_pago',       label: 'Mismo medio de pago' },
  { value: 'transferencia_manual',   label: 'Transferencia manual' },
  { value: 'credito_cliente',        label: 'Crédito cliente' },
];

function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency, maximumFractionDigits: currency === 'CLP' ? 0 : 2 }).format(cents / 100);
}

export function AdminReembolsosPage() {
  const navigate     = useNavigate();
  const queryClient  = useQueryClient();
  const [filters, setFilters] = useState<FilterValues>({});
  const [showModal, setShowModal] = useState(false);

  const listQuery = useQuery({
    queryKey: ['admin', 'reembolsos', 'list', filters],
    queryFn:  async () => (await api.get<PaginatedReembolsos>('/admin/reembolsos', { params: filters })).data,
  });

  const exportMutation = useMutation({
    mutationFn: async () => {
      const res = await api.get('/admin/reembolsos/export', { params: filters, responseType: 'blob' });
      const url = URL.createObjectURL(res.data as Blob);
      const a   = document.createElement('a');
      a.href    = url;
      a.download = `reembolsos-${new Date().toISOString().slice(0,10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    },
  });

  const columns: Column<Reembolso>[] = [
    { key: 'uuid',   label: 'UUID',   render: (r) => r.uuid.slice(0, 8) + '…' },
    { key: 'estado', label: 'Estado' },
    { key: 'razon',  label: 'Razón'  },
    { key: 'metodo', label: 'Método' },
    { key: 'monto',  label: 'Monto',  render: (r) => formatMoney(r.monto_centavos, r.moneda) },
    { key: 'email',  label: 'Cliente',render: (r) => r.cliente?.email ?? '—' },
    { key: 'fecha',  label: 'Solicitado', render: (r) => r.solicitado_en ? new Date(r.solicitado_en).toLocaleDateString('es-CL') : '—' },
  ];

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <h1>Reembolsos</h1>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button className="btn-secondary" onClick={() => exportMutation.mutate()} disabled={exportMutation.isPending}>
            {exportMutation.isPending ? 'Exportando…' : 'Exportar CSV'}
          </button>
          <button className="btn-primary" onClick={() => setShowModal(true)}>Crear reembolso</button>
        </div>
      </header>

      <AdminFilters
        values={filters}
        onChange={setFilters}
        estados={ESTADOS}
        metodos={METODOS}
      />

      <AdminTable
        columns={columns}
        rows={listQuery.data?.data ?? []}
        loading={listQuery.isLoading}
        rowKey={(r) => r.uuid}
        onRowClick={(r) => navigate(`/app/admin/reembolsos/${r.uuid}`)}
      />

      {showModal && (
        <CreateReembolsoModal
          onClose={() => setShowModal(false)}
          onCreated={() => {
            setShowModal(false);
            queryClient.invalidateQueries({ queryKey: ['admin', 'reembolsos'] });
          }}
        />
      )}
    </main>
  );
}

function CreateReembolsoModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const [form, setForm] = useState({
    cita_uuid:      '',
    pago_uuid:      '',
    monto_centavos: '',
    moneda:         'CLP',
    razon:          'cancelacion_24h',
    metodo:         'mismo_medio_pago',
    nota_admin:     '',
  });
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        ...form,
        pago_uuid:      form.pago_uuid || undefined,
        monto_centavos: Number(form.monto_centavos),
      };
      return api.post('/admin/reembolsos', payload);
    },
    onSuccess: onCreated,
    onError:   (e: any) => setError(e?.response?.data?.message ?? 'No se pudo crear.'),
  });

  return (
    <div className="confirm-dialog-backdrop" role="dialog" aria-modal="true">
      <div className="confirm-dialog" style={{ maxWidth: 600 }}>
        <h3>Crear reembolso manual</h3>
        <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }} style={{ display: 'grid', gap: '1rem' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Cita UUID</span>
              <input className="input-field" required value={form.cita_uuid} onChange={(e) => setForm({ ...form, cita_uuid: e.target.value })} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Pago UUID (opcional)</span>
              <input className="input-field" value={form.pago_uuid} onChange={(e) => setForm({ ...form, pago_uuid: e.target.value })} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Monto (centavos)</span>
              <input className="input-field" type="number" required min={1} value={form.monto_centavos} onChange={(e) => setForm({ ...form, monto_centavos: e.target.value })} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Moneda</span>
              <input className="input-field" required maxLength={3} value={form.moneda} onChange={(e) => setForm({ ...form, moneda: e.target.value.toUpperCase() })} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Razón</span>
              <input className="input-field" required value={form.razon} onChange={(e) => setForm({ ...form, razon: e.target.value })} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Método</span>
              <select className="input-field" value={form.metodo} onChange={(e) => setForm({ ...form, metodo: e.target.value })}>
                <option value="mismo_medio_pago">Mismo medio de pago</option>
                <option value="transferencia_manual">Transferencia manual</option>
                <option value="credito_cliente">Crédito cliente</option>
              </select>
            </label>
          </div>
          <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>Nota admin</span>
            <textarea className="input-field" value={form.nota_admin} onChange={(e) => setForm({ ...form, nota_admin: e.target.value })} rows={3} />
          </label>
          {error && <p className="form-error">{error}</p>}
          <div className="confirm-dialog-actions">
            <button type="button" className="btn-secondary" onClick={onClose}>Cancelar</button>
            <button type="submit" className="btn-primary" disabled={mutation.isPending}>
              {mutation.isPending ? 'Creando…' : 'Crear'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
