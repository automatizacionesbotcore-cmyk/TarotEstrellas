export type TimelineEvent = {
  tipo:      string;
  timestamp: string;
  detalle?:  string | null;
  actor?:    string | null;
};

export function Timeline({ events }: { events: TimelineEvent[] }) {
  if (!events || events.length === 0) {
    return <p className="admin-table-empty">Sin eventos registrados.</p>;
  }

  return (
    <ol className="timeline">
      {events.map((ev, idx) => (
        <li key={`${ev.tipo}-${idx}`} className="timeline-item">
          <div className="timeline-marker" />
          <div className="timeline-body">
            <strong>{ev.tipo}</strong>
            <time>{new Date(ev.timestamp).toLocaleString('es-CL')}</time>
            {ev.detalle && <p className="timeline-detail">{ev.detalle}</p>}
            {ev.actor   && <small>por {ev.actor}</small>}
          </div>
        </li>
      ))}
    </ol>
  );
}
