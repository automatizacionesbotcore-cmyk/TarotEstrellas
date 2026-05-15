# Memoria para Claude

Claude debe usar `AGENTS.md` como fuente principal de contexto compartido del proyecto. Este archivo existe para que Claude Desktop/CLI encuentre las reglas rapidamente y no pierda el handoff entre sesiones.

## Antes de trabajar

1. Revisar `git status --short --branch`.
2. Crear una rama nueva si aun no existe una rama de trabajo para la tarea.
3. Leer `AGENTS.md`, `docs/AGENTS.md`, `docs/HANDOFF.md` y la documentacion relevante en `docs/`.
4. No guardar secretos ni credenciales en archivos versionados.

## Prioridades de continuidad

- Mantener sincronizados `AGENTS.md`, `CLAUDE.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` cuando cambien reglas, comandos, arquitectura, dependencias o estado real.
- Preferir el estado fechado mas reciente en `docs/HANDOFF.md` sobre documentos historicos.
- Usar `docs/OPERATIONS_RUNBOOK.md` para pendientes operativos: PayPal LIVE, cron scheduler, queue worker, API usage, smoke E2E y WhatsApp.
- Tratar menciones a Stripe o dominios `.cl` como potencialmente antiguas; el flujo actual documentado es PayPal + transferencia en `tarotestrellas.com`.
- `cfg-14` API usage esta implementado; mantener cobertura en `ApiUsageDashboardTest`.
- Reembolsos PayPal automaticos estan implementados con `ProcesarReembolsoPaypalJob`; Stripe es legacy.
- Emails transaccionales backend usan layout corporativo comun; si agregas un envio nuevo, extender `emails.layouts.branded` o justificar la excepcion.
- Al terminar, resumir cambios, pruebas y estado Git; no hacer merge sin confirmacion del usuario.

_Ultima actualizacion: 2026-05-14._
