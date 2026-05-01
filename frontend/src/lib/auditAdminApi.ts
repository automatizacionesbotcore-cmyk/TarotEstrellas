import { api } from './api';

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

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export const listAuditLog = (params: { setting_key?: string; user_id?: number; page?: number } = {}) =>
  api.get<Paginated<AuditEntry>>('/admin/audit-log', { params });
