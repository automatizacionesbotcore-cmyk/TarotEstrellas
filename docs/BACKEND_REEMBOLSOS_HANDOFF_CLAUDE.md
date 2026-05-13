# Backend Reembolsos - Handoff Tecnico para Claude

Fecha: 2026-04-19
Proyecto: TarotEstrellas
Stack backend: Laravel + Sanctum + Queue Jobs + Scheduler + Stripe API

## ⚠️ Nota de actualización (2026-05-13)

Documento conservado como referencia técnica del módulo Stripe original. Cambios mayores desde abril:

- **Stripe → PayPal**: el motor de cobro se cambió a PayPal. Los reembolsos automáticos contra Stripe siguen en el código pero ya no aplican a transacciones nuevas. Pendiente: implementar `ProcesarReembolsoPaypalJob` análogo, llamando a la API `/v2/payments/captures/{id}/refund` de PayPal.
- **Frontend admin reembolsos** (`AdminReembolsosPage`, `AdminReembolsoDetallePage`): UI mantenida y mejorada en sprints siguientes (responsive, filtros).
- **Scheduler cada 5 min**: requiere cron en PROD (tarea `cfg-06` pendiente).
- **Tests**: la suite Stripe sigue verde. Añadir cobertura PayPal cuando se implemente.

Toda la arquitectura de jobs, metadata, timeline y endpoints admin descrita abajo sigue siendo el modelo a seguir para PayPal.

---

## 1) Objetivo del modulo

Este modulo implementa el ciclo completo de reembolsos:

1. Creacion automatica de reembolsos por reglas de negocio (cancelaciones y no-show).
2. Gestion administrativa (listar, detalle, metricas, exportar, crear manual, procesar).
3. Procesamiento asincrono contra Stripe para reembolsos de tipo automatico.
4. Seguimiento de eventos mediante metadata y timeline derivado.

## 2) Componentes principales

- API routes: [backend/routes/api.php](backend/routes/api.php)
- Console scheduler: [backend/routes/console.php](backend/routes/console.php)
- Controller admin: [backend/app/Http/Controllers/Api/ReembolsoController.php](backend/app/Http/Controllers/Api/ReembolsoController.php)
- Origen funcional (creacion automatica): [backend/app/Http/Controllers/Api/CitaController.php](backend/app/Http/Controllers/Api/CitaController.php)
- Job procesamiento individual: [backend/app/Jobs/ProcesarReembolsoJob.php](backend/app/Jobs/ProcesarReembolsoJob.php)
- Job batch pendientes: [backend/app/Jobs/ProcesarReembolsosPendientesJob.php](backend/app/Jobs/ProcesarReembolsosPendientesJob.php)
- Modelo: [backend/app/Models/Reembolso.php](backend/app/Models/Reembolso.php)
- Migracion: [backend/database/migrations/2026_04_19_100000_create_reembolsos_table.php](backend/database/migrations/2026_04_19_100000_create_reembolsos_table.php)
- Tests API admin: [backend/tests/Feature/AdminReembolsoControllerTest.php](backend/tests/Feature/AdminReembolsoControllerTest.php)
- Tests jobs: [backend/tests/Feature/ProcesarReembolsoJobTest.php](backend/tests/Feature/ProcesarReembolsoJobTest.php)

## 3) Modelo de datos

Tabla: `reembolsos`

Campos principales:

- `id` bigint PK
- `uuid` char(36) unique
- `cita_id` FK -> citas (cascadeOnDelete)
- `cliente_id` FK -> users (cascadeOnDelete)
- `pago_id` FK nullable -> pagos (nullOnDelete)
- `monto_centavos` unsignedBigInteger
- `moneda` char(3)
- `estado` string(30), default `pendiente`
- `razon` string(50)
- `metodo` string(30), default `mismo_medio_pago`
- `solicitado_en` timestamp nullable
- `procesado_en` timestamp nullable
- `metadata` json nullable
- `created_at`, `updated_at`, `deleted_at` (soft delete)

Indices:

- (`cliente_id`, `razon`, `estado`)
- (`cita_id`, `estado`)

Cast del modelo:

- `solicitado_en` datetime
- `procesado_en` datetime
- `metadata` array
- `deleted_at` datetime

## 4) Contrato de API (admin)

Todas estas rutas requieren `auth:sanctum` y rol admin (`$user->isAdmin()`).

### 4.1 Listado

- `GET /api/admin/reembolsos`
- Filtros:
  - `estado` in `pendiente|completado|fallido`
  - `razon` string
  - `metodo` in `mismo_medio_pago|transferencia_manual|credito_cliente`
  - `from_date`, `to_date` formato `Y-m-d`
  - `per_page` 1..100
- Respuesta: paginada con relaciones `cita`, `cliente`, `pago`.
- Error de rango de fechas invalido: HTTP 422.

### 4.2 Detalle con timeline

- `GET /api/admin/reembolsos/{uuid}`
- Respuesta:
  - `data.reembolso`: entidad completa con relaciones.
  - `data.timeline`: eventos ordenados por timestamp.
- Eventos soportados:
  - `creado`
  - `reintento`
  - `procesado_manual`
  - `procesado_stripe`
  - `fallo_procesamiento`

### 4.3 Metricas

- `GET /api/admin/reembolsos/metricas`
- Filtros de fecha: `from_date`, `to_date` (Y-m-d)
- Respuesta:
  - `total`
  - `monto_total_centavos`
  - `by_status` (pendiente/completado/fallido)
  - `by_method` (mismo_medio_pago/transferencia_manual/credito_cliente)

### 4.4 Export CSV

- `GET /api/admin/reembolsos/export`
- Filtros: mismos del listado (excepto `per_page`)
- Devuelve stream CSV con columnas:
  - uuid, cita_uuid, codigo_referencia, cliente_uuid, cliente_email, pago_uuid,
    estado, razon, metodo, monto_centavos, moneda, solicitado_en,
    procesado_en, created_at

### 4.5 Creacion manual

- `POST /api/admin/reembolsos`
- Payload:
  - `cita_uuid` required
  - `pago_uuid` nullable (si existe, debe pertenecer a la cita)
  - `monto_centavos` required >= 1
  - `moneda` required size 3
  - `razon` required
  - `metodo` required enum
  - `nota_admin` optional
- Estado inicial: `pendiente`
- Metadata inicial:
  - `creado_por_admin_id`
  - `nota_admin`

### 4.6 Procesamiento / retry / cierre manual

- `POST /api/admin/reembolsos/{uuid}/procesar`
- Payload:
  - `accion` in `procesar|reintentar|marcar_completado` (default `procesar`)
  - `referencia_manual` required_if `marcar_completado`
  - `nota_admin` optional

Comportamiento:

- `marcar_completado`:
  - fuerza `estado=completado`
  - setea `procesado_en`
  - metadata:
    - `referencia_manual`
    - `procesado_manual_por_admin_id`
    - `procesado_manual_en`
    - `nota_admin`

- `reintentar` sobre estado `fallido`:
  - mueve a `pendiente`
  - limpia `procesado_en`
  - metadata:
    - `reintento_por_admin_id`
    - `reintento_en`
    - `nota_admin`

- `procesar`/`reintentar` con metodo `mismo_medio_pago`:
  - encola `ProcesarReembolsoJob`

- Si metodo no automatico:
  - responde 422 indicando cierre manual.

## 5) Flujo de creacion automatica (origen dominio)

### 5.1 Cancelacion por cliente

En [backend/app/Http/Controllers/Api/CitaController.php](backend/app/Http/Controllers/Api/CitaController.php):

- Si aplica politica y existe abono completado:
  - crea reembolso `pendiente`
  - razon `cancelacion_24h`
  - metodo `mismo_medio_pago`
  - metadata incluye `cita_uuid` y `canal_pago_cita`

### 5.2 No-show de especialista

En `marcarNoShow`:

- Si especialista no asiste:
  - cita pasa a `cancelada_chachita`
  - por cada pago completado se crea reembolso `pendiente`
  - razon `cancelacion_chachita`
  - metodo `mismo_medio_pago`
  - metadata incluye `cita_uuid` y `origen=admin_no_show`

## 6) Procesamiento asincrono y scheduling

### 6.1 Job individual: ProcesarReembolsoJob

Validaciones previas:

- reembolso existe y estado `pendiente`
- `monto_centavos > 0`
- pago asociado existe y `canal == stripe`
- existe `services.stripe.secret`
- existe `stripe_charge_id` o `stripe_payment_intent_id`

Llamada Stripe:

- `POST https://api.stripe.com/v1/refunds`
- auth por bearer token
- payload:
  - `amount`
  - `charge` o `payment_intent`
  - `metadata` (`reembolso_uuid`, `cita_id`)

Estados aceptados de Stripe para completar:

- `succeeded`
- `pending`
- `requires_action`

Al completar:

- `estado = completado`
- `procesado_en = now`
- metadata relevante:
  - `stripe_refund_id`
  - `stripe_status`
  - `stripe.processed_at`
  - `stripe.refund_id`
  - `stripe.status`
  - `stripe.balance_transaction`
  - `stripe_response` completo

Al fallar:

- `estado = fallido`
- `procesado_en = now`
- metadata:
  - `error`
  - `error_details.message`
  - `error_details.at`
  - extras segun caso (`stripe_status`, `stripe_body`, etc.)

### 6.2 Job batch: ProcesarReembolsosPendientesJob

- Toma hasta `limit` (default 100) reembolsos `pendiente`
- Despacha 1 `ProcesarReembolsoJob` por cada id

### 6.3 Scheduler

En [backend/routes/console.php](backend/routes/console.php):

- `Schedule::job(new ProcesarReembolsosPendientesJob())->everyFiveMinutes()->withoutOverlapping();`

## 7) Timeline y compatibilidad de metadata

El endpoint de detalle soporta metadata historica y actual para evitar roturas:

- Refund id Stripe:
  - `stripe.refund_id`
  - `stripe_refund_id`
  - `stripe_response.id`

- Status Stripe:
  - `stripe.status`
  - `stripe_status`
  - `stripe_response.status`

- Balance tx:
  - `stripe.balance_transaction`
  - `stripe_response.balance_transaction`

- Error message:
  - `stripe.error.message`
  - `error.message`
  - `error_details.message`
  - `error` (string legacy)
  - `stripe_body.error.message`

Con esto, el timeline puede reconstruir eventos incluso con payloads legacy.

## 8) Seguridad y autorizacion

- Middleware global: `auth:sanctum`
- Guard de negocio: `isAdmin()` en cada endpoint admin de reembolsos
- No-admin recibe HTTP 403

## 9) Matriz de pruebas implementadas

### 9.1 AdminReembolsoControllerTest

Cobertura principal:

- bloqueo a no-admin
- listado con filtros
- creacion manual
- encolado de procesamiento automatico
- cierre manual
- metricas
- validacion de rango de fechas en metricas
- export CSV + filtro
- bloqueo export a no-admin
- validacion de rango de fechas en export
- detalle con timeline
- detalle bloqueado para no-admin
- timeline con metadata stripe legacy
- timeline con error string legacy

### 9.2 ProcesarReembolsoJobTest

Cobertura principal:

- exito Stripe -> `completado`
- error Stripe -> `fallido`
- batch pendientes -> dispatch por cada pendiente

### 9.3 Estado de validacion (ultimo run)

- Suite backend: 134 tests passed
- Assertions: 642
- Fallos: 0

## 10) Comandos de verificacion recomendados

Desde `backend/`:

```bash
C:/wamp64/bin/php/php8.2.26/php.exe artisan test --filter="AdminReembolsoControllerTest"
C:/wamp64/bin/php/php8.2.26/php.exe artisan test --filter="ProcesarReembolsoJobTest"
C:/wamp64/bin/php/php8.2.26/php.exe artisan test --compact
```

## 11) Observaciones operativas para Claude

1. El contrato de metadata ya tiene compatibilidad backward para timeline.
2. El flujo automatico depende de queue worker y scheduler activos.
3. Para metodo distinto a `mismo_medio_pago`, el cierre es manual por diseno.
4. Los codigos de razon/estado/metodo son strings controlados por validacion del controller.
5. Si se extiende el modelo de razones, actualizar:
   - validaciones en controller
   - doc tecnica principal
   - tests de filtros/metricas/export

## 12) Proximo nivel de evolucion (opcional)

1. Idempotencia explicita por `reembolso_uuid` en llamada Stripe (si se desea hardening adicional).
2. Tabla de auditoria dedicada para eventos de reembolso (en vez de solo metadata).
3. Politica configurable por tipo de consulta/canal para reglas de reembolso.
4. Dashboard de SLO operacional para pendientes/fallidos por ventana temporal.
