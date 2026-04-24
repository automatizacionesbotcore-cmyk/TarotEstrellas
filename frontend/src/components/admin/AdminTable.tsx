import React from 'react';

export type Column<T> = {
  key:     string;
  label:   string;
  render?: (row: T) => React.ReactNode;
};

type Props<T> = {
  columns:      Column<T>[];
  rows:         T[];
  loading?:     boolean;
  emptyLabel?:  string;
  onRowClick?:  (row: T) => void;
  rowKey:       (row: T) => string | number;
};

export function AdminTable<T>({ columns, rows, loading, emptyLabel = 'Sin resultados', onRowClick, rowKey }: Props<T>) {
  if (loading) return <p className="admin-table-empty">Cargando…</p>;
  if (rows.length === 0) return <p className="admin-table-empty">{emptyLabel}</p>;

  return (
    <table className="admin-table">
      <thead>
        <tr>{columns.map((c) => <th key={c.key}>{c.label}</th>)}</tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr
            key={rowKey(row)}
            onClick={onRowClick ? () => onRowClick(row) : undefined}
            className={onRowClick ? 'is-clickable' : ''}
          >
            {columns.map((c) => (
              <td key={c.key}>{c.render ? c.render(row) : (row as Record<string, React.ReactNode>)[c.key]}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
