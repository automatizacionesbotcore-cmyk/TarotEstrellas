# DeepSeek Local Project Memory

<!-- AI-MEMORY-WORKFLOW:START -->
## DeepSeek Local Project Memory

DeepSeek local no lee archivos por si solo. Debe usarse desde una app puente con acceso al proyecto o a la boveda, por ejemplo Ollama/Open WebUI, AnythingLLM, Continue, Cline, Roo Code o un cliente MCP/RAG compatible.

Antes de trabajar, carga o lee este contexto: `AGENTS.md`, `DEEPSEEK.md`, `CLAUDE.md`, `docs/AGENTS.md`, `.github/copilot-instructions.md`, `docs/`, `docs/Master` si existe, y la boveda `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- DeepSeek debe recibir contexto por RAG, MCP, workspace de IDE/agente o prompt inicial.
- No guardar secretos, tokens, passwords, credenciales ni datos sensibles.

### Estado actual para contexto RAG

- Rama UI activa: `feat/ui-ux-pro-max-redesign`, subida a `origin/feat/ui-ux-pro-max-redesign`.
- Frontend: usar `pnpm`, no `npm`. Existe `frontend/pnpm-lock.yaml`; `frontend/package-lock.json` fue eliminado.
- Verificacion: `pnpm build` pasa. Warning conocido: chunks grandes de Vite.
- Cambios recientes: pulido responsive y visual del panel admin/super admin, paginas publicas, cliente, reserva y pagos.
<!-- AI-MEMORY-WORKFLOW:END -->

