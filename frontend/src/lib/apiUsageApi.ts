import { api } from './api';

export type ApiUsageRow = {
  provider: string;
  unidad: string;
  usado: number;
  limite: number;
  porcentaje: number;
  warn_pct: number;
  estado: 'ok' | 'warning' | 'exceeded';
};

export type ApiUsageAlert = {
  id: number;
  provider: string;
  period: string;
  nivel: 'warning' | 'exceeded';
  porcentaje: number;
  usado: number;
  limite: number;
  notificado_at: string | null;
  created_at: string;
};

export type ApiUsageResponse = {
  periodo: string;
  rows: ApiUsageRow[];
  alertas_recientes: ApiUsageAlert[];
  destinatarios: string[];
  emails_extra: string;
  limites: Record<string, { limite: number; warn_pct: number }>;
};

export async function getApiUsage(): Promise<ApiUsageResponse> {
  const { data } = await api.get('/admin/api-usage');
  return data;
}

export async function updateApiLimits(payload: {
  anthropic?: { limite?: number; warn_pct?: number };
  daily?: { limite?: number; warn_pct?: number };
  alert_emails?: string;
}): Promise<{ ok: true }> {
  const { data } = await api.patch('/admin/api-usage/limits', payload);
  return data;
}

export async function triggerApiCheck(): Promise<{ ok: true; rows: ApiUsageRow[] }> {
  const { data } = await api.post('/admin/api-usage/check', {});
  return data;
}
