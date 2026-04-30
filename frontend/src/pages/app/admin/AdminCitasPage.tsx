import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminFilters, type FilterValues } from '../../../components/admin/AdminFilters';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Cita = {
  uuid:           string;
  estado:         string;
  inicio_utc:     string;
  cliente_email?: string | null;
  tipo_consulta?: { nombre: string } | null;
};

type Historial = { data: Cita[]; meta?: { total: number } };

const ESTADOS = [
  { value: 'reservada',              label: 'Reservada'              },
  { value: 'confirmada',             label: 'Confirmada'             },
  { value: 'en_curso',               label: 'En curso'               },
  { value: 'finalizada',             label: 'Finalizada'             },
  { value: 'completada',             label: 'Completada (legacy)'    },
  { value: 'cancelada_cliente',      label: 'Cancelada cliente'      },
  { value: 'cancelada_chachita',     label: 'Cancelada especialista' },
  { value: 'no_show',                label: 'No show'                },
  { value: 'expirada',               label: 'Expirada'               },
];

export function AdminCitasPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<FilterValues>({});
  const [targetUuid, setTargetUuid] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ['admin', 'citas', 'historial', filters],
    queryFn:  async () => (await api.get<Historial>('/admin/citas/historial', { params: filters })).data,
  });

  const noShow = useMutation({
    mutationFn: (uuid: string) => api.post(`/admin/citas/${uuid}/no-show`),
    onSuccess: () => {
      setTargetUuid(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'citas'] });
    },
  });

  const exportCsv = async () => {
    const res = await api.get('/admin/citas/historial/export', { params: filters, responseType: 'blob' });
    const url = URL.createObjectURL(res.data as Blob);
    const a   = document.createElement('a');
    a.href    = url;
    a.download = `citas-historial-${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const descargarTranscripcion = async (uuid: string) => {
    try {
      const res = await api.get(`/admin/citas/${uuid}/transcripcion/descargar`, { responseType: 'blob' });
      const url = URL.createObjectURL(res.data);
      const a = document.createElement('a');
      a.href = url;
      a.download = `transcripcion-${uuid.slice(0, 8)}.txt`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      alert('No hay transcripción disponible para esta cita.');
    }
  };

  const columns: Column<Cita>[] = [
    { key: 'uuid',    label: 'UUID',    render: (r) => r.uuid.slice(0, 8) + '…' },
    { key: 'tipo',    label: 'Tipo',    render: (r) => r.tipo_consulta?.nombre ?? '—' },
    { key: 'estado',  label: 'Estado' },
    { key: 'inicio',  label: 'Inicio',  render: (r) => new Date(r.inicio_utc).toLocaleString('es-CL') },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente_email ?? '—' },
    { key: 'accion',  label: '',        render: (r) => (
      <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
        {(r.estado === 'reservada' || r.estado === 'confirmada') && (
          <button className="btn-secondary" onClick={(e) => { e.stopPropagation(); setTargetUuid(r.uuid); }}>Marcar no-show</button>
        )}
        <button
          className="btn-secondary"
          title="Descargar transcripción cruda (admin)"
          onClick={(e) => { e.stopPropagation(); descargarTranscripcion(r.uuid); }}
        >
          📝 Transcripción
        </button>
      </div>
    ) },
  ];

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <h1>Historial de citas</h1>
        <button className="btn-secondary" onClick={exportCsv}>Exportar CSV</button>
      </header>

      <AdminFilters values={filters} onChange={setFilters} estados={ESTADOS} />

      <AdminTable
        columns={columns}
        rows={query.data?.data ?? []}
        loading={query.isLoading}
        rowKey={(r) => r.uuid}
      />

      <ConfirmDialog
        open={!!targetUuid}
        title="Marcar no-show"
        message="Esto cancelará la cita y generará reembolsos pendientes por los pagos completados. ¿Continuar?"
        loading={noShow.isPending}
        onConfirm={() => targetUuid && noShow.mutate(targetUuid)}
        onCancel={()  => setTargetUuid(null)}
      />
    </main>
  );
}
