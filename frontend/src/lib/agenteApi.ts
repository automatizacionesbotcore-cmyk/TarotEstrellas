import { api } from './api';

export interface AgenteConversacion {
  uuid: string;
  cliente_id: number;
  autor_rol: 'admin' | 'cliente';
  autor: { uuid: string; name: string } | null;
  pregunta: string;
  respuesta: string | null;
  modelo: string | null;
  tokens_in: number | null;
  tokens_out: number | null;
  latencia_ms: number | null;
  contexto_sesiones: number | null;
  created_at: string | null;
}

export interface AgenteListResponse {
  data: AgenteConversacion[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export async function consultarAgenteSelf(pregunta: string): Promise<AgenteConversacion> {
  const { data } = await api.post<AgenteConversacion>('/me/agente/consultar', { pregunta });
  return data;
}

export async function consultarAgenteAdmin(clienteUuid: string, pregunta: string): Promise<AgenteConversacion> {
  const { data } = await api.post<AgenteConversacion>('/agente/consultar', {
    cliente_uuid: clienteUuid,
    pregunta,
  });
  return data;
}

export async function listConversacionesSelf(perPage = 20): Promise<AgenteListResponse> {
  const { data } = await api.get<AgenteListResponse>('/me/agente/conversaciones', {
    params: { per_page: perPage },
  });
  return data;
}

export async function listConversacionesAdmin(clienteUuid: string, perPage = 20): Promise<AgenteListResponse> {
  const { data } = await api.get<AgenteListResponse>('/agente/conversaciones', {
    params: { cliente_uuid: clienteUuid, per_page: perPage },
  });
  return data;
}
