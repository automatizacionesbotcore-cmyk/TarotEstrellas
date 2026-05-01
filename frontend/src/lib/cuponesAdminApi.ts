import { api } from './api';

export type Cupon = {
  id: number;
  codigo: string;
  descripcion: string | null;
  tipo_descuento: 'porcentaje' | 'monto_fijo';
  valor_descuento: number;
  moneda: string | null;
  uso_maximo_total: number | null;
  uso_maximo_por_cliente: number | null;
  usos_totales: number;
  vigente_desde: string;
  vigente_hasta: string | null;
  monto_minimo_centavos: number | null;
  solo_primera_consulta: boolean;
  activo: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
};

export type CuponPayload = {
  codigo: string;
  descripcion?: string | null;
  tipo_descuento: 'porcentaje' | 'monto_fijo';
  valor_descuento: number;
  moneda?: string | null;
  uso_maximo_total?: number | null;
  uso_maximo_por_cliente?: number | null;
  vigente_desde: string;
  vigente_hasta?: string | null;
  monto_minimo_centavos?: number | null;
  solo_primera_consulta?: boolean;
  activo?: boolean;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type ListCuponesParams = {
  q?: string;
  activo?: boolean;
  vigentes?: boolean;
  page?: number;
  per_page?: number;
};

export const listCupones = (params: ListCuponesParams = {}) =>
  api.get<Paginated<Cupon>>('/admin/cupones', { params });

export const getCupon = (codigo: string) => api.get<{ data: Cupon }>(`/admin/cupones/${codigo}`);

export const crearCupon = (payload: CuponPayload) => api.post<{ data: Cupon }>('/admin/cupones', payload);

export const actualizarCupon = (codigo: string, payload: CuponPayload) =>
  api.patch<{ data: Cupon }>(`/admin/cupones/${codigo}`, payload);

export const eliminarCupon = (codigo: string) => api.delete(`/admin/cupones/${codigo}`);

export const toggleCupon = (codigo: string) => api.post<{ data: Cupon }>(`/admin/cupones/${codigo}/toggle`);
