import React, { useEffect, useMemo, useState } from 'react';

export type Column<T> = {
  key:     string;
  label:   string;
  sortable?: boolean;
  render?: (row: T) => React.ReactNode;
};

type Pagination = {
  currentPage: number;
  lastPage:    number;
  total?:      number;
  onPageChange: (page: number) => void;
};

type SortState = {
  sortBy:  string;
  sortDir: 'asc' | 'desc';
  onSort:  (key: string) => void;
};

type Props<T> = {
  columns:      Column<T>[];
  rows:         T[];
  loading?:     boolean;
  emptyLabel?:  string;
  onRowClick?:  (row: T) => void;
  rowKey:       (row: T) => string | number;
  searchable?:  boolean;
  searchPlaceholder?: string;
  getSearchText?: (row: T) => string;
  pageSize?:    number;
  pageSizeOptions?: number[];
  pagination?:  Pagination;
  sort?:        SortState;
  showFooter?:  boolean;
};

export function AdminTable<T>({
  columns,
  rows,
  loading,
  emptyLabel = 'Sin resultados',
  onRowClick,
  rowKey,
  searchable = true,
  searchPlaceholder = 'Buscar en la tabla',
  getSearchText,
  pageSize = 10,
  pageSizeOptions = [10, 25, 50],
  pagination,
  sort,
  showFooter = true,
}: Props<T>) {
  const [query, setQuery] = useState('');
  const [localPage, setLocalPage] = useState(1);
  const [localPageSize, setLocalPageSize] = useState(pageSize);

  const filteredRows = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return rows;

    return rows.filter((row) => {
      const haystack = getSearchText
        ? getSearchText(row)
        : JSON.stringify(row);
      return haystack.toLowerCase().includes(needle);
    });
  }, [getSearchText, query, rows]);

  const localLastPage = Math.max(1, Math.ceil(filteredRows.length / localPageSize));
  const currentPage = pagination ? pagination.currentPage : Math.min(localPage, localLastPage);
  const lastPage = pagination ? pagination.lastPage : localLastPage;
  const total = pagination?.total ?? filteredRows.length;
  const visibleRows = pagination
    ? filteredRows
    : filteredRows.slice((currentPage - 1) * localPageSize, currentPage * localPageSize);

  useEffect(() => {
    setLocalPage(1);
  }, [query, rows.length, localPageSize]);

  const goToPage = (page: number) => {
    const next = Math.min(Math.max(1, page), lastPage);
    if (pagination) pagination.onPageChange(next);
    else setLocalPage(next);
  };

  return (
    <section className="admin-data-table">
      {(searchable || pageSizeOptions.length > 1) && (
        <div className="admin-data-table-toolbar">
          {searchable && (
            <label className="admin-data-table-search">
              <span>Buscar</span>
              <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder={searchPlaceholder}
              />
            </label>
          )}

          {!pagination && pageSizeOptions.length > 1 && (
            <label className="admin-data-table-size">
              <span>Filas</span>
              <select value={localPageSize} onChange={(e) => setLocalPageSize(Number(e.target.value))}>
                {pageSizeOptions.map((option) => <option key={option} value={option}>{option}</option>)}
              </select>
            </label>
          )}
        </div>
      )}

      <div className="admin-table-scroll">
        <table className="admin-table">
          <thead>
            <tr>
              {columns.map((c) => {
                const isSorted = sort?.sortBy === c.key;
                return (
                  <th key={c.key}>
                    {c.sortable && sort ? (
                      <button
                        type="button"
                        className="admin-table-sort"
                        onClick={() => sort.onSort(c.key)}
                        aria-sort={isSorted ? (sort.sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                      >
                        <span>{c.label}</span>
                        <span aria-hidden="true" className="admin-table-sort-icon">
                          {isSorted ? (sort.sortDir === 'asc' ? '▲' : '▼') : '↕'}
                        </span>
                      </button>
                    ) : c.label}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={columns.length} className="admin-table-empty">Cargando…</td>
              </tr>
            )}

            {!loading && visibleRows.length === 0 && (
              <tr>
                <td colSpan={columns.length} className="admin-table-empty">{emptyLabel}</td>
              </tr>
            )}

            {!loading && visibleRows.map((row) => (
              <tr
                key={rowKey(row)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={onRowClick ? 'is-clickable' : ''}
              >
                {columns.map((c) => (
                  <td key={c.key} data-label={c.label || 'Acciones'}>
                    {c.render ? c.render(row) : (row as Record<string, React.ReactNode>)[c.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showFooter && !loading && total > 0 && (
        <footer className="admin-data-table-footer">
          <span>
            {total} resultado{total === 1 ? '' : 's'}
            {!pagination && total !== rows.length ? ` · ${rows.length} sin filtro` : ''}
          </span>
          <nav aria-label="Paginación de tabla">
            <button type="button" className="btn-secondary" disabled={currentPage <= 1} onClick={() => goToPage(currentPage - 1)}>
              Anterior
            </button>
            <span>Página {currentPage} de {lastPage}</span>
            <button type="button" className="btn-secondary" disabled={currentPage >= lastPage} onClick={() => goToPage(currentPage + 1)}>
              Siguiente
            </button>
          </nav>
        </footer>
      )}
    </section>
  );
}
