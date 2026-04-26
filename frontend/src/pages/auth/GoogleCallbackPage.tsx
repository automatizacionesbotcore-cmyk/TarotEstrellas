import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';

export function GoogleCallbackPage() {
  const [params]   = useSearchParams();
  const navigate   = useNavigate();
  const setSession = useAuthStore((s) => s.setSession);

  useEffect(() => {
    const token   = params.get('token');
    const userRaw = params.get('user');
    const error   = params.get('error');

    if (error || !token || !userRaw) {
      navigate('/auth/login?error=google', { replace: true });
      return;
    }

    try {
      const user = JSON.parse(decodeURIComponent(userRaw));
      setSession(token, user);
      navigate('/app', { replace: true });
    } catch {
      navigate('/auth/login?error=google', { replace: true });
    }
  }, []);

  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh', flexDirection: 'column', gap: '1rem' }}>
      <p style={{ color: 'var(--text-muted)' }}>Iniciando sesión con Google…</p>
    </div>
  );
}
