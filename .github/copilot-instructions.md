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
