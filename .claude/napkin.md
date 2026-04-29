# Napkin Runbook

## Curation Rules
- Re-prioritize on every read.
- Keep recurring, high-value notes only.
- Max 10 items per category.
- Each item includes date + "Do instead".

## Execution & Validation (Highest Priority)

1. **[2026-04-26] PHP en WAMP es php7.4 por defecto — Laravel requiere 8.2**
   Do instead: siempre usar `/c/wamp64/bin/php/php8.2.26/php artisan ...` para migraciones y artisan.

2. **[2026-04-26] SQLite test DB puede quedar en estado inconsistente tras migración parcial**
   Do instead: si falla una migración que ya ejecutó parte de sus ALTER TABLE, verificar con `PRAGMA table_info(tabla)` antes de reintentar. Insertar en `migrations` manualmente si las columnas ya existen.

3. **[2026-04-26] `smallInteger('id', true, true)` en Laravel ya es PK — no llamar `->primary()` encima**
   Do instead: `$table->smallInteger('id', true, true);` sin `.primary()` al final.

## Shell & Command Reliability

1. **[2026-04-26] `git add` desde CWD equivocado falla con pathspec error**
   Do instead: siempre correr git desde el root del repo (`/c/wamp64/www/TarotEstrella/tarotestrellas`), no desde `frontend/` ni `backend/`.

2. **[2026-04-26] `Write` tool falla si el archivo no fue leído antes en la sesión**
   Do instead: leer el archivo con `Read` antes de intentar `Write` sobre él. Para archivos nuevos usar `Write` directamente sin leer.

## Domain Behavior Guardrails

1. **[2026-04-26] Todos los endpoints de citas usan UUID — NUNCA numeric id en URLs**
   Do instead: `where('uuid', $uuid)` siempre. En frontend pasar `cita.uuid` a los navegación links, nunca `cita.id`.

2. **[2026-04-26] `/metodos-pago` no existe — endpoint bancario correcto es `/citas/{uuid}/pagar/transferencia/datos`**
   Do instead: en cualquier página de pago, usar `fetchDatosTransferencia(citaUuid)` que llama al endpoint correcto.

3. **[2026-04-26] El campo bancario se llama `cuenta` (no `numero_cuenta`) en DatosBancarios**
   Do instead: `datosBancarios.cuenta`, nunca `datosBancarios.numero_cuenta`.

4. **[2026-04-26] `es_primera_consulta` ya existe en la migración original de citas — no agregarlo de nuevo**
   Do instead: antes de agregar columnas a citas, revisar `create_citas_table.php` para no duplicar.

## User Directives

1. **[2026-04-26] Responder siempre en español**
   Do instead: toda comunicación con el usuario en español, incluyendo commits, código comentado si aplica.

2. **[2026-04-26] Caveman mode activo — respuestas tersas**
   Do instead: fragmentos OK, sin artículos innecesarios, directo al grano. Suspender solo para advertencias destructivas.
