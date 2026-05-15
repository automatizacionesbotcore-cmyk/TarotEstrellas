import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';

type Settings = {
  auto_approval_enabled: boolean;
  threshold_centavos?:   number | null;
  updated_at?:           string;
};

export function AdminSettingsPage() {
  const queryClient = useQueryClient();
  const [local, setLocal] = useState<Settings | null>(null);

  const query = useQuery({
    queryKey: ['admin', 'settings', 'transfer-validation'],
    queryFn:  async () => (await api.get<{ data: Settings }>('/admin/settings/transfer-validation')).data.data,
  });

  useEffect(() => {
    if (query.data && local === null) setLocal(query.data);
  }, [query.data, local]);

  const save = useMutation({
    mutationFn: (payload: Settings) => api.patch('/admin/settings/transfer-validation', payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] }),
  });

  if (query.isLoading || !local) return <main className="page-content"><p>Cargando…</p></main>;

  return (
    <main className="page-content">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Configuración</p>
          <h1>Validación de transferencias</h1>
          <p className="admin-page-subtitle">
            Controla la aprobación automática y el umbral usado para conciliación.
          </p>
        </div>
      </header>

      <form className="admin-settings-card" onSubmit={(e) => { e.preventDefault(); save.mutate(local); }}>
        <label className="admin-checkbox-row">
          <input
            type="checkbox"
            checked={local.auto_approval_enabled}
            onChange={(e) => setLocal({ ...local, auto_approval_enabled: e.target.checked })}
          />
          <span>Aprobación automática habilitada</span>
        </label>
        <label className="booking-field-label">
          Umbral (centavos, opcional)
          <input
            className="input-field"
            type="number"
            min={0}
            value={local.threshold_centavos ?? ''}
            onChange={(e) => setLocal({ ...local, threshold_centavos: e.target.value ? Number(e.target.value) : null })}
          />
        </label>
        <div className="admin-filter-actions">
          <button className="btn-primary" type="submit" disabled={save.isPending}>
            {save.isPending ? 'Guardando…' : 'Guardar cambios'}
          </button>
        </div>
      </form>
    </main>
  );
}
