import { AgenteChat } from '../../components/agente/AgenteChat';

export function AsistenteIAPage() {
  return (
    <main className="page-content">
      <header style={{ marginBottom: '1.5rem' }}>
        <h1>Asistente IA</h1>
        <p style={{ color: 'var(--text-muted)' }}>
          Pregunta sobre tu propio historial de consultas. El asistente sintetiza tus sesiones previas
          y te ayuda a recordar lo conversado.
        </p>
      </header>
      <AgenteChat mode="self" />
    </main>
  );
}
