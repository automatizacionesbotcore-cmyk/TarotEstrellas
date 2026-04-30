import { useState } from 'react';
import { AgenteChat } from '../../../components/agente/AgenteChat';

export function AdminAsistenteIAPage() {
  const [clienteUuidInput, setClienteUuidInput] = useState('');
  const [activeUuid, setActiveUuid] = useState<string | null>(null);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setActiveUuid(clienteUuidInput.trim() || null);
  };

  return (
    <main className="page-content">
      <header style={{ marginBottom: '1.5rem' }}>
        <h1>Asistente IA — Cliente</h1>
        <p style={{ color: 'var(--text-muted)' }}>
          Consulta el historial de sesiones de un cliente. Pega su UUID y pregunta al agente.
        </p>
      </header>

      <form onSubmit={submit} style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem' }}>
        <input
          type="text"
          value={clienteUuidInput}
          onChange={(e) => setClienteUuidInput(e.target.value)}
          placeholder="UUID del cliente"
          style={{ flex: 1 }}
        />
        <button type="submit" className="btn-secondary">Cargar</button>
      </form>

      {activeUuid ? (
        <AgenteChat mode="admin" clienteUuid={activeUuid} />
      ) : (
        <p style={{ color: 'var(--text-muted)' }}>Ingresa un UUID de cliente para comenzar.</p>
      )}
    </main>
  );
}
