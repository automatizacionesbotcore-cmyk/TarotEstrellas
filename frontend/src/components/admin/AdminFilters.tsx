import type { ChangeEvent } from 'react';

export type FilterValues = {
  estado?: string;
  razon?: string;
  metodo?: string;
  from_date?: string;
  to_date?: string;
};

type FilterOption = { value: string; label: string };

type Props = {
  values:     FilterValues;
  onChange:   (next: FilterValues) => void;
  estados?:   FilterOption[];
  metodos?:   FilterOption[];
  razones?:   FilterOption[];
  showDates?: boolean;
};

export function AdminFilters({
  values,
  onChange,
  estados = [],
  metodos = [],
  razones = [],
  showDates = true,
}: Props) {
  const set = (key: keyof FilterValues) => (e: ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    onChange({ ...values, [key]: e.target.value || undefined });
  };

  return (
    <div className="admin-filters">
      {estados.length > 0 && (
        <label>
          Estado
          <select value={values.estado ?? ''} onChange={set('estado')}>
            <option value="">Todos</option>
            {estados.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>
        </label>
      )}
      {metodos.length > 0 && (
        <label>
          Método
          <select value={values.metodo ?? ''} onChange={set('metodo')}>
            <option value="">Todos</option>
            {metodos.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>
        </label>
      )}
      {razones.length > 0 && (
        <label>
          Razón
          <select value={values.razon ?? ''} onChange={set('razon')}>
            <option value="">Todas</option>
            {razones.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>
        </label>
      )}
      {showDates && (
        <>
          <label>Desde<input type="date" value={values.from_date ?? ''} onChange={set('from_date')} /></label>
          <label>Hasta<input type="date" value={values.to_date   ?? ''} onChange={set('to_date')} /></label>
        </>
      )}
    </div>
  );
}
