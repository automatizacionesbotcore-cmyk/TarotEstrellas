import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { SupportTicketForm } from '../../components/support/SupportTicketForm';
import {
  getMySupportTicket,
  listMySupportTickets,
  replyMySupportTicket,
  SUPPORT_STATUS_LABELS,
  SUPPORT_TYPES,
  buildSupportFormData,
  type SupportTicket,
} from '../../lib/supportApi';

export function SoportePage() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
  const [reply, setReply] = useState('');
  const [files, setFiles] = useState<FileList | null>(null);
  const [replying, setReplying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ticketsQuery = useQuery({
    queryKey: ['me-soporte', page],
    queryFn: () => listMySupportTickets(page),
  });

  const detailQuery = useQuery({
    queryKey: ['me-soporte-detail', selectedUuid],
    queryFn: () => getMySupportTicket(selectedUuid!),
    enabled: Boolean(selectedUuid),
  });

  const selected = detailQuery.data;
  const typeLabel = (value: string) => SUPPORT_TYPES.find((t) => t.value === value)?.label ?? value;

  const handleReply = async () => {
    if (!selectedUuid || !reply.trim()) return;
    setReplying(true);
    setError(null);
    try {
      const data = buildSupportFormData({
        tipo_error: 'plataforma',
        asunto: 'Respuesta',
        descripcion: reply,
        imagenes: files,
      });
      data.set('mensaje', reply);
      await replyMySupportTicket(selectedUuid, data);
      setReply('');
      setFiles(null);
      await queryClient.invalidateQueries({ queryKey: ['me-soporte'] });
      await queryClient.invalidateQueries({ queryKey: ['me-soporte-detail', selectedUuid] });
    } catch (err: any) {
      setError(err?.response?.data?.message ?? 'No se pudo enviar la respuesta.');
    } finally {
      setReplying(false);
    }
  };

  return (
    <main className="page-content support-page">
      <header className="admin-page-header">
        <div>
          <p className="dash-eyebrow">Soporte</p>
          <h1>Mis solicitudes</h1>
          <p className="admin-page-subtitle">
            Crea solicitudes de soporte y revisa el estado de las respuestas del equipo.
          </p>
        </div>
      </header>

      <section className="support-layout">
        <article className="admin-filter-card">
          <h2>Nueva solicitud</h2>
          <SupportTicketForm
            mode="authenticated"
            onCreated={(ticket) => {
              queryClient.invalidateQueries({ queryKey: ['me-soporte'] });
              setSelectedUuid(ticket.uuid);
            }}
          />
        </article>

        <article className="admin-filter-card">
          <h2>Historial</h2>
          {ticketsQuery.isLoading ? <p>Cargando solicitudes...</p> : null}
          {ticketsQuery.data?.data.length === 0 ? <p className="admin-page-subtitle">Aún no tienes solicitudes.</p> : null}
          <div className="support-ticket-list">
            {ticketsQuery.data?.data.map((ticket: SupportTicket) => (
              <button
                key={ticket.uuid}
                type="button"
                className={`support-ticket-item${selectedUuid === ticket.uuid ? ' is-active' : ''}`}
                onClick={() => setSelectedUuid(ticket.uuid)}
              >
                <strong>{ticket.codigo}</strong>
                <span>{ticket.asunto}</span>
                <small>{SUPPORT_STATUS_LABELS[ticket.estado] ?? ticket.estado}</small>
              </button>
            ))}
          </div>
          {ticketsQuery.data?.meta && ticketsQuery.data.meta.last_page > 1 ? (
            <div className="pagination">
              <button className="btn-secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Anterior</button>
              <span>Página {page} de {ticketsQuery.data.meta.last_page}</span>
              <button className="btn-secondary" disabled={page >= ticketsQuery.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>Siguiente</button>
            </div>
          ) : null}
        </article>
      </section>

      {selected ? (
        <section className="admin-filter-card support-detail-card">
          <div className="support-detail-header">
            <div>
              <p className="dash-eyebrow">{selected.codigo}</p>
              <h2>{selected.asunto}</h2>
              <p className="admin-page-subtitle">{typeLabel(selected.tipo_error)}</p>
            </div>
            <span className={`badge estado-${selected.estado}`}>{SUPPORT_STATUS_LABELS[selected.estado] ?? selected.estado}</span>
          </div>

          <div className="support-thread">
            {selected.mensajes?.map((message) => (
              <div key={message.id} className={`support-message support-message-${message.autor_tipo}`}>
                <strong>{message.autor_tipo === 'admin' ? 'Soporte TarotEstrellas' : 'Tú'}</strong>
                <p>{message.mensaje}</p>
                <small>{new Date(message.created_at).toLocaleString('es-CL')}</small>
              </div>
            ))}
          </div>

          <div className="support-reply-box">
            <label>
              Responder
              <textarea className="input-field" rows={4} value={reply} onChange={(e) => setReply(e.target.value)} />
            </label>
            <input className="input-field" type="file" accept="image/*" multiple onChange={(e) => setFiles(e.target.files)} />
            {error ? <p className="form-error">{error}</p> : null}
            <button className="btn-primary" type="button" disabled={replying || !reply.trim()} onClick={handleReply}>
              {replying ? 'Enviando...' : 'Enviar respuesta'}
            </button>
          </div>
        </section>
      ) : null}
    </main>
  );
}
