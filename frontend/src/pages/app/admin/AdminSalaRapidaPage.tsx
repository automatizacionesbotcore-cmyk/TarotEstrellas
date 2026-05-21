import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { api } from '../../../lib/api';
import { toast } from '../../../stores/toastStore';

type ClienteRow = {
  uuid: string;
  name: string;
  email: string;
  profile?: {
    nombre?: string | null;
    apellido?: string | null;
  } | null;
};

type TipoConsulta = {
  id: number;
  nombre: string;
  duracion_minutos: number;
  activo?: boolean;
};

type SalaRapidaResponse = {
  message: string;
  data: {
    cita_uuid: string;
    codigo_referencia: string;
    sala_app_url: string;
    cliente_email: string;
  };
};

function clienteLabel(cliente: ClienteRow) {
  const nombre = `${cliente.profile?.nombre ?? ''} ${cliente.profile?.apellido ?? ''}`.trim();
  return `${nombre || cliente.name || cliente.email} · ${cliente.email}`;
}

export function AdminSalaRapidaPage() {
  const [clienteUuid, setClienteUuid] = useState('');
  const [clienteSearch, setClienteSearch] = useState('');
  const [tipoConsultaId, setTipoConsultaId] = useState('');
  const [tema, setTema] = useState('Consulta rápida');
  const [mensaje, setMensaje] = useState('');
  const [grabacion, setGrabacion] = useState(true);
  const [resultado, setResultado] = useState<SalaRapidaResponse['data'] | null>(null);

  const clientesQuery = useQuery<{ data: ClienteRow[] }>({
    queryKey: ['admin-clientes-sala-rapida', clienteSearch],
    queryFn: async () => (await api.get('/admin/clientes', {
      params: {
        q: clienteSearch || undefined,
        per_page: 20,
      },
    })).data,
    staleTime: 30_000,
  });

  const tiposQuery = useQuery<{ data: TipoConsulta[] }>({
    queryKey: ['tipos-consulta-sala-rapida'],
    queryFn: async () => (await api.get('/tipos-consulta')).data,
    staleTime: 120_000,
  });

  const clientes = clientesQuery.data?.data ?? [];
  const tipos = tiposQuery.data?.data ?? [];
  const tipoSeleccionado = useMemo(
    () => tipos.find((tipo) => String(tipo.id) === tipoConsultaId),
    [tipos, tipoConsultaId],
  );

  const abrirSala = useMutation({
    mutationFn: async () => {
      const response = await api.post<SalaRapidaResponse>('/admin/sesiones-rapidas', {
        cliente_uuid: clienteUuid,
        tipo_consulta_id: Number(tipoConsultaId),
        duracion_minutos: tipoSeleccionado?.duracion_minutos,
        tema_principal: tema,
        mensaje: mensaje || undefined,
        grabacion_solicitada: grabacion,
      });
      return response.data;
    },
    onSuccess: (data) => {
      setResultado(data.data);
      toast.success('Sala rápida abierta y correo enviado al cliente.');
    },
    onError: () => {
      toast.error('No se pudo abrir la sala rápida. Revisa cliente, servicio y configuración Daily.');
    },
  });

  return (
    <main className="page-content">
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
        <p className="dash-eyebrow">✦ Consulta inmediata</p>
        <h1 className="dash-title">Sala rápida</h1>
        <p className="dash-subtitle">Abre una sala privada sin pasar por el flujo de agendamiento.</p>
      </motion.div>

      <section className="admin-card" style={{ marginTop: '1.5rem' }}>
        <div className="auth-grid">
          <label className="auth-span-2">
            Buscar cliente
            <input
              className="input-field"
              type="search"
              placeholder="Nombre, correo o teléfono"
              value={clienteSearch}
              onChange={(event) => setClienteSearch(event.target.value)}
            />
          </label>

          <label className="auth-span-2">
            Cliente
            <select className="input-field" value={clienteUuid} onChange={(event) => setClienteUuid(event.target.value)}>
              <option value="">Selecciona un cliente</option>
              {clientes.map((cliente) => (
                <option key={cliente.uuid} value={cliente.uuid}>
                  {clienteLabel(cliente)}
                </option>
              ))}
            </select>
          </label>

          <label>
            Servicio
            <select className="input-field" value={tipoConsultaId} onChange={(event) => setTipoConsultaId(event.target.value)}>
              <option value="">Selecciona un servicio</option>
              {tipos.map((tipo) => (
                <option key={tipo.id} value={tipo.id}>
                  {tipo.nombre} · {tipo.duracion_minutos} min
                </option>
              ))}
            </select>
          </label>

          <label>
            Tema
            <input className="input-field" value={tema} onChange={(event) => setTema(event.target.value)} />
          </label>

          <label className="auth-span-2">
            Mensaje para el cliente
            <textarea
              className="input-field"
              rows={3}
              placeholder="Ejemplo: Ya abrí tu sala para la consulta. Puedes entrar cuando estés listo/a."
              value={mensaje}
              onChange={(event) => setMensaje(event.target.value)}
            />
          </label>

          <label className="auth-checkbox auth-span-2">
            <input type="checkbox" checked={grabacion} onChange={(event) => setGrabacion(event.target.checked)} />
            Habilitar grabación de la sesión
          </label>

          {abrirSala.isError ? (
            <p className="form-error auth-span-2">No se pudo abrir la sala rápida. Intenta nuevamente.</p>
          ) : null}

          <div className="wizard-actions auth-span-2">
            <button
              className="btn-primary"
              type="button"
              disabled={!clienteUuid || !tipoConsultaId || abrirSala.isPending}
              onClick={() => abrirSala.mutate()}
            >
              {abrirSala.isPending ? 'Abriendo sala...' : 'Abrir sala y notificar'}
            </button>
          </div>
        </div>
      </section>

      {resultado ? (
        <section className="admin-card" style={{ marginTop: '1rem' }}>
          <p className="form-success">Sala abierta. El cliente recibirá el correo en {resultado.cliente_email}.</p>
          <div className="admin-row-actions" style={{ marginTop: '1rem' }}>
            <Link className="btn-primary" to={`/app/sala/${resultado.cita_uuid}`}>Entrar sala</Link>
            <Link className="btn-secondary" to={`/app/citas/${resultado.cita_uuid}`}>Ver consulta</Link>
          </div>
          <p className="text-muted" style={{ marginTop: '0.75rem' }}>
            Referencia: {resultado.codigo_referencia}
          </p>
        </section>
      ) : null}
    </main>
  );
}
