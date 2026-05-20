import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useState } from 'react';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

type MiCita = {
  uuid: string;
  estado: string;
  inicio_utc: string;
  duracion_minutos: number;
  servicio: string | null;
  cliente_nombre: string | null;
};

const ESTADO_LABELS: Record<string, string> = {
  reservada: 'Reservada',
  confirmada: 'Confirmada',
  finalizada: 'Finalizada',
};

function fmtDate(iso: string) {
  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'America/Santiago',
  }).format(new Date(iso));
}

export function EspecialistaMisCitasPage() {
  const queryClient = useQueryClient();
  const [reprogramarTarget, setReprogramarTarget] = useState<MiCita | null>(null);
  const [saldoTarget, setSaldoTarget] = useState<MiCita | null>(null);
  const [saldoReferencia, setSaldoReferencia] = useState('');
  const [saldoCanal, setSaldoCanal] = useState('whatsapp');
  const [saldoNota, setSaldoNota] = useState('');
  const [nuevoInicio, setNuevoInicio] = useState('');
  const [motivoReprogramacion, setMotivoReprogramacion] = useState('');

  const { data, isLoading, isError } = useQuery<{ data: MiCita[] }>({
    queryKey: ['especialista', 'mis-citas'],
    queryFn: async () => (await api.get('/especialista/mis-citas')).data,
    staleTime: 60_000,
  });

  const citas = data?.data ?? [];
  const proximas = citas.filter((c) => c.estado !== 'finalizada');
  const pasadas = citas.filter((c) => c.estado === 'finalizada');
  const reprogramar = useMutation({
    mutationFn: (payload: { uuid: string; inicio_local: string; motivo?: string }) =>
      api.post(`/admin/citas/${payload.uuid}/reprogramar`, {
        inicio_local: payload.inicio_local,
        zona_horaria: 'America/Santiago',
        motivo: payload.motivo || undefined,
      }),
    onSuccess: () => {
      setReprogramarTarget(null);
      setNuevoInicio('');
      setMotivoReprogramacion('');
      queryClient.invalidateQueries({ queryKey: ['especialista', 'mis-citas'] });
    },
  });
  const marcarSaldo = useMutation({
    mutationFn: (payload: { uuid: string; referencia?: string; canal_origen: string; nota?: string }) =>
      api.post(`/admin/citas/${payload.uuid}/marcar-saldo-pagado`, {
        referencia: payload.referencia || undefined,
        canal_origen: payload.canal_origen,
        nota: payload.nota || undefined,
      }),
    onSuccess: () => {
      setSaldoTarget(null);
      setSaldoReferencia('');
      setSaldoCanal('whatsapp');
      setSaldoNota('');
      queryClient.invalidateQueries({ queryKey: ['especialista', 'mis-citas'] });
    },
  });
  const proximasColumns: Column<MiCita>[] = [
    { key: 'inicio_utc', label: 'Fecha', render: (c) => fmtDate(c.inicio_utc) },
    { key: 'servicio', label: 'Servicio', render: (c) => c.servicio ?? '—' },
    { key: 'cliente_nombre', label: 'Cliente', render: (c) => c.cliente_nombre ?? '—' },
    { key: 'duracion_minutos', label: 'Duración', render: (c) => `${c.duracion_minutos} min` },
    {
      key: 'estado',
      label: 'Estado',
      render: (c) => (
        <span className={`service-pill estado-${c.estado}`}>
          {ESTADO_LABELS[c.estado] ?? c.estado}
        </span>
      ),
    },
    {
      key: 'acciones',
      label: 'Acciones',
      render: (c) => (
        <div className="admin-row-actions">
          {c.estado === 'reservada' ? (
            <button
              className="btn-primary"
              type="button"
              style={{ fontSize: '0.8rem', padding: '0.25rem 0.6rem' }}
              onClick={() => setSaldoTarget(c)}
            >
              Marcar saldo pagado
            </button>
          ) : null}
          <button
            className="btn-secondary"
            type="button"
            style={{ fontSize: '0.8rem', padding: '0.25rem 0.6rem' }}
            onClick={() => {
              setReprogramarTarget(c);
              setNuevoInicio(toDatetimeLocal(c.inicio_utc));
            }}
          >
            Reprogramar
          </button>
        </div>
      ),
    },
  ];
  const historialColumns: Column<MiCita>[] = proximasColumns.filter((column) => column.key !== 'estado' && column.key !== 'acciones');

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
        <p className="dash-eyebrow">✦ Mi agenda</p>
        <h1 className="dash-title">Mis citas</h1>
      </motion.div>

      {isLoading && <p className="text-muted">Cargando citas…</p>}
      {isError && <p className="form-error">No se pudieron cargar las citas.</p>}

      {!isLoading && !isError && (
        <>
          <section style={{ marginTop: '1.5rem' }}>
            <h2 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Próximas</h2>
            {proximas.length === 0 ? (
              <p className="text-muted">No tienes citas próximas.</p>
            ) : (
              <AdminTable
                columns={proximasColumns}
                rows={proximas}
                rowKey={(c) => c.uuid}
                searchable
                searchPlaceholder="Buscar cita"
                getSearchText={(c) => `${c.servicio ?? ''} ${c.cliente_nombre ?? ''} ${c.estado}`}
                pageSize={5}
                pageSizeOptions={[5, 10, 25]}
              />
            )}
          </section>

          {pasadas.length > 0 && (
            <section style={{ marginTop: '2rem' }}>
              <h2 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Historial</h2>
              <AdminTable
                columns={historialColumns}
                rows={pasadas}
                rowKey={(c) => c.uuid}
                searchable
                searchPlaceholder="Buscar en historial"
                getSearchText={(c) => `${c.servicio ?? ''} ${c.cliente_nombre ?? ''}`}
                pageSize={5}
                pageSizeOptions={[5, 10, 25]}
              />
            </section>
          )}
        </>
      )}

      <div style={{ marginTop: '1.5rem' }}>
        <Link className="btn-secondary" to="/app/admin">← Volver al dashboard</Link>
      </div>
      {reprogramarTarget ? (
        <div className="admin-modal-backdrop" role="presentation">
          <section className="admin-modal" role="dialog" aria-modal="true" aria-labelledby="reprogramar-especialista-title">
            <header className="admin-modal-header">
              <h2 id="reprogramar-especialista-title">Reprogramar cita</h2>
              <button className="admin-modal-close" type="button" onClick={() => setReprogramarTarget(null)}>×</button>
            </header>
            <div className="admin-modal-body">
              <p className="text-muted">El cliente recibirá un correo para aceptar la nueva fecha o elegir otra.</p>
              <label>
                Nueva fecha y hora (Chile)
                <input className="input-field" type="datetime-local" value={nuevoInicio} onChange={(e) => setNuevoInicio(e.target.value)} />
              </label>
              <label>
                Motivo visible para el cliente
                <textarea className="input-field" rows={3} value={motivoReprogramacion} onChange={(e) => setMotivoReprogramacion(e.target.value)} />
              </label>
              {reprogramar.isError ? <p className="form-error">No se pudo reprogramar la cita.</p> : null}
            </div>
            <div className="admin-modal-footer">
              <button className="btn-secondary" type="button" onClick={() => setReprogramarTarget(null)}>Cancelar</button>
              <button
                className="btn-primary"
                type="button"
                disabled={!nuevoInicio || reprogramar.isPending}
                onClick={() => reprogramar.mutate({
                  uuid: reprogramarTarget.uuid,
                  inicio_local: toBackendLocalDateTime(nuevoInicio),
                  motivo: motivoReprogramacion,
                })}
              >
                {reprogramar.isPending ? 'Enviando...' : 'Reprogramar y notificar'}
              </button>
            </div>
          </section>
        </div>
      ) : null}

      {saldoTarget ? (
        <div className="admin-modal-backdrop" role="presentation">
          <section className="admin-modal" role="dialog" aria-modal="true" aria-labelledby="saldo-especialista-title">
            <header className="admin-modal-header">
              <h2 id="saldo-especialista-title">Marcar saldo pagado</h2>
              <button className="admin-modal-close" type="button" onClick={() => setSaldoTarget(null)}>×</button>
            </header>
            <div className="admin-modal-body">
              <p className="text-muted">Úsalo si el cliente pagó la diferencia por WhatsApp, correo u otro canal externo.</p>
              <label>
                Canal de pago
                <select className="input-field" value={saldoCanal} onChange={(e) => setSaldoCanal(e.target.value)}>
                  <option value="whatsapp">WhatsApp</option>
                  <option value="email">Correo</option>
                  <option value="telefono">Teléfono</option>
                  <option value="otro">Otro</option>
                </select>
              </label>
              <label>
                Referencia del pago
                <input className="input-field" value={saldoReferencia} onChange={(e) => setSaldoReferencia(e.target.value)} />
              </label>
              <label>
                Nota interna
                <textarea className="input-field" rows={3} value={saldoNota} onChange={(e) => setSaldoNota(e.target.value)} />
              </label>
              {marcarSaldo.isError ? <p className="form-error">No se pudo marcar el saldo como pagado.</p> : null}
            </div>
            <div className="admin-modal-footer">
              <button className="btn-secondary" type="button" onClick={() => setSaldoTarget(null)}>Cancelar</button>
              <button
                className="btn-primary"
                type="button"
                disabled={marcarSaldo.isPending}
                onClick={() => marcarSaldo.mutate({
                  uuid: saldoTarget.uuid,
                  referencia: saldoReferencia,
                  canal_origen: saldoCanal,
                  nota: saldoNota,
                })}
              >
                {marcarSaldo.isPending ? 'Guardando...' : 'Confirmar saldo pagado'}
              </button>
            </div>
          </section>
        </div>
      ) : null}
    </main>
  );
}

function toDatetimeLocal(iso: string): string {
  const date = new Date(iso);
  const parts = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'America/Santiago',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '00';
  return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

function toBackendLocalDateTime(value: string): string {
  return value ? `${value.replace('T', ' ')}:00` : '';
}
