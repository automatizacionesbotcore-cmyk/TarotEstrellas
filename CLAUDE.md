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

<!-- AI-MEMORY-WORKFLOW:START -->
## Claude Project Memory

Antes de trabajar, crea una rama nueva. Usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `DEEPSEEK.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, DeepSeek y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo y evita duplicar informacion que el job ya mantiene. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes, porque algunos proyectos guardan su documentacion principal en `docs/Master`. Crea archivos de memoria si faltan y son utiles. Actualiza memoria y documentacion cuando cambien arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes. No guardes secretos. Al terminar, resume cambios, pruebas y estado Git; no hagas merge sin permiso. Espera confirmacion del usuario para cerrar y sugiere crear PR o merge hacia `main`/`master`/`develop`/`dev` segun corresponda.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- Job de documentacion: hay un job que sincroniza/actualiza documentacion y memoria de agentes, incluyendo DeepSeek local. Revisar su salida antes de reestructurar archivos.
- Claude debe revisar este archivo antes de modificar el proyecto.

### Estado actual

- Rama UI: `feat/ui-ux-pro-max-redesign` esta subida a GitHub con upstream.
- Frontend: usar `pnpm` exclusivamente. `npm` queda obsoleto para este frontend.
- Verificacion conocida: `pnpm build` pasa; Vite mantiene warning de chunks grandes.
- No hacer merge sin confirmacion del usuario.
<!-- AI-MEMORY-WORKFLOW:END -->





