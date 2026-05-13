import { api } from './api';

export type Notificacion = {
  id: number;
  canal: 'email' | 'whatsapp' | 'sms' | 'push';
  destinatario: string;
  plantilla_clave?: string | null;
  tipo?: string | null;
  asunto: string | null;
  estado: 'enviado' | 'error' | 'rebotado' | 'leido' | 'pendiente';
  error?: string | null;
  user_id: number | null;
  cita_id?: number | null;
  enviado_en: string | null;
  created_at: string;
};

export type NotificacionMetricas = {
  hoy: number;
  errores: number;
  porCanal: Record<string, number>;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export const listNotificaciones = (params: {
  canal?: string;
  estado?: string;
  desde?: string;
  hasta?: string;
  page?: number;
} = {}) => api.get<Paginated<Notificacion>>('/admin/notificaciones', { params });

export const getNotificacionMetricas = () =>
  api.get<NotificacionMetricas>('/admin/notificaciones/metrics');
