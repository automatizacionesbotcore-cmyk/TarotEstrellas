# 📋 Handoff TarotEstrellas — Estado Actual

**Stack**: Laravel 11 (PHP 8.3) + React 18 + Vite + TanStack Query + MySQL 8 · Hostinger
**Repo**: `automatizacionesbotcore-cmyk/TarotEstrellas`
**PROD**: https://tarotestrellas.com (accesos Hostinger/SSH fuera de Git; usar gestor seguro del equipo)
**Spec**: `tarotestrellas/docs/ESPECIFICACION_TECNICATarotEstrellasV2.md` (305 KB)

---

## 🌿 Estrategia de ramas (Git Flow simplificado)

> **Corte base (2026-05-13)**: las 3 ramas principales se crearon al mismo commit `c352a3c`. A partir de aquí divergen según el propósito de cada una.

| Rama | Propósito | Reglas |
|---|---|---|
| **`main`** | Espejo de PROD — lo que está vivo en tarotestrellas.com | Solo se actualiza con merges validados desde `dev`. Nunca se hace commit directo aquí. Siempre debe estar desplegable |
| **`dev`** | Integración / staging — donde trabaja Codex/Claude | Recibe merges de feature branches. Se testea aquí antes de subir a `main`. Puede estar "un sprint adelante" de PROD |
| **`feat/*`** | Feature branches individuales | Se crean desde `dev`, se mergean de vuelta a `dev` cuando la feature está completa y probada |

### Flujo recomendado

```
feat/mi-feature  ──┐
                   ├──► dev ──► (QA + smoke test) ──► main ──► deploy PROD
feat/otra-cosa   ──┘
```

### Comandos útiles

```bash
# Iniciar nueva feature
git checkout dev
git pull origin dev
git checkout -b feat/nombre-feature

# Mergear feature a dev (sin fast-forward para conservar historial)
git checkout dev
git merge --no-ff feat/nombre-feature

# Llevar dev a main (solo cuando validado en PROD staging)
git checkout main
git merge --no-ff dev
git push origin main

# Crear rama feature desde dev en remoto (para Codex/Claude)
git push origin feat/nombre-feature
```

> ⚠️ Nunca hacer `git push --force` sobre `main` salvo emergencia y con consenso del equipo.

---

---

## ✅ Completado (27/38 tareas iniciales)

### Núcleo funcional

- **Auth**: Sanctum + Google OAuth · roles `super_admin` / `admin_especialista` / `cliente` · reset password con email branded en español (`ResetPasswordNotification`)
- **Catálogo + Agenda**: especialistas, tipos de consulta, slots, reserva con códigos, estados `pendiente_abono → reservada → confirmada → realizada`
- **Pagos**: PayPal LIVE-ready (sandbox local) + Transferencia bancaria con flujo comprobante (subida cliente → notif admin → aprobar/rechazar con emails). Flow.cl **comentado** para Fase 2. Abono mínimo 20% reflejado en UI con saldo 80% explicado
- **Reembolsos**: Stripe queda legacy. PayPal ya tiene job automatico contra capturas (`ProcesarReembolsoPaypalJob`) usando `/v2/payments/captures/{capture_id}/refund`; se enruta desde scheduler/admin segun `pago.canal`
- **Cuentas bancarias**: CRUD admin (max 3 por especialista), expuestas al pagar
- **Sala video**: Daily.co integrado (rooms, recording, transcription webhooks)
- **Resúmenes IA**: post-sesión con OpenAI/Anthropic
- **Agente IA "Astrea"**: chatbot público + privado + admin con historial. Markdown rendering (`**` → negrita, `*` → cursiva, `` ` `` → code). Backend con `AgenteIAService` (Anthropic), tabla `agente_conversaciones`, throttle 30/min, métricas de tokens
- **Notificaciones**: emails Resend (verificación, reset, comprobantes aprobado/rechazado, nuevo comprobante recibido para admin)
- **Admin completo**: clientes (con eliminación cascada para super_admin), especialistas, citas, comprobantes, cuentas bancarias, asistente IA, métricas, API usage, app settings
- **Responsive**: drawer móvil ≤860px en admin, tablas/filtros/cards adaptativos, inputs ≥16px (anti-zoom iOS)
- **Documentación**: `QA-CHECKLIST.md` (9 secciones), `DEPLOY_HOSTINGER.md`, `DAILY_INTEGRATION.md`

### Deploy realizado

- ✅ Frontend Vite buildeado y servido desde `public_html/`
- ✅ Backend Laravel en `public_html/backend/` con migrations y seeders de roles/super_admin. El email real del super admin debe consultarse fuera de Git.
- ✅ SSL activo, OAuth Google funcional, Resend dominio verificado
- ✅ Caches recompilados: `optimize:clear`, `config:cache`, `route:cache`
- ✅ Deploy PROD 2026-05-14: respaldo manual remoto creado antes de subir, frontend reconstruido con `VITE_API_URL=https://tarotestrellas.com/backend/public/api`, backend actualizado con API usage/reembolsos PayPal, migraciones sin pendientes, caches recompilados y smoke `health`, `/`, `/servicios`, catálogo público OK.

---

## ⏳ Pendiente / estado operativo

| ID | Tarea | Notas |
|---|---|---|
| **cfg-paypal-prod** | Configurar PayPal **LIVE** en `.env` PROD | Requiere credenciales LIVE externas. Reembolsos automaticos PayPal ya implementados; checklist en `docs/OPERATIONS_RUNBOOK.md` |
| **cfg-09** | WhatsApp Meta API | Webhook `/api/webhooks/whatsapp` existe y tiene tests; faltan app/secret/plantillas aprobadas |
| **cfg-14** | Límites API | Implementado en backend/frontend: `/app/admin/api-usage`, job horario, emails e in-app alerts. Ultimo ajuste: serializacion `usado` para frontend y limpieza de emails extra |
| **cfg-15** | Smoke E2E completo | Seguir `docs/QA-CHECKLIST.md` end-to-end; comandos en `docs/OPERATIONS_RUNBOOK.md` |
| **cfg-05** | Queue worker Supervisor | Codigo usa queue database. Falta activar worker persistente o fallback cron en PROD; runbook agregado |
| **cfg-06** | Cron scheduler | Scheduler definido en `backend/routes/console.php`; falta activar cron `php artisan schedule:run` en PROD |
| cfg-01..04, cfg-12 | Items de infra ya cubiertos parcialmente por deploy actual; cerrar formalmente |

---

## 🐛 Bugs recientes resueltos

1. **Pantalla en blanco PROD**: `index.php` vacío creado por `touch` errático que precedía a `index.html`. Solución: NO usar `touch index.php` en `public_html/` raíz
2. **Reset password en inglés con link roto / emails inconsistentes**: creado `ResetPasswordNotification` custom + `APP_LOCALE=es` + `frontend_url` en config. Al 2026-05-14 todos los emails transaccionales backend usan layout corporativo común con logo, colores, tipografía, detalle alineado y CTAs.
3. **Astrea mostraba `**` literales en widget flotante**: `AgenteWidget.tsx` no aplicaba `renderMarkdown` (solo `AgenteChat.tsx` sí). Corregido
4. **UI pago mostraba 100% donde decía "Abono 20%"**: ahora calcula `Math.round(precio_final * 0.20)` y muestra Saldo restante

---

## 🔑 Decisiones técnicas clave

- `precio_final_centavos` = TOTAL tras descuentos. Abono se calcula on-the-fly (no almacenado)
- Eliminar cliente requiere cascada manual: pagos → resumenes → grabaciones → citas (forceDelete) → roles → profile → datos_natales → consents → agente_conv → creditos → tokens → user (forceDelete)
- PHP local: usar `C:\wamp64\bin\php\php8.3.14\php.exe` (PATH tiene 7.4 por defecto)
- Deploy: `pscp` / `plink` PuTTY con `-pw '...' -batch`
  - Backend: `domains/tarotestrellas.com/public_html/backend/`
  - Frontend: `domains/tarotestrellas.com/public_html/`
- API publica real verificada: `https://tarotestrellas.com/backend/public/api` (`/health` responde OK). El frontend PROD debe compilar con `VITE_API_URL=https://tarotestrellas.com/backend/public/api`.
- Para reload OPcache: **NO** `touch index.php` en raíz; usar `touch backend/bootstrap/app.php` o reiniciar PHP-FPM desde panel Hostinger

---

## 📂 Archivos clave para entender el sistema

| Path | Propósito |
|---|---|
| `backend/app/Http/Controllers/Api/CitaController.php` | Flujo agenda + pago |
| `backend/app/Http/Controllers/Api/PagoController.php` | PayPal / transferencia |
| `backend/app/Jobs/ProcesarReembolsoPaypalJob.php` | Reembolsos automaticos PayPal por capture_id |
| `backend/app/Services/PaypalService.php` | Ordenes, capturas y reembolsos PayPal |
| `backend/app/Services/AgenteIAService.php` | Astrea (chatbot IA) |
| `backend/app/Notifications/ResetPasswordNotification.php` | Email branded reset |
| `backend/resources/views/emails/layouts/branded.blade.php` | Layout corporativo para emails transaccionales |
| `frontend/src/pages/app/PagarCitaPage.tsx` | UI pago con 20% / 80% |
| `frontend/src/components/agente/AgenteWidget.tsx` | Chatbot flotante |
| `frontend/src/components/agente/AgenteChat.tsx` | Chat asistente IA full-page |
| `frontend/src/layouts/AdminLayout.tsx` | Drawer móvil admin |
| `docs/ESPECIFICACION_TECNICATarotEstrellasV2.md` | Spec completa |
| `docs/QA-CHECKLIST.md` | Protocolo de pruebas |
| `docs/DEPLOY_HOSTINGER.md` | Manual deploy |
| `docs/OPERATIONS_RUNBOOK.md` | Pendientes operativos: PayPal LIVE, cron, queue, API limits, E2E, WhatsApp |

---

## 🎯 Próximos pasos sugeridos (orden recomendado)

1. **cfg-paypal-prod** — desbloquea cobros/reembolsos reales cuando existan credenciales LIVE
2. **cfg-06/cfg-05** activar cron scheduler y queue worker/fallback en Hostinger
3. **cfg-15** Smoke E2E con `QA-CHECKLIST.md`
4. **Flow.cl fase 2** — preparar segunda pasarela para cobros internacionales en CLP
5. **cfg-09** WhatsApp Meta — último, requiere aprobación de plantillas

---

## 🔐 Accesos y secretos

- No guardar secretos, passwords, tokens ni credenciales reales en documentos versionados.
- Los accesos de PROD, usuarios admin y claves de proveedores deben vivir en el gestor seguro del equipo o en `.env` fuera de Git.
- **Anthropic model**: configurable en `.env` mediante `ANTHROPIC_AGENT_MODEL`.
- **Reset link expiration**: 60 min (`config/auth.php` → `passwords.users.expire`).

---

## 🤝 Memoria compartida para asistentes

- Agregado `AGENTS.md` como memoria comun para Codex, Claude y GitHub Copilot.
- Agregado `CLAUDE.md` para que Claude encuentre rapidamente el contexto.
- Agregado `docs/AGENTS.md` con reglas especificas para documentacion.
- Agregado `.github/copilot-instructions.md` con instrucciones canonicas para Copilot.
- Mantener estos archivos sincronizados cuando cambien arquitectura, comandos, dependencias, flujos, estado real o decisiones importantes.

---

_Última actualización: 2026-05-14_
