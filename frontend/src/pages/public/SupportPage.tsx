import { Link } from 'react-router-dom';
import { SupportTicketForm } from '../../components/support/SupportTicketForm';

export function SupportPage() {
  return (
    <main className="page-content support-page">
      <section className="auth-card support-public-card">
        <p className="dash-eyebrow">Soporte de plataforma</p>
        <h1>¿Problemas con tu cuenta?</h1>
        <p className="auth-form-subtitle">
          Si no puedes iniciar sesión, registrarte o completar una acción en TarotEstrellas,
          envíanos el detalle y una captura. Te avisaremos por correo cada actualización.
        </p>

        <SupportTicketForm mode="public" />

        <p className="auth-footer-action">
          <Link className="btn-secondary auth-secondary-cta" to="/auth/login">Volver a iniciar sesión</Link>
        </p>
      </section>
    </main>
  );
}
