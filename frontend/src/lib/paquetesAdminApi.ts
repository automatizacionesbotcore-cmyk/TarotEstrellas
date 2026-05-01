import { api } from './api';

export type Paquete = {
  id: number;
  slug: string;
  nombre: string;
  tipo: 'paquete' | 'membresia';
  consultas_incluidas: number;
  vigencia_dias: number | null;
  precio_centavos: number;
  moneda: string;
  descuento_porcentaje?: number | null;
  activo: boolean;
  destacado: boolean;
  orden_visualizacion: number;
  imagen_url?: string | null;
  tipos_consulta?: { id: number; nombre: string }[];
  created_at: string;
  updated_at: string;
};

export type PaquetePayload = {
  slug?: string;
  nombre: string;
  tipo: 'paquete' | 'membresia';
  consultas_incluidas: number;
  vigencia_dias?: number | null;
  precio_centavos: number;
  moneda: string;
  descuento_porcentaje?: number | null;
  activo?: boolean;
  destacado?: boolean;
  orden_visualizacion?: number;
  imagen_url?: string | null;
  tipos_consulta_ids?: number[];
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export const listPaquetes = (params: { q?: string; activo?: boolean; tipo?: string; page?: number } = {}) =>
  api.get<Paginated<Paquete>>('/admin/paquetes', { params });

export const crearPaquete = (payload: PaquetePayload) =>
  api.post<{ data: Paquete }>('/admin/paquetes', payload);

export const actualizarPaquete = (id: number, payload: PaquetePayload) =>
  api.patch<{ data: Paquete }>(`/admin/paquetes/${id}`, payload);

export const eliminarPaquete = (id: number) => api.delete(`/admin/paquetes/${id}`);

export const togglePaquete = (id: number) =>
  api.post<{ data: Paquete }>(`/admin/paquetes/${id}/toggle`);
