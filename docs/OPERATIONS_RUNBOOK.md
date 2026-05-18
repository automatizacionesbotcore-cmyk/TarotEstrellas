# Runbook operativo TarotEstrellas

Ultima actualizacion: 2026-05-13.

Este documento aterriza los pendientes operativos del handoff sin incluir secretos. Las credenciales reales deben configurarse en `.env` de produccion o en el gestor seguro del equipo.

## API real de produccion

Frontend PROD:

```env
VITE_API_URL=https://tarotestrellas.com/backend/public/api
```

Health:

```text
https://tarotestrellas.com/backend/public/api/health
```

No usar `api.tarotestrellas.com` mientras no exista subdominio/API separada.

## Deploy PROD 2026-05-14

Estado: completado.

- Respaldo manual remoto creado antes del deploy: `backup-predeploy-20260514-143216.tar.gz` en home del usuario Hostinger.
- Frontend desplegado desde `frontend/dist` con `.htaccess` de SPA.
- Backend desplegado con reembolsos PayPal automaticos y ajustes de API usage.
- Post-deploy ejecutado: `optimize:clear`, `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `queue:restart`.
- Smoke test OK:
  - `https://tarotestrellas.com/backend/public/api/health`
  - `https://tarotestrellas.com/`
  - `https://tarotestrellas.com/servicios`
  - `https://tarotestrellas.com/backend/public/api/public/tipos-consulta`

## Deploy PROD 2026-05-18

Estado: completado.

- Respaldo manual remoto creado antes del deploy: `backup-predeploy-ui-20260518-110522.tar.gz` en home del usuario Hostinger.
- Frontend desplegado desde `frontend/dist`, compilado con `VITE_API_URL=https://tarotestrellas.com/backend/public/api`.
- Cambio publicado: footer publico incluye credito con enlace a `https://automatizatech.cl`.
- No se desplegaron cambios backend ni se tocaron migraciones.
- Smoke test OK:
  - `https://tarotestrellas.com/backend/public/api/health`
  - `https://tarotestrellas.com/`
  - `https://tarotestrellas.com/servicios`
  - Bundle PROD verificado con `https://automatizatech.cl` y texto `Desarrollado por`.

## Hotfix PROD 2026-05-18: Google OAuth frontend URL

Estado: completado.

- Respaldo manual remoto creado antes del hotfix: `backup-predeploy-oauth-20260518-112903.tar.gz` en home del usuario Hostinger.
- Causa: `AuthController::googleCallback()` usaba `env('FRONTEND_URL', 'http://localhost:5173')` dentro del controller; con configuracion cacheada de Laravel podia caer al default local.
- Fix desplegado: usar `config('app.frontend_url', config('app.url'))`.
- Post-deploy ejecutado: `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, `queue:restart` y reload OPcache tocando `backend/bootstrap/app.php`.
- Smoke test OK:
  - `https://tarotestrellas.com/backend/public/api/health`
  - Callback Google sin code redirige a `https://tarotestrellas.com/auth/login?error=google_failed`, no a localhost.
  - Inicio OAuth redirige a Google correctamente.

## Hotfix PROD 2026-05-18: Astrea mobile público

Estado: completado.

- Respaldo manual remoto creado antes del hotfix: `backup-predeploy-astrea-mobile-20260518-115334.tar.gz` en home del usuario Hostinger.
- Causa: en Chrome mobile el widget flotante usaba `100vh`; en algunos Samsung la barra del navegador podia dejar el header fuera del area visible. Además el boton de pantalla completa no se renderizaba para visitantes publicos.
- Fix desplegado: Astrea publico ahora muestra boton de pantalla completa/restaurar, el panel usa `100svh`/`100dvh` con safe-area y mantiene los controles superiores visibles.
- Pruebas locales:
  - `pnpm build`
  - `pnpm test`
  - `pnpm test:e2e e2e/responsive.spec.ts` con caso mobile de Astrea publico.
- Smoke test PROD OK:
  - `https://tarotestrellas.com/`
  - `https://tarotestrellas.com/backend/public/api/health`
  - CSS PROD verificado con `100dvh`, `safe-area-inset-top` y `astrea-widget--expanded`.

## cfg-paypal-prod: PayPal LIVE

Estado: requiere credenciales externas.

Checklist:

1. Crear o abrir app LIVE en PayPal Developer.
2. Configurar en `backend/.env` de produccion:

```env
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_BASE_URL=https://api-m.paypal.com
```

3. Limpiar/cachear config Laravel:

```bash
cd /home/<usuario>/domains/tarotestrellas.com/public_html/backend
php artisan optimize:clear
php artisan config:cache
```

4. Ejecutar una compra real controlada y validar:

- cita queda pagada/confirmada segun flujo;
- `pagos.referencia_externa` registra referencia PayPal;
- `pagos.metadata.paypal_capture_id` queda presente porque reembolsos automaticos dependen de ese capture ID;
- email de confirmacion llega;
- UI muestra abono 20% y saldo 80% correctamente.

5. Ejecutar un reembolso controlado desde admin o politica de cancelacion y validar:

- `ProcesarReembolsoPaypalJob` llama a `/v2/payments/captures/{capture_id}/refund`;
- `reembolsos.estado` pasa a `completado` si PayPal responde `COMPLETED` o `PENDING`;
- `reembolsos.metadata.paypal_refund_id` y `paypal_status` quedan registrados;
- el detalle admin muestra evento `procesado_paypal`.

## cfg-06: Cron scheduler

Estado codigo: definido en `backend/routes/console.php`.

Jobs programados actualmente:

- `ExpirarReservasJob`: cada minuto.
- `ProcesarReembolsosPendientesJob`: cada 5 minutos.
- `EnviarRecordatorioCitaJob`: email 3 dias, 1 dia y 1 hora antes.
- `EnviarRecordatorioCitaJob`: WhatsApp 30 minutos antes.
- `LimpiarGrabacionesExpiradasJob`: diario 03:00.
- `ExpirarMembresiasJob`: diario 02:30.
- `AvisarVencimientoMembresiasJob`: diario 09:00.
- `VerificarConsumoApisJob`: cada hora.
- `DetectarNoShowAutomaticoJob`: cada 15 minutos.

Cron recomendado en Hostinger:

```cron
* * * * * cd /home/<usuario>/domains/tarotestrellas.com/public_html/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Si Hostinger no permite cron cada minuto, usar el intervalo minimo disponible y documentar la degradacion: reservas vencidas, recordatorios y no-show pueden ejecutarse con retraso.

Verificacion:

```bash
php artisan schedule:list
php artisan schedule:run -v
```

## cfg-05: Queue worker

Estado codigo: `QUEUE_CONNECTION=database` y tabla `jobs` disponibles.

Worker recomendado si el hosting permite proceso persistente:

```bash
cd /home/<usuario>/domains/tarotestrellas.com/public_html/backend
php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Supervisor ejemplo para VPS o entorno con Supervisor:

```ini
[program:tarotestrellas-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /home/<usuario>/domains/tarotestrellas.com/public_html/backend/artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
directory=/home/<usuario>/domains/tarotestrellas.com/public_html/backend
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/home/<usuario>/domains/tarotestrellas.com/public_html/backend/storage/logs/worker.log
stopwaitsecs=130
```

Si Hostinger compartido no permite workers persistentes, fallback temporal:

```cron
* * * * * cd /home/<usuario>/domains/tarotestrellas.com/public_html/backend && /usr/bin/php artisan queue:work database --stop-when-empty --tries=3 --timeout=120 >> storage/logs/queue-cron.log 2>&1
```

Verificacion:

```bash
php artisan queue:failed
php artisan queue:retry all
tail -100 storage/logs/worker.log
```

## cfg-14: Limites API y alertas

Estado codigo: implementado en backend y frontend.

Pantalla: `/app/admin/api-usage` para super_admin.

Incluye:

- consumo mensual Anthropic/OpenAI en USD aproximado;
- consumo Daily.co en minutos;
- limites y umbrales editables;
- emails extra para alertas;
- job horario `VerificarConsumoApisJob`;
- emails e in-app notifications cuando se alcanza warning/exceeded;
- endpoint manual `POST /api/admin/api-usage/check`.

Pruebas enfocadas:

```bash
cd backend
C:\wamp64\bin\php\php8.3.14\php.exe artisan test --filter=ApiUsageDashboardTest
```

## Reembolsos PayPal automaticos

Estado codigo: implementado.

Componentes:

- `App\Jobs\ProcesarReembolsoPaypalJob`
- `App\Services\PaypalService::reembolsarCaptura`
- `ProcesarReembolsosPendientesJob` enruta segun `pago.canal`
- `ReembolsoController::procesar` encola PayPal o Stripe legacy segun corresponda

Pruebas enfocadas:

```bash
cd backend
C:\wamp64\bin\php\php8.3.14\php.exe artisan test --filter=ProcesarReembolsoJobTest
C:\wamp64\bin\php\php8.3.14\php.exe artisan test --filter=AdminReembolsoControllerTest
```

## Emails transaccionales corporativos

Estado codigo: implementado.

Convencion:

- usar `backend/resources/views/emails/layouts/branded.blade.php` para correos nuevos;
- usar `emails.partials.detail-table` para datos clave de citas, pagos o alertas;
- usar `emails.partials.button` para CTAs principales;
- mantener copy claro para usuario final y no incluir secretos ni datos sensibles;
- recuperacion de contraseña y verificacion de email Laravel ya estan personalizados.

Validacion rapida:

```bash
cd backend
C:\wamp64\bin\php\php8.3.14\php.exe artisan view:cache
C:\wamp64\bin\php\php8.3.14\php.exe artisan test --filter=ComprobanteEmailFlowTest
```

## cfg-15: Smoke E2E

Local:

```powershell
cd frontend
npm install
npm run test:e2e:install
npm run test:e2e
```

Con frontend ya levantado:

```powershell
cd frontend
$env:E2E_NO_SERVER=1
$env:E2E_BASE_URL="http://localhost:5173"
npm run test:e2e
```

Release final: seguir `docs/QA-CHECKLIST.md` sustituyendo referencias antiguas de Stripe por PayPal.

## cfg-09: WhatsApp Meta

Estado codigo: webhook existe (`GET/POST /api/webhooks/whatsapp`) y tiene tests.

Configurar cuando existan app y plantillas aprobadas:

```env
WHATSAPP_PROVIDER=meta
META_WHATSAPP_APP_SECRET=...
META_WHATSAPP_VERIFY_TOKEN=...
```

Webhook publico:

```text
https://tarotestrellas.com/api/webhooks/whatsapp
```

No activar recordatorios WhatsApp en produccion hasta confirmar plantillas y firma.
