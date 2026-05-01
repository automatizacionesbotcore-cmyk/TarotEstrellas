# Tests E2E con Playwright

## Setup

```bash
cd frontend
npm install
npx playwright install
```

## Correr

```bash
npm run test:e2e
# o apuntando a un servidor ya levantado:
E2E_NO_SERVER=1 E2E_BASE_URL=http://localhost:5173 npm run test:e2e
```

## Specs

- `smoke.spec.ts` — humo público.
- `auth.spec.ts` — registro + login.
- `agendar.spec.ts` — flujo agenda (placeholder, requiere auth).

Stripe / Daily / Anthropic deben mockearse en backend para cubrir pago y sala.
