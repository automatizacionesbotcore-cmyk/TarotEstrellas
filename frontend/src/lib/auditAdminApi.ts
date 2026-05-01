import { api } from './api';

// Legacy: config audit
export type AuditEntry = {
  id: number;
  setting_key: string;
  old_value: string | null;
  new_value: string | null;
  changed_by_user_id: number | null;
  changed_by_email: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
};

// Unified audit log
export type UnifiedAuditLog = {
  id: number;
  uuid: string;
  user_id: number | null;
  user_email: string | null;
  user_role: string | null;
  action: string;
  auditable_type: string | null;
  auditable_id: number | null;
  changes: { old?: Record<string, unknown>; new?: Record<string, unknown>; [key: string]: unknown } | null;
  route: string | null;
  method: string | null;
  url: string | null;
  ip: string | null;
  user_agent: string | null;
  payload: Record<string, unknown> | null;
  status_code: number | null;
  created_at: string;
  user?: { id: number; uuid: string; name: string; email: string } | null;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type UnifiedAuditFilters = {
  q?: string;
  user_id?: number;
  action?: string;
  auditable_type?: string;
  desde?: string;
  hasta?: string;
  per_page?: number;
  page?: number;
};

export const listAuditLog = (params: { setting_key?: string; user_id?: number; page?: number } = {}) =>
  api.get<Paginated<AuditEntry>>('/admin/audit-log', { params });

export const listUnifiedAuditLogs = (params: UnifiedAuditFilters = {}) =>
  api.get<Paginated<UnifiedAuditLog>>('/admin/audit-logs', { params });

export const AUDIT_ACTIONS = [
  'login', 'login_failed', 'logout', 'register',
  'created', 'updated', 'deleted',
  'password_reset',
] as const;

export const AUDIT_TYPES: Record<string, string> = {
  'App\\Models\\Cita': 'Cita',
  'App\\Models\\Pago': 'Pago',
  'App\\Models\\User': 'Usuario',
  'App\\Models\\ComprobanteTransferencia': 'Comprobante',
  'App\\Models\\CuentaBancaria': 'Cuenta Bancaria',
  'App\\Models\\Cupon': 'Cupón',
  'App\\Models\\TipoConsulta': 'Tipo Consulta',
  'App\\Models\\AppSetting': 'Configuración',
  'App\\Models\\Reembolso': 'Reembolso',
  'App\\Models\\UserRole': 'Rol Usuario',
  'App\\Models\\PerfilEspecialista': 'Perfil Especialista',
};

