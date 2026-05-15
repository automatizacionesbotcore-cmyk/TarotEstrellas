# Contexto compartido para agentes

Este archivo es la memoria operativa comun para Codex, Claude y GitHub Copilot en TarotEstrellas. Mantenlo actualizado cuando cambien arquitectura, comandos, dependencias, estructura, flujos, decisiones tecnicas o estado real del proyecto. No guardar secretos, tokens, passwords, credenciales ni datos sensibles.

## Objetivo del proyecto

TarotEstrellas es una plataforma fullstack para reservar y gestionar consultas espirituales online: catalogo publico, agenda, pagos, videollamadas, grabaciones/transcripcion, resumenes IA, chatbot Astrea, panel de cliente y panel admin.

## Estado actual

- Producto desplegado en produccion en `https://tarotestrellas.com`.
- Stack principal: Laravel 11, PHP 8.3 en produccion, MySQL/MariaDB, React + TypeScript + Vite, TanStack Query, Tailwind, Playwright/Vitest.
- El backend requiere PHP `^8.2`; en local Windows usar preferentemente `C:\wamp64\bin\php\php8.3.14\php.exe` porque el PATH puede apuntar a PHP 7.4.
- Pagos actuales: PayPal + transferencia bancaria. Stripe queda legacy/en documentos antiguos y debe tratarse con cautela.
- Daily.co esta implementado pero puede operar en modo mock si faltan variables de entorno.
- IA: Astrea usa servicio Anthropic; transcripcion/resumenes pueden depender de OpenAI/Anthropic segun configuracion.
- Email: Resend. OAuth: Google. WhatsApp Meta pendiente/configurable.

## Ramas y flujo Git

Seguir Git Flow simplificado:

- `main`: espejo de produccion; no hacer commits directos.
- `dev`: integracion/staging.
- `feat/*`, `fix/*`, `chore/*`: ramas de trabajo individuales, idealmente creadas desde `dev`.

Antes de modificar archivos:

1. Revisar rama y estado con `git status --short --branch`.
2. Crear una rama clara, por ejemplo `feat/nombre`, `fix/nombre` o `chore/nombre`.
3. No hacer merge automaticamente. Al terminar sugerir PR o merge hacia la rama base correspondiente.

## Estructura relevante

- `backend/`: Laravel API, jobs, servicios, controladores, migraciones, seeders y tests PHPUnit.
- `frontend/`: React SPA, rutas cliente/admin, componentes, pruebas Vitest y Playwright.
- `frontend/email-templates/`: plantillas HTML de email.
- `frontend/e2e/`: pruebas E2E Playwright.
- `docs/`: handoff, especificacion tecnica, QA, deploy, integraciones y planes.
- `.github/`: instrucciones para herramientas GitHub/Copilot.
- `.claude/`: configuracion local de Claude. No asumir que todo ahi es portable.

## Documentacion clave

- `docs/HANDOFF.md`: estado real mas reciente y estrategia de ramas.
- `docs/ESPECIFICACION_TECNICATarotEstrellasV2.md`: especificacion completa.
- `docs/QA-CHECKLIST.md`: checklist QA de release.
- `docs/DEPLOY_HOSTINGER.md`: guia de despliegue y notas de produccion.
- `docs/DAILY_INTEGRATION.md`: estado e instrucciones Daily.co.
- `docs/OPERATIONS_RUNBOOK.md`: pasos operativos para PayPal LIVE, cron, queue, API limits, E2E y WhatsApp.
- `docs/AGENTS.md`: memoria adicional para asistentes.
- `.github/copilot-instructions.md`: reglas especificas para GitHub Copilot.

## Comandos utiles

Backend:

```powershell
cd backend
composer install
C:\wamp64\bin\php\php8.3.14\php.exe artisan migrate
C:\wamp64\bin\php\php8.3.14\php.exe artisan test
C:\wamp64\bin\php\php8.3.14\php.exe artisan serve
```

Frontend:

```powershell
cd frontend
npm install
npm run dev
npm run build
npm run lint
npm run test
npm run test:e2e
```

E2E con servidor existente:

```powershell
cd frontend
$env:E2E_NO_SERVER=1
$env:E2E_BASE_URL="http://localhost:5173"
npm run test:e2e
```

Deploy: seguir `docs/DEPLOY_HOSTINGER.md`. Importante: no crear ni tocar `index.php` vacio en la raiz `public_html/`; para refrescar OPcache usar el backend o el panel de Hostinger.

## Convenciones tecnicas

- Tablas en `snake_case` plural.
- Entidades publicas con UUID cuando aplica, especialmente citas/pagos/grabaciones.
- Montos como enteros en centavos o unidad menor segun el contexto existente.
- Fechas normalizadas en backend y con timezone de negocio `America/Santiago`.
- Respetar Sanctum, roles y middlewares existentes.
- Mantener calculo de abono como 20% del total final cuando aplique; no duplicar reglas de precio si ya existe helper/servicio.
- Preferir servicios Laravel existentes antes de crear logica en controladores.
- En frontend, seguir patrones de rutas, hooks, TanStack Query y componentes existentes.
- No introducir secretos en archivos versionados. Usar `.env`, `.env.example` solo con placeholders.

## Decisiones y notas recientes

- PayPal reemplaza el flujo principal Stripe para pagos nuevos; transferencia bancaria sigue activa.
- Reembolsos PayPal automaticos ya estan implementados mediante `ProcesarReembolsoPaypalJob`; Stripe queda legacy.
- Flow.cl esta comentado/pospuesto para fase 2.
- Emails transaccionales backend usan layout corporativo comun en `backend/resources/views/emails/layouts/branded.blade.php`; mantener logo, colores, tipografia y CTAs coherentes. Reset password y verificacion de correo tambien estan personalizados.
- Astrea debe renderizar Markdown sin mostrar `**` literales.
- Admin mobile usa drawer responsive.
- Eliminacion de cliente requiere cascada manual segun logica documentada en `docs/HANDOFF.md`.
- Hay documentos antiguos que aun mencionan Stripe, React 18 o rutas `.cl`; verificar contra el estado real antes de implementar.
- `frontend/package.json` declara React 19.x actualmente; no asumir React 18 solo por documentos anteriores.

## Problemas conocidos / pendientes

- Configurar PayPal LIVE en produccion si aun no se completo; seguir `docs/OPERATIONS_RUNBOOK.md`. El codigo de reembolso PayPal automatico ya existe, pero depende de `paypal_capture_id`.
- Activar cron scheduler de Laravel para recordatorios/expiracion; el codigo ya esta en `backend/routes/console.php`.
- Configurar worker de queues 24/7 o alternativa viable en Hostinger; el runbook incluye fallback cron `queue:work --stop-when-empty`.
- Limites/alertas de APIs ya tienen backend/frontend; verificar en `/app/admin/api-usage` y mantener tests `ApiUsageDashboardTest`.
- Completar smoke E2E de release con `docs/QA-CHECKLIST.md`.
- WhatsApp Meta requiere configuracion de webhook y plantillas aprobadas.
- Daily.co puede seguir en mock hasta completar variables y webhooks reales.
- Actualizar documentos antiguos que aun nombran Stripe como flujo principal.

## Proximos pasos para futuros agentes

1. Partir siempre desde rama limpia o rama nueva.
2. Leer `docs/HANDOFF.md` y este archivo antes de tocar codigo.
3. Si cambias comandos, dependencias, arquitectura o flujos, actualiza `AGENTS.md`, `docs/AGENTS.md`, `CLAUDE.md` y `.github/copilot-instructions.md` segun corresponda.
4. Ejecutar pruebas proporcionales al cambio y dejar resultados en el resumen final.
5. Revisar `git status --short --branch` antes de cerrar.

_Ultima actualizacion: 2026-05-14._
