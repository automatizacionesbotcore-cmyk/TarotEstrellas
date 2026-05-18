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

<!-- AI-MEMORY-WORKFLOW:START -->
## Multi-Agent Collaboration Workflow

Antes de trabajar, crea una rama nueva. Usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `DEEPSEEK.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, DeepSeek y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo y evita duplicar informacion que el job ya mantiene. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes, porque algunos proyectos guardan su documentacion principal en `docs/Master`. Crea archivos de memoria si faltan y son utiles. Actualiza memoria y documentacion cuando cambien arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes. No guardes secretos. Al terminar, resume cambios, pruebas y estado Git; no hagas merge sin permiso. Espera confirmacion del usuario para cerrar y sugiere crear PR o merge hacia `main`/`master`/`develop`/`dev` segun corresponda.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- Job de documentacion: hay un job que sincroniza/actualiza documentacion y memoria de agentes, incluyendo DeepSeek local. Revisar su salida antes de reestructurar archivos.
- DeepSeek local: el modelo necesita una app puente con acceso a archivos/RAG/MCP; no lee la boveda ni el proyecto por si solo.
- No guardar secretos, tokens, passwords, credenciales ni datos sensibles.

### Notas operativas actuales

- Frontend: usar `pnpm build`, `pnpm test` y comandos `pnpm exec ...`; evitar `npm`.
- La rama `feat/ui-ux-pro-max-redesign` contiene el rediseño responsive del admin, super admin, flujos publicos/cliente, reserva y pagos.
- PR sugerido desde GitHub: `origin/feat/ui-ux-pro-max-redesign`.
- Build actual: `pnpm build` pasa con warning no bloqueante de chunks grandes.
<!-- AI-MEMORY-WORKFLOW:END -->





