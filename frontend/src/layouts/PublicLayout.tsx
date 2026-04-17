import { Link, Outlet } from 'react-router-dom';
import { ThemeToggle } from '../components/ui/ThemeToggle';

export function PublicLayout() {
  return (
    <div className="page-shell">
      <header className="main-header" role="banner">
        <Link className="brand" to="/" aria-label="TarotEstrellas — inicio">
          TarotEstrellas
        </Link>
        <nav aria-label="Navegación principal">
          <Link to="/servicios">Servicios</Link>
          <Link to="/auth/login">Ingresar</Link>
          <ThemeToggle />
        </nav>
      </header>
      <Outlet />
    </div>
  );
}
