export function generateIcs(params: {
  title: string;
  startUtc: string;
  endUtc: string;
  description?: string;
}): void {
  const fmt = (iso: string) =>
    iso.replace(/[-:]/g, '').replace('.000Z', 'Z').slice(0, 16) + 'Z';

  const content = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//TarotEstrellas//ES',
    'BEGIN:VEVENT',
    `DTSTART:${fmt(params.startUtc)}`,
    `DTEND:${fmt(params.endUtc)}`,
    `SUMMARY:${params.title}`,
    params.description ? `DESCRIPTION:${params.description}` : '',
    'END:VEVENT',
    'END:VCALENDAR',
  ]
    .filter(Boolean)
    .join('\r\n');

  const blob = new Blob([content], { type: 'text/calendar;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'cita-tarotestrellas.ics';
  a.click();
  URL.revokeObjectURL(url);
}
