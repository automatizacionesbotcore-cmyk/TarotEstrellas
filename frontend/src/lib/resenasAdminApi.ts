import { api } from './api';
import type { Paginated } from './cuponesAdminApi';

export type ResenaAdmin = {
  id: number;
  uuid: string;
  cita_id: number;
  cliente_id: number;
  especialista_id: number;
  puntuacion: number;
  comentario: string | null;
  visible: boolean;
  respuesta_admin: string | null;
  respondida_en: string | null;
  respondida_por: number | null;
  created_at: string;
  cliente?: { id: number; profile?: { nombre?: string; apellido?: string } | null };
  especialista?: { id: number; name: string };
  cita?: { id: number; uuid: string; codigo_referencia: string; inicio_utc?: string };
};

export type ListResenasParams = {
  q?: string;
  especialista_id?: number;
  puntuacion_min?: number;
  puntuacion_max?: number;
  visible?: boolean;
  sin_responder?: boolean;
  page?: number;
  per_page?: number;
};

export const listResenas = (params: ListResenasParams = {}) =>
  api.get<Paginated<ResenaAdmin>>('/admin/resenas', { params });

export const getResena = (uuid: string) => api.get<{ data: ResenaAdmin }>(`/admin/resenas/${uuid}`);

export const responderResena = (uuid: string, respuesta: string) =>
  api.post<{ data: ResenaAdmin }>(`/admin/resenas/${uuid}/responder`, { respuesta });

export const eliminarRespuestaResena = (uuid: string) =>
  api.delete<{ data: ResenaAdmin }>(`/admin/resenas/${uuid}/responder`);

export const toggleVisibilidadResena = (uuid: string) =>
  api.post<{ data: ResenaAdmin }>(`/admin/resenas/${uuid}/visibilidad`);
