# Diseño de integración Full-Stack — TarotEstrellas

Fecha: 2026-04-24
Rama de trabajo: `backend/feat/backend-avance-fase` (se convierte en rama de integración)
Estado previo: backend completo por GitHub Copilot + frontend completo por Claude (Sprints 1-8)

## 1. Objetivo

Unificar frontend (React + Vite + TS) y backend (Laravel + Sanctum) en una única rama funcional, corrigiendo las brechas de configuración y añadiendo las páginas de administración que consumen los endpoints admin ya existentes.

## 2. Alcance (este sprint)

Incluye:
1. Cherry-pick del commit de dependencias faltante.
2. Configuración CORS en backend.
3. Configuración de entorno en frontend (`.env`).
4. Persistencia de usuario en refresh.
5. Guardia + layout admin.
6. Seis páginas admin conectadas a la API.
7. Verificación y corrección de contratos endpoint por endpoint.
8. Manejo de errores centralizado.

No incluye:
- Refactor del frontend existente (se toca solo lo roto).
- Tests automatizados nuevos del frontend.
- Cambios de backend (ya está validado con 134 tests).

## 3. Arquitectura

```
[Navegador]
   │
   ▼
[Frontend React/Vite] ── axios (Bearer token) ──► [Backend Laravel/Sanctum]
   │                                                │
   ├─ Zustand authStore (token + user persist)      ├─ api.php routes
   ├─ TanStack Query (cache + mutations)            ├─ ReembolsoController (admin)
   ├─ Rutas públicas / auth / app / app/admin       ├─ Jobs Stripe async
   └─ AdminOnly guard (roles.includes('admin'))     └─ CORS habilitado
```

## 4. Git — flujo de integración

### 4.1 Cherry-pick del commit faltante
- Commit: `ae53980` (chore deps sprints 4-8) en `frontend/design-avance`
- Destino: `backend/feat/backend-avance-fase`
- Comando: `git cherry-pick ae53980`

### 4.2 Identidad de la rama
La rama actual queda como rama de integración. Al final del sprint se abre PR de `backend/feat/backend-avance-fase` → `main`.

## 5. Configuración

### 5.1 CORS backend
Publicar/crear `backend/config/cors.php`:
- `paths`: `['api/*', 'sanctum/csrf-cookie']`
- `allowed_methods`: `['*']`
- `allowed_origins`: incluir orígenes locales y WAMP
- `supports_credentials`: `true`

### 5.2 Backend `.env`
- `APP_URL=http://localhost/tarotEstrella/tarotestrellas/backend/public`
- `SANCTUM_STATEFUL_DOMAINS=localhost`

### 5.3 Frontend `.env.local`
- `VITE_API_URL=http://localhost/tarotEstrella/tarotestrellas/backend/public/index.php/api`
- Crear también `frontend/.env.example` con la misma variable vacía.

## 6. Persistencia de usuario

### 6.1 Problema
`authStore` lee `token` de `localStorage` al iniciar, pero `user` queda `null` → al refrescar, se pierde `user.roles`, `user.nombre`, etc.

### 6.2 Solución
1. Guardar también el objeto `user` en `localStorage` (`tarotestrellas-user`) dentro de `setSession` y limpiarlo en `clearSession`.
2. Hidratar el store al inicio leyendo ambos.
3. Añadir función `refreshUser()` que llama `GET /api/user` y actualiza el store.
4. En `main.tsx`, si hay token, disparar `refreshUser()` en background (no bloquea el render, corrige stale data).

## 7. Admin frontend

### 7.1 Guardia y layout
- `src/components/guards/AdminOnly.tsx`: si `user?.roles?.includes('admin')` es falso → `<Navigate to="/app" />`.
- `src/layouts/AdminLayout.tsx`: wrapper sobre `AppLayout` con sidebar que linkea Reembolsos, Comprobantes, Citas, Settings.

### 7.2 Ruteo
En `App.tsx`, dentro de `/app`:
```
/app/admin                        → AdminDashboardPage
/app/admin/reembolsos             → AdminReembolsosPage
/app/admin/reembolsos/:uuid       → AdminReembolsoDetallePage
/app/admin/comprobantes           → AdminComprobantesPage
/app/admin/citas                  → AdminCitasPage
/app/admin/settings               → AdminSettingsPage
```

Todas protegidas por `AdminOnly`. Las páginas pesadas (listas con gráficos) se cargan con `lazy()`.

### 7.3 Páginas

| Página | Endpoints | Componentes clave |
|---|---|---|
| `AdminDashboardPage` | `/admin/reembolsos/metricas`, `/admin/comprobantes/metricas` | KPI cards, resumen `by_status` / `by_method` |
| `AdminReembolsosPage` | `/admin/reembolsos`, `/admin/reembolsos/export`, `POST /admin/reembolsos` | `AdminFilters`, `AdminTable`, modal creación manual |
| `AdminReembolsoDetallePage` | `/admin/reembolsos/{uuid}`, `POST .../procesar` | `Timeline`, botones procesar / reintentar / marcar_completado |
| `AdminComprobantesPage` | `/pagos/comprobantes`, `POST /admin/comprobantes/{id}/aprobar\|rechazar`, `PATCH .../validacion-manual` | Tabla con acciones, preview del comprobante |
| `AdminCitasPage` | `/admin/citas/historial`, `POST /admin/citas/{uuid}/no-show`, `/admin/citas/historial/export` | Tabla con filtros, `ConfirmDialog` para no-show |
| `AdminSettingsPage` | `/admin/settings/transfer-validation` (GET/PATCH), `.../audits` | Toggle, tabla de audits con revert |

### 7.4 Componentes nuevos compartidos
- `src/components/admin/AdminFilters.tsx`: select estado, método, razón, rango de fechas.
- `src/components/admin/AdminTable.tsx`: tabla con paginación TanStack Query-friendly.
- `src/components/admin/Timeline.tsx`: línea de eventos verticales (creado, reintento, procesado_stripe, etc.).
- `src/components/ui/ConfirmDialog.tsx`: modal de confirmación reutilizable.

### 7.5 Patrón de datos
- `useQuery` para fetches GET (con `queryKey` por recurso + filtros).
- `useMutation` para POST/PATCH, con `onSuccess` → `queryClient.invalidateQueries([...])`.
- Todos los endpoints admin pasan por `api` de `lib/api.ts` (ya añade Bearer token).

## 8. Verificación de contratos

Pase rápido sobre páginas existentes contra `routes/api.php`. Si hay mismatch, se corrige inline. Páginas a revisar:

- `LoginForm`, `RegisterForm`, `ForgotPasswordForm` → `/auth/*`
- `DashboardPage`, `MisConsultasPage` → `/citas`
- `DetalleCitaPage` → `/citas/{uuid}`, `/citas/{uuid}/historial`, `/me/consultas/{uuid}/*`
- `PagarCitaPage` → `/citas/{uuid}/pagar/stripe`, `/citas/{uuid}/pagar/transferencia/*`
- `PagarSaldoPage` → `/pagos/abono`
- `SalaVideoPage` → `/citas/{uuid}/sala`, `/citas/{uuid}/sala/entrada`
- `MiCuentaPage` → `/me/datos-personales`, `/me/consultas`, `/me/export`
- `MembresiaPage` → revisar si usa endpoint público o autenticado
- `ServiciosPage`, `ServicioDetallePage` → `/public/tipos-consulta*`

Resultado esperado: zero mismatches sin corregir.

## 9. Manejo de errores

Centralizado en `lib/api.ts`:
- 401 → `clearSession()` + redirect a `/auth/login` (ya implementado).
- 403 → toast "Sin permisos suficientes".
- 422 → no se toca globalmente; las páginas extraen `errors.{campo}[0]`.
- 5xx / timeout → toast "Error temporal, reintenta en unos segundos".

Se usa el `toastStore` existente.

## 10. Validación manual

Checklist al cerrar el sprint:
1. `npm run dev` (frontend) + servidor Apache/WAMP del backend.
2. Login con usuario normal → refresh F5 → sigue logueado.
3. Login con admin → aparece menú admin → navega a Reembolsos → lista carga.
4. Crear reembolso manual → aparece en lista.
5. Procesar reembolso pendiente → cambia estado tras el job.
6. Aprobar un comprobante → estado cambia.
7. Marcar no-show de una cita → se genera reembolso automático.
8. `npm run build` sin errores de tipado.
9. `php artisan test --compact` sigue en verde.

## 11. Criterios de éxito

1. Frontend puede hacer login, navegar todas sus páginas y consumir el backend sin errores CORS.
2. Usuario mantiene sesión tras F5.
3. Usuario admin ve y opera las 6 pantallas admin.
4. Endpoints existentes en frontend coinciden con el backend (verificados).
5. PR limpio de `backend/feat/backend-avance-fase` → `main` listo para revisión.

## 12. Fuera de alcance (futuro)

- Admin de usuarios / roles.
- Dashboard admin con gráficos temporales (solo KPIs numéricos este sprint).
- Tests automatizados frontend (Vitest ya configurado, pero no se añaden nuevos aquí).
- Panel especialista (otro rol diferente de admin y cliente).
- PWA / offline.
