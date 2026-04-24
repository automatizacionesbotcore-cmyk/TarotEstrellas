import { Link } from 'react-router-dom';
import { LoginForm } from '../../components/forms/LoginForm';

export function LoginPage() {
  return (
    <div className="auth-card">
      <LoginForm />
      <div style={{ textAlign: 'center', marginTop: '1rem' }}>
        <Link to="/" className="auth-link-inline">
          ← Volver al inicio
        </Link>
      </div>
    </div>
  );
}
