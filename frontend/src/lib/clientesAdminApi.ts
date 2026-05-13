import { api } from './api';

export interface ClienteListaItem {
  uuid: string;
  name: string;
  email: string;
  created_at: string | null;
  last_login_at: string | null;
  profile: {
    nombre: string | null;
    apellido: string | null;
    pais_residencia: string | null;
    avatar_url: string | null;
    fecha_nacimiento: string | null;
  } | null;
  tiene_datos_natales: boolean;
  stats: ClienteStats;
}

export interface ClienteStats {
  total_completadas: number;
  total_canceladas: number;
  total_no_show: number;
  total_confirmadas: number;
  ingresos_centavos: Record<string, number>;
  tipo_favorito: string | null;
  primera_consulta: string | null;
  ultima_consulta: string | null;
}

export interface ClienteDetalle {
  uuid: string;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string | null;
  last_login_at: string | null;
  profile: any;
  dato_natal: any;
  preferencia_notificacion: any;
  consentimientos: any[];
  roles: string[];
  notas_admin: string | null;
  notas_admin_actualizadas_en: string | null;
  stats: ClienteStats;
  citas: Array<{
    uuid: string;
    codigo_referencia: string;
    inicio_utc: string | null;
    duracion_minutos: number;
    estado: string;
    tipo_consulta: { nombre?: string; slug?: string } | null;
    precio_final_centavos: number | null;
    moneda: string | null;
    tema_principal: string | null;
  }>;
  resumenes: Array<{
    id: number;
    cita_id: number;
    contenido: string;
    created_at: string;
    cita_uuid: string;
    inicio_utc: string;
    tema_principal: string | null;
  }>;
}

export interface ClienteListaParams {
  q?: string;
  pais?: string;
  signo?: string;
  frecuencia?: 'sin_consultas' | 'baja' | 'media' | 'alta';
  activos?: 0 | 1;
  desde?: string;
  hasta?: string;
  per_page?: number;
  page?: number;
}

export interface ListResponse<T> {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface BriefingResponse {
  contenido: string;
  generado_en: string;
  modelo: string;
  tokens_in: number | null;
  tokens_out: number | null;
  sesiones_consideradas: number;
}

export async function listClientes(params: ClienteListaParams = {}): Promise<ListResponse<ClienteListaItem>> {
  const { data } = await api.get<ListResponse<ClienteListaItem>>('/admin/clientes', { params });
  return data;
}

export async function getClienteDetalle(uuid: string): Promise<{ data: ClienteDetalle }> {
  const { data } = await api.get<{ data: ClienteDetalle }>(`/admin/clientes/${uuid}`);
  return data;
}

export async function getClienteEstadisticas(uuid: string): Promise<{ data: ClienteStats }> {
  const { data } = await api.get<{ data: ClienteStats }>(`/admin/clientes/${uuid}/estadisticas`);
  return data;
}

export async function actualizarNotasCliente(uuid: string, notas: string | null): Promise<{ data: { notas_admin: string | null; notas_admin_actualizadas_en: string | null } }> {
  const { data } = await api.patch(`/admin/clientes/${uuid}/notas`, { notas_admin: notas });
  return data;
}

export async function eliminarCliente(uuid: string, force = false): Promise<{ message: string }> {
  const { data } = await api.delete<{ message: string }>(`/admin/clientes/${uuid}`, {
    params: force ? { force: 1 } : {},
  });
  return data;
}

export async function getClienteBriefing(uuid: string, citaUuid?: string): Promise<{ data: BriefingResponse }> {
  const { data } = await api.get<{ data: BriefingResponse }>(`/admin/clientes/${uuid}/briefing`, {
    params: citaUuid ? { cita_uuid: citaUuid } : {},
  });
  return data;
}
