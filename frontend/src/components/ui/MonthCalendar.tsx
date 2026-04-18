import { useMemo, useState } from 'react';
import { motion } from 'framer-motion';

type Props = {
  value: string;           // YYYY-MM-DD seleccionado
  onChange: (date: string) => void;
  min?: string;            // YYYY-MM-DD mínimo (default: hoy)
};

const DAYS_ES = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
const MONTHS_ES = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function toYmd(d: Date) {
  return d.toISOString().slice(0, 10);
}

function buildGrid(year: number, month: number): (string | null)[] {
  const first = new Date(year, month, 1);
  // getDay() → 0=Dom…6=Sáb, convertir a Lu=0…Do=6
  const startDow = (first.getDay() + 6) % 7;
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const cells: (string | null)[] = Array(startDow).fill(null);
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push(toYmd(new Date(year, month, d)));
  }
  while (cells.length % 7 !== 0) cells.push(null);
  return cells;
}

export function MonthCalendar({ value, onChange, min }: Props) {
  const today = toYmd(new Date());
  const minDate = min ?? today;

  const [cursor, setCursor] = useState<{ year: number; month: number }>(() => {
    const base = value ? new Date(value + 'T12:00:00') : new Date();
    return { year: base.getFullYear(), month: base.getMonth() };
  });

  const grid = useMemo(() => buildGrid(cursor.year, cursor.month), [cursor]);

  const canPrev = useMemo(() => {
    const prevMonth = new Date(cursor.year, cursor.month - 1, 1);
    const minMonth = new Date(minDate + 'T12:00:00');
    minMonth.setDate(1);
    return prevMonth >= minMonth;
  }, [cursor, minDate]);

  const goPrev = () =>
    setCursor(({ year, month }) =>
      month === 0 ? { year: year - 1, month: 11 } : { year, month: month - 1 },
    );

  const goNext = () =>
    setCursor(({ year, month }) =>
      month === 11 ? { year: year + 1, month: 0 } : { year, month: month + 1 },
    );

  return (
    <div className="month-calendar" role="group" aria-label="Selecciona una fecha">
      {/* ── Header nav ── */}
      <div className="cal-header">
        <button
          type="button"
          className="cal-nav"
          onClick={goPrev}
          disabled={!canPrev}
          aria-label="Mes anterior"
        >
          ‹
        </button>
        <motion.span
          key={`${cursor.year}-${cursor.month}`}
          className="cal-month-label"
          initial={{ opacity: 0, y: -6 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.2 }}
        >
          {MONTHS_ES[cursor.month]} {cursor.year}
        </motion.span>
        <button type="button" className="cal-nav" onClick={goNext} aria-label="Mes siguiente">
          ›
        </button>
      </div>

      {/* ── Day labels ── */}
      <div className="cal-grid cal-days-row">
        {DAYS_ES.map((d) => (
          <span key={d} className="cal-day-label">{d}</span>
        ))}
      </div>

      {/* ── Day cells ── */}
      <motion.div
        key={`${cursor.year}-${cursor.month}`}
        className="cal-grid"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.18 }}
      >
        {grid.map((date, i) => {
          if (!date) return <span key={`empty-${i}`} className="cal-cell empty" />;

          const isDisabled = date < minDate;
          const isSelected = date === value;
          const isToday = date === today;

          return (
            <button
              key={date}
              type="button"
              className={[
                'cal-cell',
                isSelected  ? 'selected'  : '',
                isToday     ? 'today'     : '',
                isDisabled  ? 'disabled'  : '',
              ].join(' ').trim()}
              disabled={isDisabled}
              aria-pressed={isSelected}
              aria-label={date}
              onClick={() => onChange(date)}
            >
              {parseInt(date.slice(8), 10)}
            </button>
          );
        })}
      </motion.div>
    </div>
  );
}
