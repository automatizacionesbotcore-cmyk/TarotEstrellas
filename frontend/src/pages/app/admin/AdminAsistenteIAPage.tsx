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
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Astrea IA</p>
          <h1>Asistente de cliente</h1>
          <p className="admin-page-subtitle">
            Consulta el historial de sesiones de un cliente. Pega su UUID y pregunta al agente.
          </p>
        </div>
      </header>

      <form onSubmit={submit} className="admin-filter-card admin-ai-client-form">
        <label>
          Cliente UUID
          <input
            className="form-input"
            type="text"
            value={clienteUuidInput}
            onChange={(e) => setClienteUuidInput(e.target.value)}
            placeholder="UUID del cliente"
          />
        </label>
        <button type="submit" className="btn-primary">Cargar cliente</button>
      </form>

      {activeUuid ? (
        <section className="admin-ai-chat-shell">
          <AgenteChat mode="admin" clienteUuid={activeUuid} />
        </section>
      ) : (
        <div className="admin-empty-card">
          <p className="text-muted">Ingresa un UUID de cliente para comenzar.</p>
        </div>
      )}
    </main>
  );
}
