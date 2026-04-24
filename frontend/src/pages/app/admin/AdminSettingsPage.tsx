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
      <h1>Ajustes — Validación de transferencias</h1>
      <form className="auth-form" onSubmit={(e) => { e.preventDefault(); save.mutate(local); }}>
        <label style={{ flexDirection: 'row', gap: '0.5rem', alignItems: 'center' }}>
          <input
            type="checkbox"
            checked={local.auto_approval_enabled}
            onChange={(e) => setLocal({ ...local, auto_approval_enabled: e.target.checked })}
          />
          Aprobación automática habilitada
        </label>
        <label>
          Umbral (centavos, opcional)
          <input
            type="number"
            min={0}
            value={local.threshold_centavos ?? ''}
            onChange={(e) => setLocal({ ...local, threshold_centavos: e.target.value ? Number(e.target.value) : null })}
          />
        </label>
        <button className="btn-primary" type="submit" disabled={save.isPending}>
          {save.isPending ? 'Guardando…' : 'Guardar'}
        </button>
      </form>
    </main>
  );
}
