import { api } from './api';

export type Plantilla = {
  id: number;
  clave: string;
  canal: 'email' | 'whatsapp' | 'sms';
  asunto: string | null;
  cuerpo: string;
  variables: string[] | null;
  activo: boolean;
  version: number;
  created_at: string;
  updated_at: string;
};

export type PlantillaPayload = {
  clave: string;
  canal: 'email' | 'whatsapp' | 'sms';
  asunto?: string | null;
  cuerpo: string;
  variables?: string[] | null;
  activo?: boolean;
};

export type PlantillaVersion = {
  id: number;
  plantilla_id: number;
  version: number;
  asunto: string | null;
  cuerpo: string;
  created_at: string;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export const VARIABLES_BASE = [
  'cita_uuid', 'cita_codigo', 'cita_inicio',
  'cliente_nombre', 'cliente_email', 'cliente_telefono',
  'tipo_consulta', 'sala_url', 'reembolso_monto', 'enlace_descarga',
];

export const listPlantillas = (params: { canal?: string; activo?: boolean; page?: number } = {}) =>
  api.get<Paginated<Plantilla>>('/admin/plantillas', { params });

export const getPlantilla = (id: number) => api.get<{ data: Plantilla }>(`/admin/plantillas/${id}`);

export const crearPlantilla = (payload: PlantillaPayload) =>
  api.post<{ data: Plantilla }>('/admin/plantillas', payload);

export const actualizarPlantilla = (id: number, payload: PlantillaPayload) =>
  api.patch<{ data: Plantilla }>(`/admin/plantillas/${id}`, payload);

export const eliminarPlantilla = (id: number) => api.delete(`/admin/plantillas/${id}`);

export const previewPlantilla = (id: number, variables: Record<string, string>) =>
  api.post<{ asunto: string | null; cuerpo: string }>(`/admin/plantillas/${id}/preview`, { variables });
