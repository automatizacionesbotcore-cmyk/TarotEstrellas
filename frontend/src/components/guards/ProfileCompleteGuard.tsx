import { useEffect } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';

const RUTAS_EXENTAS = ['/app/completar-perfil', '/app/mi-cuenta'];

export function ProfileCompleteGuard() {
  const user = useAuthStore((s) => s.user);
  const refreshUser = useAuthStore((s) => s.refreshUser);
  const location = useLocation();

  useEffect(() => {
    if (user && user.perfil_completo === undefined) {
      refreshUser();
    }
  }, [user?.uuid]);

  const isExent = RUTAS_EXENTAS.some((r) => location.pathname.startsWith(r));

  if (user && user.perfil_completo === false && !isExent) {
    return <Navigate to="/app/completar-perfil" replace />;
  }

  return <Outlet />;
}
