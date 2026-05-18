# GitHub Copilot instructions - TarotEstrellas

Use `AGENTS.md` as the primary shared project memory. Keep generated suggestions aligned with the current documented state, not with older references.

## Current stack

- Backend: Laravel 11, PHP 8.3 in production, Sanctum, Socialite, PHPUnit.
- Frontend: React + TypeScript + Vite, TanStack Query, Tailwind, Vitest, Playwright.
- Payments: PayPal + bank transfer for the current flow. Stripe references are legacy unless a task explicitly touches old compatibility code.
- PayPal automatic refunds are implemented with `ProcesarReembolsoPaypalJob`; preserve Stripe refund code as legacy compatibility.
- Video: Daily.co, with mock mode when production variables are missing.
- AI: Anthropic/OpenAI services depending on the feature.
- Email: Resend. Transactional backend emails use the branded Blade layout in `backend/resources/views/emails/layouts/branded.blade.php`; new emails should follow it.

## Coding guidance

- Follow existing Laravel services/controllers/middleware patterns.
- Keep monetary values as integer minor units where the existing code does so.
- Use UUIDs for public entities where the app already exposes UUIDs.
- Prefer existing frontend hooks, API clients, route conventions and components.
- Do not introduce secrets into tracked files. `.env.example` may contain placeholders only.
- If changing commands, dependencies, architecture, deploy flow or business rules, update `AGENTS.md`, `docs/AGENTS.md` and related docs.
- Use `docs/OPERATIONS_RUNBOOK.md` for operational pending work: PayPal LIVE, cron, queue workers, API usage checks, E2E smoke and WhatsApp setup.

## Local commands

Backend:

```powershell
cd backend
composer install
C:\wamp64\bin\php\php8.3.14\php.exe artisan test
```

Frontend:

```powershell
cd frontend
npm install
npm run build
npm run lint
npm run test
npm run test:e2e
```

_Last updated: 2026-05-14._

<!-- AI-MEMORY-WORKFLOW:START -->
## GitHub Copilot Instructions

Antes de sugerir cambios, usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `DEEPSEEK.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, DeepSeek y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes. Algunos proyectos guardan documentacion principal en `docs/Master`. Si cambian arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes, actualiza la memoria/documentacion correspondiente. No incluyas secretos, tokens, passwords, credenciales ni datos sensibles.

Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`

Estado actual: frontend usa `pnpm` exclusivamente (`packageManager: pnpm@11.1.2`). No sugerir `npm` para este frontend. Rama UI activa: `feat/ui-ux-pro-max-redesign`; `pnpm build` pasa con warning conocido de chunks grandes en Vite.
<!-- AI-MEMORY-WORKFLOW:END -->





