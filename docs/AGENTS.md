# Guia para asistentes en documentacion

Este documento complementa `../AGENTS.md` y resume como trabajar con la carpeta `docs/`.

## Fuentes de verdad

- `HANDOFF.md`: estado operativo y pendientes actuales.
- `ESPECIFICACION_TECNICATarotEstrellasV2.md`: alcance funcional completo.
- `QA-CHECKLIST.md`: pruebas manuales y smoke de release.
- `DEPLOY_HOSTINGER.md`: deploy y diferencias reales de produccion.
- `DAILY_INTEGRATION.md`: modo mock/produccion de Daily.co.
- `OPERATIONS_RUNBOOK.md`: pasos concretos para pendientes operativos sin secretos.

## Como actualizar docs

- Si cambias arquitectura, flujos de negocio, comandos, dependencias, deploy o integraciones, actualiza el documento especifico y tambien la memoria compartida si afecta a futuros agentes.
- Si encuentras documentos antiguos, no borres contexto historico util; agrega una nota fechada con el estado real.
- No incluir secretos, tokens, passwords, credenciales ni datos sensibles. Usar placeholders y referir a gestores seguros o `.env` local/produccion.
- Mantener fechas absolutas en notas de estado para evitar ambiguedad.

## Estado conocido al 2026-05-13

- Produccion: `https://tarotestrellas.com`.
- Backend: Laravel 11, PHP 8.3 en produccion.
- Frontend: React + TypeScript + Vite. `frontend/package.json` declara React 19.x.
- Pagos principales: PayPal + transferencia bancaria; Stripe es legacy en algunos documentos.
- Reembolsos PayPal automaticos implementados; validar con `ProcesarReembolsoJobTest` y `AdminReembolsoControllerTest`.
- Daily.co puede operar en mock hasta configurar variables reales.
- Emails transaccionales backend usan layout corporativo comun en `backend/resources/views/emails/layouts/branded.blade.php`; reset password y verificacion de email tambien estan personalizados.
- Pendientes prioritarios: PayPal LIVE, activar cron scheduler en PROD, activar queue worker/fallback, smoke E2E, WhatsApp. `cfg-14` API usage esta implementado y debe verificarse con tests.

_Ultima actualizacion: 2026-05-14._
