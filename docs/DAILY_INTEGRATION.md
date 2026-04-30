# Daily.co Integration

## Estado actual
Toda la integración está implementada en **modo mock**: el código funciona end-to-end sin necesidad de cuenta Daily.co. Para activar producción solo hay que llenar variables de entorno.

## Activar Daily.co en producción
1. Crear cuenta en https://daily.co y obtener API key.
2. Configurar dominio (ej. `tarotestrellas.daily.co`).
3. En `backend/.env` agregar:
   ```
   DAILY_API_KEY=xxxxxxxxxxxxx
   DAILY_DOMAIN=tarotestrellas.daily.co
   DAILY_WEBHOOK_SECRET=<secret-elegido>
   DAILY_ENABLE_TRANSCRIPTION=true
   DAILY_RECORDING_RETENTION_DAYS=90
   ```
4. En el dashboard Daily.co configurar webhook:
   - URL: `https://tarotestrellas.com/api/webhooks/daily`
   - Eventos: `recording.ready`, `recording.started`, `recording.error`, `meeting.started`, `meeting.ended`
   - HMAC secret: el mismo `DAILY_WEBHOOK_SECRET`

## Pricing al cliente (CLP)
| Duración | Precio grabación adicional |
|----------|---------------------------|
| 30 min   | $2.500 CLP                |
| 60 min   | $3.990 CLP                |
| 90 min   | $5.990 CLP                |

Implementado en `app/Support/GrabacionPricing.php`. La transcripción IA es **gratis para todos** y se procesa automáticamente vía Whisper.

## Permisos
- **Cliente:** ve solo el `resumen` IA en su historial. NO accede al texto crudo.
- **Especialista:** owner de la sala, puede iniciar/detener grabación.
- **Admin:** acceso total — descarga transcripción cruda en `/admin/citas/{uuid}/transcripcion/descargar`.

## Retención
- Grabaciones se borran automáticamente después de **90 días** (configurable en `DAILY_RECORDING_RETENTION_DAYS`).
- Job `LimpiarGrabacionesExpiradasJob` corre cada día a las 03:00 (definido en `routes/console.php`).
- Marca grabación como `expirada` y elimina archivo en Daily.co.

## Endpoints clave
- `GET /api/citas/{uuid}/sala` — info de sala (URL+token) para cliente
- `GET /api/me/citas/{uuid}/sala-video` — info detallada con permisos
- `POST /api/me/citas/{uuid}/sala-video/grabacion/iniciar` (owner)
- `POST /api/me/citas/{uuid}/sala-video/grabacion/detener` (owner)
- `GET /api/admin/citas/{uuid}/transcripcion` (admin)
- `GET /api/admin/citas/{uuid}/transcripcion/descargar` (admin, TXT)
- `POST /api/webhooks/daily` (HMAC SHA-256 firmado)

## Modo mock
Mientras `DAILY_API_KEY` esté vacío:
- `DailyRoomService::isMockMode()` devuelve `true`.
- Genera URLs `https://mock.daily.co/{room_name}` y tokens base64.
- Permite probar todos los flujos UI sin cuenta real.
- Webhooks deben simularse manualmente con `php artisan tinker`.
