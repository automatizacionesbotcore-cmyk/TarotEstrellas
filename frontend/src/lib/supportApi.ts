import { api } from './api';

export type SupportTicket = {
  uuid: string;
  codigo: string;
  nombre?: string | null;
  email: string;
  tipo_error: string;
  asunto: string;
  descripcion: string;
  estado: string;
  prioridad: string;
  created_at: string;
  ultimo_cambio_at?: string | null;
  resuelto_at?: string | null;
  messages_count?: number;
  cliente?: { uuid: string; name: string; email: string } | null;
  mensajes?: Array<{
    id: number;
    autor_tipo: 'cliente' | 'admin' | 'sistema';
    autor?: string | null;
    mensaje: string;
    visible_para_cliente?: boolean;
    created_at: string;
  }>;
  adjuntos?: Array<{
    id: number;
    nombre_original: string;
    mime?: string | null;
    size: number;
    download_url?: string;
  }>;
};

export type SupportListResponse = {
  data: SupportTicket[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export const SUPPORT_TYPES = [
  { value: 'login', label: 'No puedo iniciar sesión' },
  { value: 'registro', label: 'No puedo registrarme' },
  { value: 'pago', label: 'Problema con pagos' },
  { value: 'agenda', label: 'Problema al agendar' },
  { value: 'videollamada', label: 'Problema con videollamada' },
  { value: 'plataforma', label: 'Error de plataforma' },
  { value: 'otro', label: 'Otro problema' },
];

export const SUPPORT_STATUS_LABELS: Record<string, string> = {
  nuevo: 'Nuevo',
  en_revision: 'En revisión',
  esperando_usuario: 'Esperando usuario',
  resuelto: 'Resuelto',
  cerrado: 'Cerrado',
};

export function buildSupportFormData(values: {
  nombre?: string;
  email?: string;
  tipo_error: string;
  asunto: string;
  descripcion: string;
  imagenes?: FileList | File[] | null;
}) {
  const data = new FormData();
  Object.entries(values).forEach(([key, value]) => {
    if (key === 'imagenes' || value === undefined || value === null) return;
    data.append(key, String(value));
  });
  Array.from(values.imagenes ?? []).slice(0, 3).forEach((file) => {
    data.append('imagenes[]', file);
  });
  return data;
}

export async function createPublicSupportTicket(formData: FormData) {
  const response = await api.post<{ message: string; data: SupportTicket }>('/soporte', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
}

export async function createMySupportTicket(formData: FormData) {
  const response = await api.post<{ message: string; data: SupportTicket }>('/me/soporte', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
}

export async function listMySupportTickets(page = 1) {
  const response = await api.get<SupportListResponse>('/me/soporte', { params: { page, per_page: 10 } });
  return response.data;
}

export async function getMySupportTicket(uuid: string) {
  const response = await api.get<{ data: SupportTicket }>(`/me/soporte/${uuid}`);
  return response.data.data;
}

export async function replyMySupportTicket(uuid: string, formData: FormData) {
  const response = await api.post<{ message: string; data: SupportTicket }>(`/me/soporte/${uuid}/responder`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
}

export async function listAdminSupportTickets(params: Record<string, string | number | undefined>) {
  const response = await api.get<SupportListResponse>('/admin/soporte', { params });
  return response.data;
}

export async function getAdminSupportTicket(uuid: string) {
  const response = await api.get<{ data: SupportTicket }>(`/admin/soporte/${uuid}`);
  return response.data.data;
}

export async function updateAdminSupportTicket(uuid: string, data: {
  estado: string;
  prioridad: string;
  respuesta?: string;
  visible_para_cliente?: boolean;
}) {
  const response = await api.patch<{ message: string; data: SupportTicket }>(`/admin/soporte/${uuid}`, data);
  return response.data;
}

export async function downloadSupportAttachment(url: string, filename: string) {
  const response = await api.get(url.replace(/^\/api/, ''), { responseType: 'blob' });
  const blobUrl = URL.createObjectURL(response.data);
  const anchor = document.createElement('a');
  anchor.href = blobUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(blobUrl);
}
