import { AgenteChat } from '../../components/agente/AgenteChat';
import { useAuthStore } from '../../stores/authStore';

export function AsistenteIAPage() {
  const user = useAuthStore((s) => s.user);
  const nombre = user?.nombre?.split(' ')[0] ?? '';

  const saludo = nombre
    ? `Hola ${nombre}, soy Astrea, asistente IA de TarotEstrellas.`
    : 'Hola, soy Astrea, asistente IA de TarotEstrellas.';

  return (
    <main className="page-content asistente-page">
      <div className="asistente-page__hero">
        <div className="asistente-page__stars" aria-hidden="true">
          <span /><span /><span /><span /><span /><span />
        </div>
        <div className="asistente-page__hero-content">
          <div className="asistente-page__avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor">
              <path d="M12 2.5l2.6 6.5 7 .6-5.3 4.6 1.7 6.8L12 17.4l-6 3.6 1.7-6.8L2.4 9.6l7-.6L12 2.5z" />
            </svg>
          </div>
          <div>
            <h1 className="asistente-page__title">Astrea ✨</h1>
            <p className="asistente-page__subtitle">
              {saludo} Pregúntame sobre tu propio historial de consultas: sintetizo tus sesiones
              previas y te ayudo a recordar lo conversado.
            </p>
          </div>
        </div>
      </div>

      <AgenteChat mode="self" />
    </main>
  );
}
