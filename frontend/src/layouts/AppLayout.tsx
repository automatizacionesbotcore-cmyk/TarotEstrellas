import { Link, Outlet } from 'react-router-dom';
import { ThemeToggle } from '../components/ui/ThemeToggle';
import { useAuthStore } from '../stores/authStore';

export function AppLayout() {
  const user = useAuthStore((state) => state.user);

  return (
    <div className="page-shell">
      <header className="main-header">
        <Link className="brand" to="/app">
          TarotEstrellas
        </Link>
        <nav>
          <Link to="/app">Dashboard</Link>
          <Link to="/app/mis-consultas">Mis consultas</Link>
          <Link to="/app/mi-cuenta">Mi cuenta</Link>
          <ThemeToggle />
        </nav>
      </header>

      {user && !user.email_verified_at ? (
        <div className="verify-banner">Tu correo aun no esta verificado. Revisa tu bandeja para continuar.</div>
      ) : null}

      <Outlet />
    </div>
  );
}
