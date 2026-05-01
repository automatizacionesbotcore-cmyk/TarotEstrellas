import { api } from './api';

export type AgenteMetricsTotales = {
  conversaciones: number;
  tokens_in: number;
  tokens_out: number;
  errores: number;
  costo_usd: number;
  latencia_avg_ms: number;
};

export type AgenteMetricsPorModelo = {
  modelo: string | null;
  conv: number;
  tokens_in: number;
  tokens_out: number;
  latencia_avg: number;
  errores: number;
  costo_usd: number;
  precio: { in: number; out: number };
};

export type AgenteMetricsTopUsuario = {
  cliente_id: number;
  conversaciones: number;
  tokens_in: number;
  tokens_out: number;
  costo_usd: number;
};

export type AgenteMetricsSerie = {
  dia: string;
  conversaciones: number;
  costo_usd: number;
};

export type AgenteMetricsResponse = {
  desde: string;
  hasta: string;
  totales: AgenteMetricsTotales;
  por_modelo: AgenteMetricsPorModelo[];
  top_usuarios: AgenteMetricsTopUsuario[];
  serie_diaria: AgenteMetricsSerie[];
  precios: Record<string, { in: number; out: number }>;
};

export const getAgenteMetrics = (params: { desde?: string; hasta?: string } = {}) =>
  api.get<AgenteMetricsResponse>('/admin/agente/metrics', { params });
