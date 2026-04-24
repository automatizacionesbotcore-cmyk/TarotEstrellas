# Integración Full-Stack TarotEstrellas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unificar frontend (React/Vite/TS) y backend (Laravel/Sanctum) en la rama `backend/feat/backend-avance-fase`, corrigiendo brechas de configuración (CORS, .env, persistencia de user), añadiendo seis páginas admin y validando contratos endpoint por endpoint.

**Architecture:** Frontend consume API Laravel vía axios con Bearer token (Sanctum). CORS configurado en backend, URLs por env var en frontend. Zustand persiste token+user en localStorage y rehidrata con `/api/user` al inicio. Admin pages protegidas por guard que valida `user.roles.includes('admin')`. TanStack Query maneja cache y mutaciones.

**Tech Stack:** React 18, Vite 5, TypeScript, Zustand, TanStack Query, Axios, React Router 6, Framer Motion, Laravel 11, Sanctum, Stripe API.

---

## Fase 1 — Git + Configuración base

### Task 1: Cherry-pick del commit de deps faltante

**Files:**
- Modify: `frontend/package.json`, `frontend/package-lock.json` (traídos por cherry-pick)

- [ ] **Step 1: Verificar estado limpio**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git status
```

Expected: working tree clean, branch `backend/feat/backend-avance-fase`.

- [ ] **Step 2: Cherry-pick del commit**

```bash
git cherry-pick ae53980
```

Expected: commit aplicado sin conflictos (solo afecta package.json y lock).

- [ ] **Step 3: Instalar dependencias**

```bash
cd frontend && npm install
```

Expected: instalación sin errores.

- [ ] **Step 4: Verificar build**

```bash
npm run build
```

Expected: build exitoso.

---

### Task 2: Backend — ajustar `.env` y publicar config de CORS

**Files:**
- Modify: `backend/.env`
- Create: `backend/config/cors.php`

- [ ] **Step 1: Actualizar `backend/.env`**

Añadir o actualizar estas líneas (buscar si existen y reemplazar, si no añadir):

```env
APP_URL=http://localhost/tarotEstrella/tarotestrellas/backend/public
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173
SESSION_DOMAIN=localhost
FRONTEND_URL=http://localhost:5173
```

- [ ] **Step 2: Crear `backend/config/cors.php`**

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'webhooks/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://127.0.0.1:5173',
        'http://localhost/tarotEstrella/tarotestrellas/frontend/dist',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
```

- [ ] **Step 3: Limpiar cache de config**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/backend
C:/wamp64/bin/php/php8.2.26/php.exe artisan config:clear
```

Expected: "Configuration cache cleared!"

- [ ] **Step 4: Verificar que los tests siguen verdes**

```bash
C:/wamp64/bin/php/php8.2.26/php.exe artisan test --compact
```

Expected: 134 passed (o el número previo), 0 failures.

- [ ] **Step 5: Commit**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add backend/.env backend/config/cors.php
git commit -m "feat(backend): configurar CORS y dominios sanctum para frontend local

Publica config/cors.php con orígenes locales (Vite dev y WAMP dist) y
ajusta APP_URL/SANCTUM_STATEFUL_DOMAINS en .env para permitir las
llamadas autenticadas del frontend."
```

---

### Task 3: Frontend — archivos `.env` y `.env.example`

**Files:**
- Create: `frontend/.env.local`
- Create: `frontend/.env.example`
- Modify: `frontend/.gitignore` (verificar que `.env.local` ya esté ignorado)

- [ ] **Step 1: Crear `frontend/.env.example`**

```env
# URL base del backend Laravel (sin trailing slash)
# En WAMP local: http://localhost/tarotEstrella/tarotestrellas/backend/public/index.php/api
# En dev server puro (artisan serve): http://localhost:8000/api
VITE_API_URL=http://localhost/tarotEstrella/tarotestrellas/backend/public/index.php/api
```

- [ ] **Step 2: Crear `frontend/.env.local`**

Mismo contenido que `.env.example` (para que funcione localmente). Este archivo NO se commitea.

- [ ] **Step 3: Verificar gitignore**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
grep -E "^\.env" .gitignore || echo ".env.local
.env" >> .gitignore
```

Expected: `.env.local` aparece listado o se añade.

- [ ] **Step 4: Commit**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add frontend/.env.example frontend/.gitignore
git commit -m "feat(frontend): plantilla .env.example para VITE_API_URL

Documenta la variable VITE_API_URL usada por src/lib/api.ts y asegura
que .env.local queda ignorado por git."
```

---

## Fase 2 — Persistencia de sesión

### Task 4: Persistir `user` en localStorage y añadir `refreshUser`

**Files:**
- Modify: `frontend/src/stores/authStore.ts`

- [ ] **Step 1: Reemplazar todo el contenido de `authStore.ts`**

```ts
import { create } from 'zustand';
import { api } from '../lib/api';

export type User = {
  uuid?: string;
  email: string;
  nombre?: string | null;
  email_verified_at?: string | null;
  roles?: string[];
};

type AuthState = {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  setSession: (token: string, user: User) => void;
  clearSession: () => void;
  refreshUser: () => Promise<void>;
  isAdmin: () => boolean;
};

const TOKEN_KEY = 'tarotestrellas-token';
const USER_KEY  = 'tarotestrellas-user';

function readStoredUser(): User | null {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

export const useAuthStore = create<AuthState>((set, get) => ({
  token: localStorage.getItem(TOKEN_KEY),
  user: readStoredUser(),
  isAuthenticated: Boolean(localStorage.getItem(TOKEN_KEY)),

  setSession: (token, user) => {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    set({ token, user, isAuthenticated: true });
  },

  clearSession: () => {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    set({ token: null, user: null, isAuthenticated: false });
  },

  refreshUser: async () => {
    const token = get().token;
    if (!token) return;
    try {
      const response = await api.get<User>('/user');
      const user = response.data;
      localStorage.setItem(USER_KEY, JSON.stringify(user));
      set({ user });
    } catch {
      // si el token está caducado el interceptor 401 hará clearSession + redirect
    }
  },

  isAdmin: () => {
    const user = get().user;
    return Array.isArray(user?.roles) && user.roles.includes('admin');
  },
}));
```

- [ ] **Step 2: Verificar compilación**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
npx tsc --noEmit
```

Expected: sin errores.

---

### Task 5: Bootstrap del `refreshUser` al arrancar la app

**Files:**
- Modify: `frontend/src/main.tsx`

- [ ] **Step 1: Leer el estado actual de `main.tsx`**

Leer el archivo para ver el patrón de montaje actual.

- [ ] **Step 2: Añadir llamada a `refreshUser` antes del render**

Inmediatamente antes del `createRoot(...).render(...)`, añadir:

```ts
import { useAuthStore } from './stores/authStore';

// Rehidratar user desde el backend si hay token local (no bloquea el render)
if (useAuthStore.getState().token) {
  void useAuthStore.getState().refreshUser();
}
```

El import de `useAuthStore` debe ir junto a los otros imports del archivo. La llamada a `refreshUser` va justo antes del `createRoot`.

- [ ] **Step 3: Verificar compilación**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
npx tsc --noEmit
```

Expected: sin errores.

- [ ] **Step 4: Commit**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add frontend/src/stores/authStore.ts frontend/src/main.tsx
git commit -m "feat(frontend): persistir user en localStorage y rehidratar en init

- authStore guarda token y user en localStorage.
- Nueva función refreshUser() consulta /api/user para actualizar
  datos del usuario (incluye roles) tras un refresh de página.
- Helper isAdmin() expone la comprobación de rol para los guards.
- main.tsx dispara refreshUser() si hay token al montar la app."
```

---

## Fase 3 — Manejo de errores global

### Task 6: Interceptor de 403 y 5xx con toast

**Files:**
- Modify: `frontend/src/lib/api.ts`

- [ ] **Step 1: Actualizar el interceptor de respuesta**

Reemplazar el bloque `api.interceptors.response.use(...)` actual por:

```ts
import { useToastStore } from '../stores/toastStore';

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;

    if (status === 401) {
      useAuthStore.getState().clearSession();
      if (window.location.pathname !== '/auth/login') {
        window.location.href = '/auth/login';
      }
    } else if (status === 403) {
      useToastStore.getState().push({
        kind: 'error',
        message: 'No tienes permisos para realizar esta acción.',
      });
    } else if (status >= 500 || error.code === 'ECONNABORTED') {
      useToastStore.getState().push({
        kind: 'error',
        message: 'Error temporal del servidor. Reintenta en unos segundos.',
      });
    }

    return Promise.reject(error);
  },
);
```

Añadir el import de `useToastStore` arriba si aún no está.

- [ ] **Step 2: Verificar API de `toastStore`**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
cat src/stores/toastStore.ts
```

Si el método o nombre del campo difieren (p.ej. `push` vs `addToast`, `kind` vs `type`), ajustar el código anterior para que coincida antes de compilar. Si el store espera otra forma, alinearse al patrón existente.

- [ ] **Step 3: Verificar compilación**

```bash
npx tsc --noEmit
```

- [ ] **Step 4: Commit**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add frontend/src/lib/api.ts
git commit -m "feat(frontend): interceptor axios muestra toast en 403 y 5xx

Reusa toastStore existente para notificar al usuario cuando el backend
responde con falta de permisos o error del servidor."
```

---

## Fase 4 — Fundación admin (guard + layout + componentes)

### Task 7: `AdminOnly` guard

**Files:**
- Create: `frontend/src/components/guards/AdminOnly.tsx`

- [ ] **Step 1: Crear el componente**

```tsx
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';

export function AdminOnly({ children }: { children: React.ReactElement }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const isAdmin         = useAuthStore((s) => s.isAdmin());

  if (!isAuthenticated) return <Navigate to="/auth/login" replace />;
  if (!isAdmin)         return <Navigate to="/app" replace />;
  return children;
}
```

---

### Task 8: `AdminLayout` con sidebar

**Files:**
- Create: `frontend/src/layouts/AdminLayout.tsx`

- [ ] **Step 1: Crear el layout**

```tsx
import { NavLink, Outlet } from 'react-router-dom';

const navItems = [
  { to: '/app/admin',              label: 'Resumen',      end: true  },
  { to: '/app/admin/reembolsos',   label: 'Reembolsos',   end: false },
  { to: '/app/admin/comprobantes', label: 'Comprobantes', end: false },
  { to: '/app/admin/citas',        label: 'Citas',        end: false },
  { to: '/app/admin/settings',     label: 'Ajustes',      end: false },
];

export function AdminLayout() {
  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <h3 className="admin-sidebar-title">Administración</h3>
        <nav>
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `admin-nav-link${isActive ? ' is-active' : ''}`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      <section className="admin-content">
        <Outlet />
      </section>
    </div>
  );
}
```

- [ ] **Step 2: Añadir estilos mínimos en `style.css`**

Al final de `frontend/src/style.css`, añadir:

```css
/* ========== Admin shell ========== */
.admin-shell { display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem; padding: 1.5rem; }
.admin-sidebar { background: var(--panel-bg, rgba(20,18,40,0.6)); border-radius: 12px; padding: 1rem; height: fit-content; position: sticky; top: 1rem; }
.admin-sidebar-title { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 0.75rem; }
.admin-nav-link { display: block; padding: 0.6rem 0.8rem; color: var(--text); text-decoration: none; border-radius: 8px; margin-bottom: 0.25rem; transition: background 0.2s; }
.admin-nav-link:hover { background: rgba(255,255,255,0.05); }
.admin-nav-link.is-active { background: var(--accent, #8b5cf6); color: #fff; }
.admin-content { min-width: 0; }
@media (max-width: 860px) {
  .admin-shell { grid-template-columns: 1fr; }
  .admin-sidebar { position: static; }
}
```

---

### Task 9: Componentes compartidos admin (filtros, tabla, timeline, dialog)

**Files:**
- Create: `frontend/src/components/admin/AdminFilters.tsx`
- Create: `frontend/src/components/admin/AdminTable.tsx`
- Create: `frontend/src/components/admin/Timeline.tsx`
- Create: `frontend/src/components/ui/ConfirmDialog.tsx`

- [ ] **Step 1: Crear `AdminFilters.tsx`**

```tsx
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
```

- [ ] **Step 2: Crear `AdminTable.tsx`**

```tsx
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
```

- [ ] **Step 3: Crear `Timeline.tsx`**

```tsx
import React from 'react';

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
```

- [ ] **Step 4: Crear `ConfirmDialog.tsx`**

```tsx
type Props = {
  open:     boolean;
  title:    string;
  message:  string;
  onConfirm: () => void;
  onCancel:  () => void;
  confirmLabel?: string;
  loading?:      boolean;
};

export function ConfirmDialog({ open, title, message, onConfirm, onCancel, confirmLabel = 'Confirmar', loading }: Props) {
  if (!open) return null;

  return (
    <div className="confirm-dialog-backdrop" role="dialog" aria-modal="true">
      <div className="confirm-dialog">
        <h3>{title}</h3>
        <p>{message}</p>
        <div className="confirm-dialog-actions">
          <button type="button" className="btn-secondary" onClick={onCancel} disabled={loading}>Cancelar</button>
          <button type="button" className="btn-primary"   onClick={onConfirm} disabled={loading}>
            {loading ? 'Procesando…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 5: Añadir estilos de tabla + timeline + dialog en `style.css`**

Añadir al final de `frontend/src/style.css`:

```css
/* ========== Admin filters ========== */
.admin-filters { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; }
.admin-filters label { display: flex; flex-direction: column; font-size: 0.8rem; color: var(--text-muted); gap: 0.25rem; }
.admin-filters select, .admin-filters input[type="date"] { padding: 0.4rem 0.6rem; background: rgba(20,18,40,0.6); color: var(--text); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; }

/* ========== Admin table ========== */
.admin-table { width: 100%; border-collapse: collapse; background: rgba(20,18,40,0.4); border-radius: 12px; overflow: hidden; }
.admin-table th, .admin-table td { padding: 0.75rem 0.9rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
.admin-table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.admin-table tr.is-clickable { cursor: pointer; transition: background 0.15s; }
.admin-table tr.is-clickable:hover { background: rgba(255,255,255,0.04); }
.admin-table-empty { padding: 1rem; color: var(--text-muted); text-align: center; }

/* ========== Timeline ========== */
.timeline { list-style: none; padding-left: 0; margin: 0; }
.timeline-item { position: relative; padding-left: 1.5rem; padding-bottom: 1rem; border-left: 2px solid rgba(255,255,255,0.1); }
.timeline-item:last-child { border-left-color: transparent; }
.timeline-marker { position: absolute; left: -7px; top: 4px; width: 12px; height: 12px; background: var(--accent, #8b5cf6); border-radius: 50%; }
.timeline-body { display: flex; flex-direction: column; gap: 0.2rem; }
.timeline-body time { font-size: 0.75rem; color: var(--text-muted); }
.timeline-detail { font-size: 0.85rem; color: var(--text); margin: 0; }

/* ========== Confirm dialog ========== */
.confirm-dialog-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.confirm-dialog { background: var(--panel-bg, #1a1733); border-radius: 12px; padding: 1.5rem; max-width: 440px; width: 90%; }
.confirm-dialog h3 { margin-top: 0; }
.confirm-dialog-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1rem; }
```

- [ ] **Step 6: Verificar compilación**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
npx tsc --noEmit
```

- [ ] **Step 7: Commit**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add frontend/src/components/guards/AdminOnly.tsx \
        frontend/src/layouts/AdminLayout.tsx \
        frontend/src/components/admin/ \
        frontend/src/components/ui/ConfirmDialog.tsx \
        frontend/src/style.css
git commit -m "feat(frontend/admin): guard + layout + componentes compartidos

- AdminOnly valida autenticación y rol admin.
- AdminLayout con sidebar de navegación admin.
- AdminFilters, AdminTable, Timeline, ConfirmDialog reutilizables."
```

---

## Fase 5 — Páginas admin

### Task 10: `AdminDashboardPage` (resumen con métricas)

**Files:**
- Create: `frontend/src/pages/app/admin/AdminDashboardPage.tsx`

- [ ] **Step 1: Crear página**

```tsx
import { useQuery } from '@tanstack/react-query';
import { api } from '../../../lib/api';

type ReembolsosMetricas = {
  total: number;
  monto_total_centavos: number;
  by_status: Record<string, number>;
  by_method: Record<string, number>;
};

type ComprobantesMetricas = {
  total: number;
  by_status?: Record<string, number>;
};

function formatMoney(cents: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(cents / 100);
}

export function AdminDashboardPage() {
  const reembolsos = useQuery({
    queryKey: ['admin', 'reembolsos', 'metricas'],
    queryFn:  async () => (await api.get<{ data: ReembolsosMetricas }>('/admin/reembolsos/metricas')).data.data,
  });

  const comprobantes = useQuery({
    queryKey: ['admin', 'comprobantes', 'metricas'],
    queryFn:  async () => (await api.get<{ data: ComprobantesMetricas }>('/admin/comprobantes/metricas')).data.data,
  });

  return (
    <main className="page-content">
      <h1>Resumen administrativo</h1>
      <section className="dash-stats">
        <div className="stat-card">
          <span className="stat-value">{reembolsos.data?.total ?? '—'}</span>
          <span className="stat-label">Reembolsos totales</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">
            {reembolsos.data ? formatMoney(reembolsos.data.monto_total_centavos) : '—'}
          </span>
          <span className="stat-label">Monto reembolsado</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{reembolsos.data?.by_status?.pendiente ?? 0}</span>
          <span className="stat-label">Reembolsos pendientes</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{comprobantes.data?.total ?? '—'}</span>
          <span className="stat-label">Comprobantes totales</span>
        </div>
      </section>

      {reembolsos.data && (
        <section>
          <h2>Por estado</h2>
          <ul>
            {Object.entries(reembolsos.data.by_status).map(([k, v]) => (
              <li key={k}><strong>{k}:</strong> {v}</li>
            ))}
          </ul>
          <h2>Por método</h2>
          <ul>
            {Object.entries(reembolsos.data.by_method).map(([k, v]) => (
              <li key={k}><strong>{k}:</strong> {v}</li>
            ))}
          </ul>
        </section>
      )}
    </main>
  );
}
```

---

### Task 11: `AdminReembolsosPage` (lista + filtros + crear manual + export)

**Files:**
- Create: `frontend/src/pages/app/admin/AdminReembolsosPage.tsx`

- [ ] **Step 1: Crear página**

```tsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api } from '../../../lib/api';
import { AdminFilters, type FilterValues } from '../../../components/admin/AdminFilters';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';

type Reembolso = {
  uuid:            string;
  estado:          string;
  razon:           string;
  metodo:          string;
  monto_centavos:  number;
  moneda:          string;
  solicitado_en:   string | null;
  procesado_en:    string | null;
  cliente?:        { email: string; nombre?: string | null } | null;
};

type PaginatedReembolsos = {
  data: Reembolso[];
  meta?: { total: number; current_page: number; last_page: number };
};

const ESTADOS = [
  { value: 'pendiente',  label: 'Pendiente'  },
  { value: 'completado', label: 'Completado' },
  { value: 'fallido',    label: 'Fallido'    },
];

const METODOS = [
  { value: 'mismo_medio_pago',       label: 'Mismo medio de pago' },
  { value: 'transferencia_manual',   label: 'Transferencia manual' },
  { value: 'credito_cliente',        label: 'Crédito cliente' },
];

function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency, maximumFractionDigits: currency === 'CLP' ? 0 : 2 }).format(cents / 100);
}

export function AdminReembolsosPage() {
  const navigate     = useNavigate();
  const queryClient  = useQueryClient();
  const [filters, setFilters] = useState<FilterValues>({});
  const [showModal, setShowModal] = useState(false);

  const listQuery = useQuery({
    queryKey: ['admin', 'reembolsos', 'list', filters],
    queryFn:  async () => (await api.get<PaginatedReembolsos>('/admin/reembolsos', { params: filters })).data,
  });

  const exportMutation = useMutation({
    mutationFn: async () => {
      const res = await api.get('/admin/reembolsos/export', { params: filters, responseType: 'blob' });
      const url = URL.createObjectURL(res.data as Blob);
      const a   = document.createElement('a');
      a.href    = url;
      a.download = `reembolsos-${new Date().toISOString().slice(0,10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    },
  });

  const columns: Column<Reembolso>[] = [
    { key: 'uuid',   label: 'UUID',   render: (r) => r.uuid.slice(0, 8) + '…' },
    { key: 'estado', label: 'Estado' },
    { key: 'razon',  label: 'Razón'  },
    { key: 'metodo', label: 'Método' },
    { key: 'monto',  label: 'Monto',  render: (r) => formatMoney(r.monto_centavos, r.moneda) },
    { key: 'email',  label: 'Cliente',render: (r) => r.cliente?.email ?? '—' },
    { key: 'fecha',  label: 'Solicitado', render: (r) => r.solicitado_en ? new Date(r.solicitado_en).toLocaleDateString('es-CL') : '—' },
  ];

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <h1>Reembolsos</h1>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button className="btn-secondary" onClick={() => exportMutation.mutate()} disabled={exportMutation.isPending}>
            {exportMutation.isPending ? 'Exportando…' : 'Exportar CSV'}
          </button>
          <button className="btn-primary" onClick={() => setShowModal(true)}>Crear reembolso</button>
        </div>
      </header>

      <AdminFilters
        values={filters}
        onChange={setFilters}
        estados={ESTADOS}
        metodos={METODOS}
      />

      <AdminTable
        columns={columns}
        rows={listQuery.data?.data ?? []}
        loading={listQuery.isLoading}
        rowKey={(r) => r.uuid}
        onRowClick={(r) => navigate(`/app/admin/reembolsos/${r.uuid}`)}
      />

      {showModal && (
        <CreateReembolsoModal
          onClose={() => setShowModal(false)}
          onCreated={() => {
            setShowModal(false);
            queryClient.invalidateQueries({ queryKey: ['admin', 'reembolsos'] });
          }}
        />
      )}
    </main>
  );
}

function CreateReembolsoModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const [form, setForm] = useState({
    cita_uuid:      '',
    pago_uuid:      '',
    monto_centavos: '',
    moneda:         'CLP',
    razon:          'cancelacion_24h',
    metodo:         'mismo_medio_pago',
    nota_admin:     '',
  });
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        ...form,
        pago_uuid:      form.pago_uuid || undefined,
        monto_centavos: Number(form.monto_centavos),
      };
      return api.post('/admin/reembolsos', payload);
    },
    onSuccess: onCreated,
    onError:   (e: any) => setError(e?.response?.data?.message ?? 'No se pudo crear.'),
  });

  return (
    <div className="confirm-dialog-backdrop" role="dialog" aria-modal="true">
      <div className="confirm-dialog" style={{ maxWidth: 520 }}>
        <h3>Crear reembolso manual</h3>
        <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }} className="auth-form">
          <label>Cita UUID<input required value={form.cita_uuid} onChange={(e) => setForm({ ...form, cita_uuid: e.target.value })} /></label>
          <label>Pago UUID (opcional)<input value={form.pago_uuid} onChange={(e) => setForm({ ...form, pago_uuid: e.target.value })} /></label>
          <label>Monto (centavos)<input type="number" required min={1} value={form.monto_centavos} onChange={(e) => setForm({ ...form, monto_centavos: e.target.value })} /></label>
          <label>Moneda<input required maxLength={3} value={form.moneda} onChange={(e) => setForm({ ...form, moneda: e.target.value.toUpperCase() })} /></label>
          <label>Razón<input required value={form.razon} onChange={(e) => setForm({ ...form, razon: e.target.value })} /></label>
          <label>Método
            <select value={form.metodo} onChange={(e) => setForm({ ...form, metodo: e.target.value })}>
              <option value="mismo_medio_pago">Mismo medio de pago</option>
              <option value="transferencia_manual">Transferencia manual</option>
              <option value="credito_cliente">Crédito cliente</option>
            </select>
          </label>
          <label>Nota admin<textarea value={form.nota_admin} onChange={(e) => setForm({ ...form, nota_admin: e.target.value })} /></label>
          {error && <p className="form-error">{error}</p>}
          <div className="confirm-dialog-actions">
            <button type="button" className="btn-secondary" onClick={onClose}>Cancelar</button>
            <button type="submit" className="btn-primary" disabled={mutation.isPending}>
              {mutation.isPending ? 'Creando…' : 'Crear'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
```

---

### Task 12: `AdminReembolsoDetallePage` (detalle + timeline + acciones)

**Files:**
- Create: `frontend/src/pages/app/admin/AdminReembolsoDetallePage.tsx`

- [ ] **Step 1: Crear página**

```tsx
import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { Timeline, type TimelineEvent } from '../../../components/admin/Timeline';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Reembolso = {
  uuid:   string;
  estado: string;
  razon:  string;
  metodo: string;
  monto_centavos: number;
  moneda: string;
  solicitado_en: string | null;
  procesado_en:  string | null;
  cita?:    { uuid: string } | null;
  cliente?: { email: string; nombre?: string | null } | null;
  pago?:    { uuid: string; canal: string } | null;
  metadata?: Record<string, unknown> | null;
};

type DetalleResponse = { data: { reembolso: Reembolso; timeline: TimelineEvent[] } };

export function AdminReembolsoDetallePage() {
  const { uuid = '' } = useParams();
  const queryClient   = useQueryClient();
  const [action, setAction]   = useState<'procesar' | 'reintentar' | 'marcar_completado' | null>(null);
  const [referencia, setReferencia] = useState('');
  const [nota, setNota] = useState('');

  const query = useQuery({
    queryKey: ['admin', 'reembolsos', 'detalle', uuid],
    queryFn:  async () => (await api.get<DetalleResponse>(`/admin/reembolsos/${uuid}`)).data.data,
    enabled:  !!uuid,
  });

  const procesar = useMutation({
    mutationFn: async () => {
      const payload: Record<string, unknown> = { accion: action, nota_admin: nota };
      if (action === 'marcar_completado') payload.referencia_manual = referencia;
      return api.post(`/admin/reembolsos/${uuid}/procesar`, payload);
    },
    onSuccess: () => {
      setAction(null);
      setReferencia('');
      setNota('');
      queryClient.invalidateQueries({ queryKey: ['admin', 'reembolsos'] });
    },
  });

  if (query.isLoading) return <main className="page-content"><p>Cargando…</p></main>;
  if (!query.data)     return <main className="page-content"><p>No encontrado.</p></main>;

  const { reembolso, timeline } = query.data;

  return (
    <main className="page-content">
      <Link to="/app/admin/reembolsos">← Volver</Link>
      <h1>Reembolso {reembolso.uuid.slice(0, 8)}…</h1>

      <section className="dash-stats">
        <div className="stat-card"><span className="stat-value">{reembolso.estado}</span><span className="stat-label">Estado</span></div>
        <div className="stat-card"><span className="stat-value">{reembolso.razon}</span><span className="stat-label">Razón</span></div>
        <div className="stat-card"><span className="stat-value">{reembolso.metodo}</span><span className="stat-label">Método</span></div>
        <div className="stat-card"><span className="stat-value">{(reembolso.monto_centavos / 100).toFixed(2)} {reembolso.moneda}</span><span className="stat-label">Monto</span></div>
      </section>

      <section>
        <h2>Acciones</h2>
        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          {reembolso.estado === 'pendiente' && (
            <button className="btn-primary" onClick={() => setAction('procesar')}>Procesar</button>
          )}
          {reembolso.estado === 'fallido' && (
            <button className="btn-secondary" onClick={() => setAction('reintentar')}>Reintentar</button>
          )}
          {reembolso.estado !== 'completado' && (
            <button className="btn-secondary" onClick={() => setAction('marcar_completado')}>Marcar completado</button>
          )}
        </div>
      </section>

      <section>
        <h2>Timeline</h2>
        <Timeline events={timeline} />
      </section>

      {action === 'marcar_completado' && (
        <div className="confirm-dialog-backdrop">
          <div className="confirm-dialog">
            <h3>Marcar como completado</h3>
            <form className="auth-form" onSubmit={(e) => { e.preventDefault(); procesar.mutate(); }}>
              <label>Referencia manual<input required value={referencia} onChange={(e) => setReferencia(e.target.value)} /></label>
              <label>Nota admin<textarea value={nota} onChange={(e) => setNota(e.target.value)} /></label>
              <div className="confirm-dialog-actions">
                <button type="button" className="btn-secondary" onClick={() => setAction(null)}>Cancelar</button>
                <button type="submit" className="btn-primary" disabled={procesar.isPending}>
                  {procesar.isPending ? 'Procesando…' : 'Confirmar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={action === 'procesar' || action === 'reintentar'}
        title={action === 'procesar' ? 'Procesar reembolso' : 'Reintentar reembolso'}
        message={action === 'procesar'
          ? '¿Encolar el job de procesamiento Stripe?'
          : '¿Mover a pendiente y reencolar el job Stripe?'}
        loading={procesar.isPending}
        onConfirm={() => procesar.mutate()}
        onCancel={()  => setAction(null)}
      />
    </main>
  );
}
```

---

### Task 13: `AdminComprobantesPage`

**Files:**
- Create: `frontend/src/pages/app/admin/AdminComprobantesPage.tsx`

- [ ] **Step 1: Crear página**

```tsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Comprobante = {
  id:          number;
  uuid:        string;
  estado:      string;
  monto_centavos?: number;
  moneda?:     string;
  creado_en?:  string;
  cliente_email?: string | null;
};

type PaginatedComprobantes = {
  data: Comprobante[];
  meta?: { total: number };
};

export function AdminComprobantesPage() {
  const queryClient = useQueryClient();
  const [pending, setPending] = useState<{ id: number; action: 'aprobar' | 'rechazar' } | null>(null);

  const listQuery = useQuery({
    queryKey: ['admin', 'comprobantes', 'list'],
    queryFn:  async () => (await api.get<PaginatedComprobantes>('/pagos/comprobantes')).data,
  });

  const mutate = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'aprobar' | 'rechazar' }) => {
      return api.post(`/admin/comprobantes/${id}/${action}`);
    },
    onSuccess: () => {
      setPending(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'comprobantes'] });
    },
  });

  const columns: Column<Comprobante>[] = [
    { key: 'uuid',   label: 'UUID',   render: (r) => r.uuid?.slice(0, 8) + '…' },
    { key: 'estado', label: 'Estado' },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente_email ?? '—' },
    { key: 'creado', label: 'Creado', render: (r) => r.creado_en ? new Date(r.creado_en).toLocaleDateString('es-CL') : '—' },
    { key: 'acciones', label: 'Acciones', render: (r) => (
      <>
        <button className="btn-primary"   style={{ marginRight: '.25rem' }} onClick={(e) => { e.stopPropagation(); setPending({ id: r.id, action: 'aprobar'  }); }}>Aprobar</button>
        <button className="btn-secondary"                                      onClick={(e) => { e.stopPropagation(); setPending({ id: r.id, action: 'rechazar' }); }}>Rechazar</button>
      </>
    ) },
  ];

  return (
    <main className="page-content">
      <h1>Comprobantes de transferencia</h1>

      <AdminTable
        columns={columns}
        rows={listQuery.data?.data ?? []}
        loading={listQuery.isLoading}
        rowKey={(r) => r.id}
      />

      <ConfirmDialog
        open={pending !== null}
        title={pending?.action === 'aprobar' ? 'Aprobar comprobante' : 'Rechazar comprobante'}
        message={`¿Confirmas ${pending?.action} el comprobante?`}
        loading={mutate.isPending}
        onConfirm={() => pending && mutate.mutate(pending)}
        onCancel={()  => setPending(null)}
      />
    </main>
  );
}
```

---

### Task 14: `AdminCitasPage` (historial + no-show)

**Files:**
- Create: `frontend/src/pages/app/admin/AdminCitasPage.tsx`

- [ ] **Step 1: Crear página**

```tsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminFilters, type FilterValues } from '../../../components/admin/AdminFilters';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';

type Cita = {
  uuid:           string;
  estado:         string;
  inicio_utc:     string;
  cliente_email?: string | null;
  tipo_consulta?: { nombre: string } | null;
};

type Historial = { data: Cita[]; meta?: { total: number } };

const ESTADOS = [
  { value: 'reservada',   label: 'Reservada'   },
  { value: 'confirmada',  label: 'Confirmada'  },
  { value: 'completada',  label: 'Completada'  },
  { value: 'cancelada',   label: 'Cancelada'   },
  { value: 'cancelada_chachita', label: 'Cancelada especialista' },
];

export function AdminCitasPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<FilterValues>({});
  const [targetUuid, setTargetUuid] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ['admin', 'citas', 'historial', filters],
    queryFn:  async () => (await api.get<Historial>('/admin/citas/historial', { params: filters })).data,
  });

  const noShow = useMutation({
    mutationFn: (uuid: string) => api.post(`/admin/citas/${uuid}/no-show`),
    onSuccess: () => {
      setTargetUuid(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'citas'] });
    },
  });

  const exportCsv = async () => {
    const res = await api.get('/admin/citas/historial/export', { params: filters, responseType: 'blob' });
    const url = URL.createObjectURL(res.data as Blob);
    const a   = document.createElement('a');
    a.href    = url;
    a.download = `citas-historial-${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const columns: Column<Cita>[] = [
    { key: 'uuid',    label: 'UUID',    render: (r) => r.uuid.slice(0, 8) + '…' },
    { key: 'tipo',    label: 'Tipo',    render: (r) => r.tipo_consulta?.nombre ?? '—' },
    { key: 'estado',  label: 'Estado' },
    { key: 'inicio',  label: 'Inicio',  render: (r) => new Date(r.inicio_utc).toLocaleString('es-CL') },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente_email ?? '—' },
    { key: 'accion',  label: '',        render: (r) => (
      r.estado === 'reservada' || r.estado === 'confirmada'
        ? <button className="btn-secondary" onClick={(e) => { e.stopPropagation(); setTargetUuid(r.uuid); }}>Marcar no-show</button>
        : null
    ) },
  ];

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <h1>Historial de citas</h1>
        <button className="btn-secondary" onClick={exportCsv}>Exportar CSV</button>
      </header>

      <AdminFilters values={filters} onChange={setFilters} estados={ESTADOS} />

      <AdminTable
        columns={columns}
        rows={query.data?.data ?? []}
        loading={query.isLoading}
        rowKey={(r) => r.uuid}
      />

      <ConfirmDialog
        open={!!targetUuid}
        title="Marcar no-show"
        message="Esto cancelará la cita y generará reembolsos pendientes por los pagos completados. ¿Continuar?"
        loading={noShow.isPending}
        onConfirm={() => targetUuid && noShow.mutate(targetUuid)}
        onCancel={()  => setTargetUuid(null)}
      />
    </main>
  );
}
```

---

### Task 15: `AdminSettingsPage` (validación transferencias)

**Files:**
- Create: `frontend/src/pages/app/admin/AdminSettingsPage.tsx`

- [ ] **Step 1: Crear página**

```tsx
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';

type Settings = {
  auto_approval_enabled: boolean;
  threshold_centavos?:   number | null;
  updated_at?:           string;
};

export function AdminSettingsPage() {
  const queryClient = useQueryClient();
  const [local, setLocal] = useState<Settings | null>(null);

  const query = useQuery({
    queryKey: ['admin', 'settings', 'transfer-validation'],
    queryFn:  async () => (await api.get<{ data: Settings }>('/admin/settings/transfer-validation')).data.data,
  });

  useEffect(() => {
    if (query.data && local === null) setLocal(query.data);
  }, [query.data, local]);

  const save = useMutation({
    mutationFn: (payload: Settings) => api.patch('/admin/settings/transfer-validation', payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] }),
  });

  if (query.isLoading || !local) return <main className="page-content"><p>Cargando…</p></main>;

  return (
    <main className="page-content">
      <h1>Ajustes — Validación de transferencias</h1>
      <form className="auth-form" onSubmit={(e) => { e.preventDefault(); save.mutate(local); }}>
        <label style={{ flexDirection: 'row', gap: '0.5rem', alignItems: 'center' }}>
          <input
            type="checkbox"
            checked={local.auto_approval_enabled}
            onChange={(e) => setLocal({ ...local, auto_approval_enabled: e.target.checked })}
          />
          Aprobación automática habilitada
        </label>
        <label>
          Umbral (centavos, opcional)
          <input
            type="number"
            min={0}
            value={local.threshold_centavos ?? ''}
            onChange={(e) => setLocal({ ...local, threshold_centavos: e.target.value ? Number(e.target.value) : null })}
          />
        </label>
        <button className="btn-primary" type="submit" disabled={save.isPending}>
          {save.isPending ? 'Guardando…' : 'Guardar'}
        </button>
      </form>
    </main>
  );
}
```

---

### Task 16: Registrar rutas admin en `App.tsx`

**Files:**
- Modify: `frontend/src/App.tsx`

- [ ] **Step 1: Añadir imports arriba**

Después del import de `MembresiaPage`, añadir:

```tsx
import { AdminOnly } from './components/guards/AdminOnly';
import { AdminLayout } from './layouts/AdminLayout';
```

- [ ] **Step 2: Añadir lazy imports de páginas admin**

Junto a los otros `lazy(() => import(...))`:

```tsx
const AdminDashboardPage        = lazy(() => import('./pages/app/admin/AdminDashboardPage').then((m) => ({ default: m.AdminDashboardPage })));
const AdminReembolsosPage       = lazy(() => import('./pages/app/admin/AdminReembolsosPage').then((m) => ({ default: m.AdminReembolsosPage })));
const AdminReembolsoDetallePage = lazy(() => import('./pages/app/admin/AdminReembolsoDetallePage').then((m) => ({ default: m.AdminReembolsoDetallePage })));
const AdminComprobantesPage     = lazy(() => import('./pages/app/admin/AdminComprobantesPage').then((m) => ({ default: m.AdminComprobantesPage })));
const AdminCitasPage            = lazy(() => import('./pages/app/admin/AdminCitasPage').then((m) => ({ default: m.AdminCitasPage })));
const AdminSettingsPage         = lazy(() => import('./pages/app/admin/AdminSettingsPage').then((m) => ({ default: m.AdminSettingsPage })));
```

- [ ] **Step 3: Registrar rutas dentro del bloque `/app`**

Después de `<Route path="mi-cuenta" element={<MiCuentaPage />} />` y antes del cierre `</Route>` de `/app`, añadir:

```tsx
<Route
  path="admin"
  element={
    <AdminOnly>
      <AdminLayout />
    </AdminOnly>
  }
>
  <Route index                       element={<Suspense fallback={<PageLoader />}><AdminDashboardPage        /></Suspense>} />
  <Route path="reembolsos"           element={<Suspense fallback={<PageLoader />}><AdminReembolsosPage       /></Suspense>} />
  <Route path="reembolsos/:uuid"     element={<Suspense fallback={<PageLoader />}><AdminReembolsoDetallePage /></Suspense>} />
  <Route path="comprobantes"         element={<Suspense fallback={<PageLoader />}><AdminComprobantesPage     /></Suspense>} />
  <Route path="citas"                element={<Suspense fallback={<PageLoader />}><AdminCitasPage            /></Suspense>} />
  <Route path="settings"             element={<Suspense fallback={<PageLoader />}><AdminSettingsPage         /></Suspense>} />
</Route>
```

- [ ] **Step 4: Añadir enlace "Admin" visible solo para admin en `AppLayout`**

Leer `frontend/src/layouts/AppLayout.tsx` y localizar el bloque de navegación. Añadir un `NavLink` condicional:

```tsx
{useAuthStore((s) => s.isAdmin()) && (
  <NavLink to="/app/admin" className="nav-link">Administración</NavLink>
)}
```

Ajustar el import si `useAuthStore` no está ya importado en el archivo.

- [ ] **Step 5: Verificar compilación**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
npx tsc --noEmit && npm run build
```

- [ ] **Step 6: Commit de toda la fase admin**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add frontend/src/pages/app/admin/ \
        frontend/src/App.tsx \
        frontend/src/layouts/AppLayout.tsx
git commit -m "feat(frontend/admin): seis páginas admin conectadas al backend

Dashboard (métricas), Reembolsos (lista + crear + export + detalle/
timeline/acciones), Comprobantes (aprobar/rechazar), Citas (historial
+ no-show) y Settings (validación transferencias). Rutas protegidas
por AdminOnly y link en la navegación principal solo visible a admin."
```

---

## Fase 6 — Verificación de contratos

### Task 17: Pase de verificación endpoint por endpoint

**Files a revisar:**
- `frontend/src/components/forms/LoginForm.tsx`
- `frontend/src/components/forms/RegisterForm.tsx`
- `frontend/src/components/forms/ForgotPasswordForm.tsx`
- `frontend/src/pages/app/DashboardPage.tsx`
- `frontend/src/pages/app/MisConsultasPage.tsx`
- `frontend/src/pages/app/DetalleCitaPage.tsx`
- `frontend/src/pages/app/PagarCitaPage.tsx`
- `frontend/src/pages/app/PagarSaldoPage.tsx`
- `frontend/src/pages/app/SalaVideoPage.tsx`
- `frontend/src/pages/app/MiCuentaPage.tsx`
- `frontend/src/pages/app/MembresiaPage.tsx`
- `frontend/src/pages/public/ServiciosPage.tsx`
- `frontend/src/pages/public/ServicioDetallePage.tsx`

Fuente de verdad: `backend/routes/api.php`.

- [ ] **Step 1: Revisar cada archivo y listar llamadas**

Para cada archivo, identificar todas las llamadas `api.get/post/patch/delete` y comparar con las rutas reales. Buscar:
- URL correcta (path exacto)
- Método HTTP correcto (GET/POST/PATCH)
- Nombres de query params coinciden
- Payload del body coincide con las reglas de validación del controller

- [ ] **Step 2: Corregir mismatches encontrados**

Arreglar inline cada mismatch. Para cada corrección:

```bash
# tras cada fix
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git add frontend/src
git commit -m "fix(frontend): alinear URLs y payloads a contratos backend

Pase de verificación sistemática de cada llamada API contra routes/api.php."
```

Si no se encontraron mismatches, omitir este commit y añadir esa nota al checklist final.

---

## Fase 7 — Validación manual

### Task 18: Validación end-to-end con servidor corriendo

- [ ] **Step 1: Iniciar backend**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/backend
C:/wamp64/bin/php/php8.2.26/php.exe artisan serve --host=0.0.0.0 --port=8000
```

(O asegurar que WAMP está activo sirviendo `backend/public`.)

- [ ] **Step 2: Iniciar frontend dev server en otra terminal**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
npm run dev
```

- [ ] **Step 3: Checklist de flujos en navegador**

En `http://localhost:5173`:

1. Registrarse con un usuario nuevo → verifica email → login → llega al dashboard.
2. Refresh del dashboard (F5) → sigue logueado, nombre/email visibles.
3. Ir a servicios → seleccionar uno → ver disponibilidad.
4. Agendar cita → ir a pago → crear PaymentIntent Stripe (sin completar pago en modo real).
5. Logout → login como admin (crear en DB si no existe): `roles=['admin']`.
6. Aparece link "Administración" → entra a `/app/admin`.
7. Dashboard admin muestra métricas (aunque sea 0).
8. Reembolsos → filtrar por estado → crear uno manual → ver aparecer en lista.
9. Click en reembolso → detalle muestra timeline.
10. Comprobantes → si hay uno pendiente, aprobar.
11. Citas admin → filtrar → si hay reservada, marcar no-show y ver que aparece reembolso nuevo.
12. Settings → cambiar toggle → guardar → refrescar → persiste.

- [ ] **Step 4: Marcar cada ítem en una nota o comentario al commit final**

---

### Task 19: Build final y verificación backend

- [ ] **Step 1: Build de frontend**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/frontend
npm run build
```

Expected: build sin errores.

- [ ] **Step 2: Tests de backend**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas/backend
C:/wamp64/bin/php/php8.2.26/php.exe artisan test --compact
```

Expected: 134+ passed, 0 failures.

- [ ] **Step 3: Commit final si hay archivos pendientes**

```bash
cd /c/wamp64/www/TarotEstrella/tarotestrellas
git status
```

Si hay archivos sueltos (probablemente no), añadir y commit con mensaje de cierre:

```bash
git add .
git commit -m "chore: cierre sprint integración full-stack"
```

- [ ] **Step 4: Listo para PR**

Recordar al usuario: abrir PR de `backend/feat/backend-avance-fase` → `main` desde GitHub, adjuntando el link al spec y al plan.

---

## Resumen de archivos del plan

**Creados:**
- `backend/config/cors.php`
- `frontend/.env.example`
- `frontend/.env.local` (no commit)
- `frontend/src/components/guards/AdminOnly.tsx`
- `frontend/src/layouts/AdminLayout.tsx`
- `frontend/src/components/admin/AdminFilters.tsx`
- `frontend/src/components/admin/AdminTable.tsx`
- `frontend/src/components/admin/Timeline.tsx`
- `frontend/src/components/ui/ConfirmDialog.tsx`
- `frontend/src/pages/app/admin/AdminDashboardPage.tsx`
- `frontend/src/pages/app/admin/AdminReembolsosPage.tsx`
- `frontend/src/pages/app/admin/AdminReembolsoDetallePage.tsx`
- `frontend/src/pages/app/admin/AdminComprobantesPage.tsx`
- `frontend/src/pages/app/admin/AdminCitasPage.tsx`
- `frontend/src/pages/app/admin/AdminSettingsPage.tsx`

**Modificados:**
- `backend/.env`
- `frontend/.gitignore`
- `frontend/src/stores/authStore.ts`
- `frontend/src/main.tsx`
- `frontend/src/lib/api.ts`
- `frontend/src/App.tsx`
- `frontend/src/layouts/AppLayout.tsx`
- `frontend/src/style.css`
- Posibles ajustes inline tras Task 17 en archivos bajo `frontend/src/`.

**Commits proyectados:** ~8-10.
