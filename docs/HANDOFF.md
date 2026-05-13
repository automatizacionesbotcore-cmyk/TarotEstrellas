# 📋 Handoff TarotEstrellas — Estado Actual

**Stack**: Laravel 11 (PHP 8.3) + React 18 + Vite + TanStack Query + MySQL 8 · Hostinger
**Repo**: `automatizacionesbotcore-cmyk/TarotEstrellas`
**PROD**: https://tarotestrellas.com (Hostinger SSH `88.223.85.175:65002` user `u402745362`)
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
- ✅ Backend Laravel en `public_html/backend/` con migrations, seeders (roles + super_admin `lmgm.0303@gmail.com`)
- ✅ SSL activo, OAuth Google funcional, Resend dominio verificado
- ✅ Caches recompilados: `optimize:clear`, `config:cache`, `route:cache`

---

## ⏳ Pendiente (11 tareas)

| ID | Tarea | Notas |
|---|---|---|
| **cfg-paypal-prod** | Configurar PayPal **LIVE** en `.env` PROD | Sandbox funcionando local. Usar credenciales de https://developer.paypal.com/dashboard/applications/live |
| **cfg-09** | WhatsApp Meta API | Webhook `/api/webhooks/whatsapp`, plantillas recordatorio aprobadas |
| **cfg-14** | Límites API | Configurar topes Anthropic/OpenAI/Daily desde panel super_admin → APIs Consumo + emails de alerta |
| **cfg-15** | Smoke E2E completo | Seguir `docs/QA-CHECKLIST.md` end-to-end |
| **cfg-05** | Queue worker Supervisor | 24/7 para jobs video/transcripción (Hostinger limitado, evaluar alternativa) |
| **cfg-06** | Cron scheduler | `* * * * * php artisan schedule:run` para recordatorios y expirar reservas |
| cfg-01..04, cfg-12 | Items de infra ya cubiertos parcialmente por deploy actual; cerrar formalmente |

---

## 🐛 Bugs recientes resueltos

1. **Pantalla en blanco PROD**: `index.php` vacío creado por `touch` errático que precedía a `index.html`. Solución: NO usar `touch index.php` en `public_html/` raíz
2. **Reset password en inglés con link roto**: creado `ResetPasswordNotification` custom + `APP_LOCALE=es` + `frontend_url` en config
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
- Para reload OPcache: **NO** `touch index.php` en raíz; usar `touch backend/bootstrap/app.php` o reiniciar PHP-FPM desde panel Hostinger

---

## 📂 Archivos clave para entender el sistema

| Path | Propósito |
|---|---|
| `backend/app/Http/Controllers/Api/CitaController.php` | Flujo agenda + pago |
| `backend/app/Http/Controllers/Api/PagoController.php` | PayPal / transferencia |
| `backend/app/Services/AgenteIAService.php` | Astrea (chatbot IA) |
| `backend/app/Notifications/ResetPasswordNotification.php` | Email branded reset |
| `frontend/src/pages/app/PagarCitaPage.tsx` | UI pago con 20% / 80% |
| `frontend/src/components/agente/AgenteWidget.tsx` | Chatbot flotante |
| `frontend/src/components/agente/AgenteChat.tsx` | Chat asistente IA full-page |
| `frontend/src/layouts/AdminLayout.tsx` | Drawer móvil admin |
| `docs/ESPECIFICACION_TECNICATarotEstrellasV2.md` | Spec completa |
| `docs/QA-CHECKLIST.md` | Protocolo de pruebas |
| `docs/DEPLOY_HOSTINGER.md` | Manual deploy |

---

## 🎯 Próximos pasos sugeridos (orden recomendado)

1. **cfg-paypal-prod** — rápido, desbloquea cobros reales
2. **cfg-06** Cron scheduler — recordatorios + expirar reservas pendientes
3. **cfg-14** Límites API + alertas
4. **cfg-15** Smoke E2E con `QA-CHECKLIST.md`
5. **cfg-09** WhatsApp Meta — último, requiere aprobación de plantillas

---

## 🔐 Credenciales / accesos clave (referencia interna)

- **PROD SSH**: `u402745362@88.223.85.175:65002`
- **Super admin**: `lmgm.0303@gmail.com`
- **Anthropic model**: `claude-3-5-sonnet-latest` (configurable en `.env` `ANTHROPIC_AGENT_MODEL`)
- **Reset link expiration**: 60 min (`config/auth.php` → `passwords.users.expire`)

---

_Última actualización: 2026-05-13_
