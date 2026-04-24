import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Comprobante = {
  id:          number;
  uuid:        string;
  estado:      string;
  monto_centavos?: number;
  moneda?:     string;
  creado_en?:  string;
  cliente_email?: string | null;
};

type PaginatedComprobantes = {
  data: Comprobante[];
  meta?: { total: number };
};

export function AdminComprobantesPage() {
  const queryClient = useQueryClient();
  const [pending, setPending] = useState<{ id: number; action: 'aprobar' | 'rechazar' } | null>(null);

  const listQuery = useQuery({
    queryKey: ['admin', 'comprobantes', 'list'],
    queryFn:  async () => (await api.get<PaginatedComprobantes>('/pagos/comprobantes')).data,
  });

  const mutate = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'aprobar' | 'rechazar' }) => {
      return api.post(`/admin/comprobantes/${id}/${action}`);
    },
    onSuccess: () => {
      setPending(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'comprobantes'] });
    },
  });

  const columns: Column<Comprobante>[] = [
    { key: 'uuid',   label: 'UUID',   render: (r) => r.uuid?.slice(0, 8) + '…' },
    { key: 'estado', label: 'Estado' },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente_email ?? '—' },
    { key: 'creado', label: 'Creado', render: (r) => r.creado_en ? new Date(r.creado_en).toLocaleDateString('es-CL') : '—' },
    { key: 'acciones', label: 'Acciones', render: (r) => (
      <>
        <button className="btn-primary"   style={{ marginRight: '.25rem' }} onClick={(e) => { e.stopPropagation(); setPending({ id: r.id, action: 'aprobar'  }); }}>Aprobar</button>
        <button className="btn-secondary"                                      onClick={(e) => { e.stopPropagation(); setPending({ id: r.id, action: 'rechazar' }); }}>Rechazar</button>
      </>
    ) },
  ];

  return (
    <main className="page-content">
      <h1>Comprobantes de transferencia</h1>

      <AdminTable
        columns={columns}
        rows={listQuery.data?.data ?? []}
        loading={listQuery.isLoading}
        rowKey={(r) => r.id}
      />

      <ConfirmDialog
        open={pending !== null}
        title={pending?.action === 'aprobar' ? 'Aprobar comprobante' : 'Rechazar comprobante'}
        message={`¿Confirmas ${pending?.action} el comprobante?`}
        loading={mutate.isPending}
        onConfirm={() => pending && mutate.mutate(pending)}
        onCancel={()  => setPending(null)}
      />
    </main>
  );
}
