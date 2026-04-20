# Backend Reembolsos - Brief Ejecutivo para Claude

Fecha: 2026-04-19
Proyecto: TarotEstrellas
Estado: implementado y validado

## 1) Resumen

Se cerro el backend de reembolsos de punta a punta, incluyendo:

1. Generacion automatica de reembolsos por reglas de negocio.
2. Operacion admin completa por API (CRUD operativo + metricas + export).
3. Procesamiento asincrono Stripe con jobs y scheduler.
4. Trazabilidad por metadata y timeline en detalle.
5. Cobertura de pruebas especifica y suite general en verde.

## 2) Lo mas importante (para contexto rapido)

- Motor principal admin: `ReembolsoController`.
- Procesamiento automatico: `ProcesarReembolsoJob`.
- Barrido de pendientes: `ProcesarReembolsosPendientesJob`.
- Programacion: cada 5 minutos via scheduler.
- Seguridad: todas las rutas admin requieren `auth:sanctum` + `isAdmin()`.

## 3) Endpoints clave implementados

1. `GET /api/admin/reembolsos` (listado con filtros)
2. `GET /api/admin/reembolsos/{uuid}` (detalle + timeline)
3. `GET /api/admin/reembolsos/metricas` (KPIs)
4. `GET /api/admin/reembolsos/export` (CSV)
5. `POST /api/admin/reembolsos` (creacion manual)
6. `POST /api/admin/reembolsos/{uuid}/procesar` (procesar, reintentar o marcar completado)

## 4) Flujo funcional en 3 capas

### Capa A: Origen de reembolso

- Cancelacion cliente con politica aplicable: crea reembolso pendiente (`cancelacion_24h`).
- No-show de especialista: crea reembolsos pendientes por pagos completados (`cancelacion_chachita`).

### Capa B: Operacion admin

- Admin puede filtrar, inspeccionar timeline, exportar, crear y cerrar/reintentar.
- Reembolsos de metodo manual se cierran por `marcar_completado`.

### Capa C: Ejecucion automatica

- Si `metodo == mismo_medio_pago`, se encola job Stripe.
- Job valida precondiciones, llama `POST /v1/refunds`, y persiste resultado.
- Scheduler dispara barrido de pendientes cada 5 minutos.

## 5) Estructura de datos (tabla reembolsos)

Campos funcionales principales:

- Identidad: `uuid`
- Relaciones: `cita_id`, `cliente_id`, `pago_id`
- Negocio: `monto_centavos`, `moneda`, `estado`, `razon`, `metodo`
- Ciclo de vida: `solicitado_en`, `procesado_en`
- Auditoria flexible: `metadata` (json)
- Soft delete habilitado

Estados operativos usados:

- `pendiente`
- `completado`
- `fallido`

## 6) Metadata y timeline (punto clave)

El detalle de reembolso construye timeline soportando metadata nueva y legacy. Eventos:

1. `creado`
2. `reintento`
3. `procesado_manual`
4. `procesado_stripe`
5. `fallo_procesamiento`

Esto evita perdida de trazabilidad aunque cambie el formato historico de metadata.

## 7) Validacion ejecutada

Resultado final reportado:

- Backend suite: 134 tests passed
- Assertions: 642
- Fallos: 0

Tests directos del modulo:

- `AdminReembolsoControllerTest`
- `ProcesarReembolsoJobTest`

## 8) Riesgos controlados y decisiones

1. Autorizacion fuerte en endpoints admin (403 para no-admin).
2. Rango de fechas invalidas responde 422 en metricas/export.
3. Reembolso automatico solo para metodo `mismo_medio_pago`.
4. Cierre manual forzado para metodos no automaticos.
5. Compatibilidad backward en lectura de metadata Stripe/error.

## 9) Archivos de referencia para Claude

- `backend/routes/api.php`
- `backend/routes/console.php`
- `backend/app/Http/Controllers/Api/ReembolsoController.php`
- `backend/app/Http/Controllers/Api/CitaController.php`
- `backend/app/Jobs/ProcesarReembolsoJob.php`
- `backend/app/Jobs/ProcesarReembolsosPendientesJob.php`
- `backend/app/Models/Reembolso.php`
- `backend/database/migrations/2026_04_19_100000_create_reembolsos_table.php`
- `backend/tests/Feature/AdminReembolsoControllerTest.php`
- `backend/tests/Feature/ProcesarReembolsoJobTest.php`

## 10) Prompt sugerido para Claude

"Toma este brief y revisa consistencia end-to-end del modulo de reembolsos (contratos API, validaciones, metadata timeline, jobs Stripe y cobertura de tests). Si detectas gaps de hardening o maintainability, propone cambios minimos y priorizados sin romper compatibilidad."