# Guía de despliegue en Hostinger

> Stack: Laravel 11 (PHP 8.2) + React/Vite SPA  
> Plataforma: Hostinger Business / Premium (hPanel + SSH)

---

## ⚠️ Notas de actualización (2026-05-13)

Esta guía es la versión original. La instalación real en PROD difiere en algunos puntos. Se conserva el documento por valor de referencia, pero ten en cuenta:

| Tema | Documento original | Realidad PROD actual |
|---|---|---|
| **Dominio** | `tarotestrellas.cl` + subdominio `api.tarotestrellas.cl` | `tarotestrellas.com` (TLD `.com`, sin subdominio API separado) |
| **Backend path** | `/home/u123456789/backend/public/` (subdominio independiente) | `/home/u402745362/domains/tarotestrellas.com/public_html/backend/` (mismo dominio, ruta `/backend`) |
| **Frontend path** | `public_html/` | `domains/tarotestrellas.com/public_html/` |
| **PHP** | 8.2 | **8.3** (PATH local tiene 7.4 — usar `C:\wamp64\bin\php\php8.3.14\php.exe`) |
| **Pasarela de pago** | Stripe (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `VITE_STRIPE_PUBLIC_KEY`) | **PayPal** (`PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_BASE_URL`) + Transferencia bancaria. Flow.cl comentado para Fase 2 |
| **Reload OPcache** | `php artisan config:cache` | **NO** usar `touch index.php` en `public_html/` raíz (rompió el sitio: creaba un `index.php` vacío que precedía a `index.html`). Usar `touch backend/bootstrap/app.php` o reiniciar PHP-FPM desde panel Hostinger |
| **Reset password** | Notification por defecto Laravel (inglés) | `App\Notifications\ResetPasswordNotification` custom en español, con link a `{FRONTEND_URL}/auth/reset-password?token=...` |
| **APP_LOCALE** | (no mencionado) | `es` (mensajes Laravel en español) |
| **APP_NAME** | (variable) | `TarotEstrellas` |
| **Usuario SSH** | `u123456789` (placeholder) | `u402745362` |
| **Puerto SSH** | 22 (default) | **65002** |
| **SSH desde Windows** | `ssh` | `plink.exe` y `pscp.exe` de PuTTY (`-pw '...' -batch`) |
| **Webhook Stripe** | `/api/webhooks/stripe` | Sigue existiendo legacy, pero no se usa para nuevos pagos |

> ⚠️ **Variables Stripe abajo** → ya no se usan en código nuevo (mantener solo por compatibilidad con webhook legacy si hay pagos antiguos). Reemplazar mentalmente por bloque PayPal:
> ```env
> PAYPAL_CLIENT_ID=...
> PAYPAL_CLIENT_SECRET=...
> PAYPAL_BASE_URL=https://api-m.paypal.com   # LIVE; sandbox: https://api-m.sandbox.paypal.com
> ```

> ⚠️ **`VITE_STRIPE_PUBLIC_KEY`** ya no es necesario en `.env.production` del frontend.

> ✅ Lo que sigue válido: arquitectura general (frontend SPA + Laravel backend), pasos de DB, migrate, storage:link, cron scheduler (`* * * * * php artisan schedule:run`), webhooks Daily/Resend/WhatsApp, OAuth Google.

---

## Arquitectura en producción

```
tarotestrellas.cl          →  React SPA  (public_html/)
api.tarotestrellas.cl      →  Laravel    (backend/public/)
```

El backend vive **fuera** de `public_html` por seguridad.  
El frontend es un build estático en `public_html`.

---

## Requisitos previos en Hostinger

- Plan **Business** o superior (requiere SSH y selector de PHP 8.2)
- Dominio ya apuntado a Hostinger (DNS propagado)
- SSL activado (Let's Encrypt gratuito en hPanel)

---

## PASO 1 — Preparar el código localmente

### 1.1 Actualizar `vite.config.ts`

Cambiar el base path para que funcione en dominio raíz:

```ts
// frontend/vite.config.ts
export default defineConfig(() => ({
  base: '/',          // ← siempre '/' en prod
  plugins: [react(), tailwindcss()],
}));
```

### 1.2 Crear `frontend/.env.production`

```env
VITE_API_URL=https://api.tarotestrellas.cl/api
VITE_STRIPE_PUBLIC_KEY=pk_live_XXXXXXXXXXXXXXXXXXXXXXXX
```

> ⚠️ Usa la clave **live** de Stripe (no la test).

### 1.3 Construir el frontend

```bash
cd frontend
npm install
npm run build          # genera frontend/dist/
```

Verifica que `frontend/dist/index.html` exista.

### 1.4 Actualizar `backend/config/cors.php`

```php
'allowed_origins' => [
    'https://tarotestrellas.cl',
    'https://www.tarotestrellas.cl',
],
```

### 1.5 Actualizar `backend/public/.htaccess`

Reemplaza la línea del `Header always set Access-Control-Allow-Origin` con el dominio real:

```apache
Header always set Access-Control-Allow-Origin "https://tarotestrellas.cl" "expr=%{REQUEST_STATUS} == 204"
```

### 1.6 Crear `backend/.env.production` (plantilla)

Guárdalo localmente como referencia. Lo subirás manualmente:

```env
APP_NAME="TarotEstrellas"
APP_ENV=production
APP_KEY=                          # se genera en el servidor: php artisan key:generate
APP_DEBUG=false
APP_TIMEZONE=America/Santiago
APP_URL=https://api.tarotestrellas.cl

FRONTEND_URL=https://tarotestrellas.cl

LOG_CHANNEL=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_db_name            # del paso 2.1
DB_USERNAME=tu_db_user            # del paso 2.1
DB_PASSWORD=tu_db_password        # del paso 2.1

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.tarotestrellas.cl
SANCTUM_STATEFUL_DOMAINS=tarotestrellas.cl,www.tarotestrellas.cl

GOOGLE_CLIENT_ID=tu_client_id
GOOGLE_CLIENT_SECRET=tu_secret
GOOGLE_REDIRECT_URI=https://api.tarotestrellas.cl/api/auth/google/callback

STRIPE_KEY=sk_live_XXXXXX
STRIPE_SECRET=sk_live_XXXXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXXXX

DAILY_API_KEY=tu_key
DAILY_WEBHOOK_SECRET=tu_secret
DAILY_DOMAIN=tu-dominio.daily.co

RESEND_API_KEY=re_XXXXXX
MAIL_FROM_ADDRESS=hola@tarotestrellas.cl
MAIL_FROM_NAME="TarotEstrellas"

OPENAI_API_KEY=sk-XXXXXX
ANTHROPIC_API_KEY=sk-ant-XXXXXX

WHATSAPP_VERIFY_TOKEN=tu_token
WHATSAPP_ACCESS_TOKEN=tu_access_token
```

---

## PASO 2 — Configurar Hostinger (hPanel)

### 2.1 Crear base de datos MySQL

1. `hPanel` → **Bases de datos** → **MySQL Databases**
2. Crear base de datos: `tarotestrellas_prod`
3. Crear usuario: `tarot_user` con contraseña segura
4. Asignar usuario a la base de datos con **todos los privilegios**
5. Anotar: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### 2.2 Crear subdominio `api.tarotestrellas.cl`

1. `hPanel` → **Dominios** → **Subdominios**
2. Crear subdominio: `api`
3. **Document root**: `/home/u123456789/backend/public`  
   *(reemplaza `u123456789` con tu nombre de usuario real)*

> Hostinger permite apuntar el document root a cualquier carpeta de tu cuenta.

### 2.3 Activar SSL

1. `hPanel` → **Seguridad** → **SSL/TLS**
2. Instalar certificado gratuito (Let's Encrypt) para:
   - `tarotestrellas.cl`
   - `www.tarotestrellas.cl`
   - `api.tarotestrellas.cl`
3. Activar **redirección HTTPS** para cada uno.

### 2.4 Seleccionar PHP 8.2

1. `hPanel` → **Sitios web** → **PHP Configuration**
2. Seleccionar versión **8.2**
3. Activar extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip`

---

## PASO 3 — Subir archivos

### Opción A — Git (recomendado, si tienes SSH)

```bash
# En tu máquina local — pushear el código
git push origin backend/feat/backend-avance-fase

# En el servidor (SSH)
ssh u123456789@tarotestrellas.cl

# Clonar el repo en home (NO dentro de public_html)
cd ~
git clone https://github.com/tu-usuario/tarotestrellas.git repo
# o si es privado:
git clone https://tu-token@github.com/tu-usuario/tarotestrellas.git repo

# Crear estructura
mkdir -p ~/backend
cp -r ~/repo/backend/* ~/backend/
```

### Opción B — FTP / Administrador de archivos

Sube mediante el **File Manager** de hPanel o un cliente FTP (FileZilla):

| Origen (local)             | Destino (servidor)                          |
|----------------------------|---------------------------------------------|
| `backend/`                 | `/home/u123456789/backend/`                 |
| `frontend/dist/`           | `/home/u123456789/public_html/`             |
| `frontend/dist/index.html` | `/home/u123456789/public_html/index.html`   |

> **No** subas `node_modules/`, `vendor/`, `.env`, ni el código fuente de `frontend/src/`.

---

## PASO 4 — Configurar el backend en el servidor (SSH)

Conectarse por SSH:

```bash
ssh u123456789@tarotestrellas.cl
```

### 4.1 Crear `.env` de producción

```bash
cd ~/backend
nano .env
# Pega el contenido de tu backend/.env.production (del Paso 1.6)
# Guarda: Ctrl+O, Enter, Ctrl+X
```

### 4.2 Instalar dependencias PHP

```bash
cd ~/backend
composer install --no-dev --optimize-autoloader
```

### 4.3 Generar APP_KEY

```bash
php artisan key:generate
# Copia la clave generada y ponla en .env → APP_KEY=base64:...
```

### 4.4 Ejecutar migraciones

```bash
php artisan migrate --force
```

> `--force` es necesario en `APP_ENV=production`.

### 4.5 Crear symlink de storage

```bash
php artisan storage:link
```

### 4.6 Optimizar para producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 4.7 Permisos de carpetas

```bash
chmod -R 775 ~/backend/storage
chmod -R 775 ~/backend/bootstrap/cache
```

---

## PASO 5 — Configurar el frontend (SPA routing)

El frontend es una SPA — todas las rutas deben retornar `index.html`.

Crea `/home/u123456789/public_html/.htaccess`:

```apache
Options -MultiViews
RewriteEngine On

# Si el archivo existe, servirlo directo
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Todo lo demás → index.html (React Router)
RewriteRule ^ /index.html [L]
```

---

## PASO 6 — Configurar webhooks en servicios externos

Actualiza las URLs de webhooks en los paneles de cada servicio:

| Servicio    | URL webhook                                             |
|-------------|--------------------------------------------------------|
| Stripe      | `https://api.tarotestrellas.cl/api/webhooks/stripe`    |
| Daily.co    | `https://api.tarotestrellas.cl/api/webhooks/daily`     |
| WhatsApp    | `https://api.tarotestrellas.cl/api/webhooks/whatsapp`  |
| Resend      | `https://api.tarotestrellas.cl/api/webhooks/resend`    |

> Copia los nuevos `WEBHOOK_SECRET` que genere cada servicio y actualiza el `.env`.

---

## PASO 7 — Google OAuth (si está habilitado)

En **Google Cloud Console**:

1. `APIs & Services` → `Credentials` → tu OAuth 2.0 Client
2. Agregar en **Authorized redirect URIs**:
   ```
   https://api.tarotestrellas.cl/api/auth/google/callback
   ```
3. Agregar en **Authorized JavaScript origins**:
   ```
   https://tarotestrellas.cl
   ```

---

## PASO 8 — Verificación final

### Checklist rápido

```bash
# En el servidor
curl https://api.tarotestrellas.cl/api/health
# Debe responder: {"status":"ok"}

curl https://api.tarotestrellas.cl/api/public/tipos-consulta
# Debe responder JSON con los tipos de consulta

curl https://tarotestrellas.cl
# Debe devolver el HTML del frontend (React)
```

Prueba en el navegador:
- [ ] `https://tarotestrellas.cl` → carga la landing
- [ ] `https://tarotestrellas.cl/servicios` → carga sin 404
- [ ] `https://tarotestrellas.cl/login` → formulario funcional
- [ ] Registro de usuario → recibe correo de verificación
- [ ] Reserva de cita → flujo completo hasta pago

---

## Problemas frecuentes

### 500 Internal Server Error en Laravel
```bash
tail -50 ~/backend/storage/logs/laravel.log
```

### CORS bloqueado en el navegador
- Verificar que `config/cors.php` tiene el dominio exacto (con `https://`)
- Limpiar cache: `php artisan config:clear && php artisan config:cache`

### Frontend muestra pantalla en blanco
- Abrir DevTools → Console → ver el error
- Verificar que `public_html/.htaccess` existe
- Verificar que `VITE_API_URL` apunta a `https://api.tarotestrellas.cl/api`

### Migraciones fallan
```bash
php artisan migrate:status       # ver qué migraciones están pendientes
php artisan migrate --force      # forzar en producción
```

### Storage sin acceso (fotos, comprobantes no cargan)
```bash
php artisan storage:link         # recrear symlink
ls -la ~/backend/public/storage  # debe ser un symlink
```

---

## Mantenimiento posterior

### Actualizar código (deploy continuo)

```bash
# En el servidor
cd ~/backend
git pull origin backend/feat/backend-avance-fase   # o main cuando hagas merge

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Para el frontend, reconstruir localmente y volver a subir `dist/`:
```bash
# Local
cd frontend
npm run build
# FTP: reemplazar public_html/ con el nuevo dist/
```

### Logs de producción

```bash
tail -f ~/backend/storage/logs/laravel.log
```

---

## Resumen de estructura final en el servidor

```
/home/u123456789/
├── public_html/          ← tarotestrellas.cl (frontend SPA)
│   ├── index.html
│   ├── assets/
│   └── .htaccess
├── backend/              ← api.tarotestrellas.cl (Laravel)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/           ← document root del subdominio api.
│   │   ├── index.php
│   │   ├── .htaccess
│   │   └── storage → ../storage/app/public
│   ├── storage/
│   ├── vendor/
│   └── .env
└── repo/                 ← clon git (opcional)
```
