# TarotEstrellas — Especificación Técnica

**Proyecto:** TarotEstrellas
**Dominio:** tarotestrellas.com
**Rubro:** Plataforma de consultas de tarot, astrología, carta astral y prácticas afines
**Especialista inicial:** Chachita (Chile)
**Desarrollado por:** Automatizatech
**Última actualización:** 16 de abril de 2026
**Versión del documento:** 1.0

---

## Tabla de Contenidos

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Decisiones Clave](#2-decisiones-clave)
3. [Arquitectura Técnica](#3-arquitectura-técnica)
4. [Modelo de Datos](#4-modelo-de-datos)
5. [Módulos Funcionales](#5-módulos-funcionales)
6. [Identidad Visual y UX](#6-identidad-visual-y-ux)
7. [API REST](#7-api-rest)
8. [Plan de Implementación por Fases](#8-plan-de-implementación-por-fases)
9. [Configuración de Servicios Externos](#9-configuración-de-servicios-externos)
10. [Documentos Legales](#10-documentos-legales)
11. [Checklist de Lanzamiento](#11-checklist-de-lanzamiento)

---

## 1. Resumen Ejecutivo

**TarotEstrellas** es una plataforma web (y móvil en Fase 2) que permite a Chachita, especialista en tarot, astrología, carta astral y prácticas afines radicada en Chile, ofrecer consultas en vivo por videollamada a clientes de todo el mundo (principalmente Chile, Venezuela, USA, México y España).

**Problema que resuelve:** hoy Chachita coordina sus consultas por WhatsApp, cobra por transferencia manual, no tiene registro estructurado de sus clientes ni historial de sesiones, y pierde tiempo en tareas administrativas que podrían automatizarse. Los clientes internacionales tienen dificultades para pagar en su moneda y coordinar horarios por diferencias de zona horaria.

**Solución:** una plataforma integral que automatiza todo el ciclo: el cliente se registra, explora el catálogo de 12 tipos de consulta, agenda en su zona horaria local, paga con tarjeta internacional (Stripe) o transferencia bancaria (validada automáticamente con IA), entra a una videollamada grabada dentro de la app, y recibe transcripción + resumen IA de cada sesión. Un agente de inteligencia artificial mantiene el historial completo de cada cliente para que tanto Chachita como el cliente puedan consultarlo en cualquier momento.

**Stack tecnológico:** Laravel 11 (backend) + React con TypeScript (frontend) + MariaDB + Redis, corriendo en un VPS KVM 2 de Hostinger con Cloudflare como proxy frontal. Integraciones: Stripe (pagos), Daily.co (video), OpenAI Whisper (transcripción), Anthropic Claude (agente IA y resúmenes), Resend (email), Meta WhatsApp Cloud API o YCloud (WhatsApp).

**Modelo de negocio:** el cliente paga 20% de abono al agendar y 80% antes de la sesión. Chachita recibe el dinero en su cuenta bancaria chilena. Membresía anual disponible (12 consultas por el precio de 2). Cupones y descuento de primera consulta (10%) parametrizables.

**Costo operativo mensual estimado:** ~$25-40 USD en servicios de IA + ~5% de comisión Stripe sobre pagos internacionales. La mayoría de servicios (Daily, R2, Resend, Sentry, Umami) están en tiers gratuitos suficientes para el volumen inicial.

**Timeline:** Fase 0 (2 semanas de setup) + Fase 1 MVP (16 semanas, part-time 3-4h/día) = lanzamiento en ~4.5 meses. Fase 2 (app móvil iOS/Android + agente IA avanzado) en +6 semanas posteriores.

**Desarrollado por:** Luis Miguel, Automatizatech, con asistencia de Claude AI (Anthropic).

**Dominio:** tarotestrellas.com (registrado).

---

## 2. Decisiones Clave

### Decisión 16 — Validación automática de transferencias bancarias (clientes Chile)

Los clientes chilenos que eligen pagar por transferencia bancaria pasan por un **agente automático de validación** que verifica el comprobante de transferencia subido por el cliente.

**El agente aplica 5 reglas de validación simultáneas:**

1. **Cuenta destino correcta**: la transferencia debe haber sido hecha a la cuenta bancaria exacta que Chachita tiene registrada en el sistema (banco + número de cuenta + RUT receptor).
2. **Monto igual o superior al acordado**: ≥ al 20% (abono) o al 80% (saldo) según la etapa. Excedentes quedan como crédito para próximas consultas.
3. **Código de referencia único**: cada cita genera un código `TE-XXXX-YYYY` que el cliente debe incluir en la glosa/mensaje de la transferencia.
4. **Unicidad del ID de transacción**: cada transferencia se usa una sola vez; si el ID ya fue usado para validar otra cita, se rechaza como fraude.
5. **Ventana temporal de 30 minutos**: la transferencia debe haber sido hecha dentro de los últimos 30 minutos (hora Chile) antes de subir el comprobante.

**Flujo operativo:**

```
Cliente agenda cita en transferencia →
  sistema genera código TE-XXXX-YYYY y muestra datos bancarios + countdown 30 min →
Cliente transfiere con el código en la glosa →
Cliente sube comprobante (foto o PDF) →
Agente IA (Claude con visión) ejecuta OCR + 5 validaciones en paralelo →
  ├─ Todas OK → ✅ Aprueba automático, cita queda reservada/confirmada
  ├─ Algunas OK, código ausente → busca cita única pendiente del cliente y decide
  └─ Falla crítica → ❌ Rechaza + alerta a Chachita si hay indicio de fraude
```

**Salvaguardas UX:**

- Aviso claro al inicio: *"Necesitarás 30 minutos para completar el pago. Ten tu app bancaria lista."*
- Botón "Extender 10 minutos" (1 vez por reserva).
- Email + WhatsApp al expirar el tiempo si no completó.
- La duración (30 min) es parametrizable desde el admin por Chachita.

**Política de match flexible (Opción B aprobada):**

Si el cliente omite el código de referencia pero todos los otros datos coinciden (monto exacto + cuenta correcta + ventana 30 min), el agente busca citas pendientes del cliente:
- 1 cita pendiente → aprueba automático
- Varias citas pendientes → marca para revisión manual de Chachita
- 0 citas pendientes → rechaza (transferencia sin cita asociada)

**Tecnología OCR**: Claude API con capacidad de visión (Anthropic). Costo aproximado: $0.01 USD por validación, despreciable.

**Casos registrados con auditoría completa**: cada validación queda con timestamp, datos extraídos, decisión tomada, razón del rechazo/aprobación, y si hubo revisión manual posterior.

---

[Otras decisiones pendientes de consolidar de las 30 tomadas — se completará al final]

---

## 3. Arquitectura Técnica

### 3.1 Visión general del sistema

TarotEstrellas es una plataforma web (Fase 1) y multiplataforma móvil (Fase 2) que conecta a una especialista esotérica (inicialmente Chachita, diseñada para soportar múltiples especialistas en el futuro) con clientes internacionales que agendan, pagan y realizan consultas en vivo por videollamada. Cada sesión se graba, se transcribe automáticamente, y alimenta un agente de inteligencia artificial que preserva el contexto de toda la relación cliente-especialista.

El sistema se construye con una arquitectura moderna de tres capas claramente separadas:

- **Capa de presentación**: aplicación de página única (SPA) en React que corre en el navegador del cliente y, en Fase 2, empaquetada con Capacitor para iOS y Android. Panel admin separado construido sobre Filament.
- **Capa de aplicación**: backend API REST construido en Laravel 11 que expone todos los recursos de negocio, procesa pagos, orquesta webhooks y mueve trabajo pesado a colas asíncronas.
- **Capa de datos e infraestructura**: base de datos relacional MariaDB, cache y colas en Redis, almacenamiento de objetos en Cloudflare R2, todo corriendo en un VPS KVM 2 con Cloudflare como proxy frontal.

El principio rector es la **separación de responsabilidades**: el backend no renderiza HTML (solo expone JSON), el frontend no toca lógica de negocio (solo consume la API), y los servicios externos se aíslan detrás de capas de abstracción para poder migrar de proveedor sin reescribir código.

### 3.2 Diagrama de arquitectura

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            USUARIOS FINALES                                 │
│                                                                             │
│   Cliente (Web)        Chachita (Admin)       Cliente (iOS/Android F2)      │
│        │                     │                          │                   │
└────────┼─────────────────────┼──────────────────────────┼───────────────────┘
         │                     │                          │
         ▼                     ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      CLOUDFLARE (PROXY FRONTAL)                             │
│                                                                             │
│   • CDN global        • Protección DDoS        • SSL/TLS                    │
│   • Rate limiting     • Cache estático         • Firewall WAF               │
└─────────────────────────────┬───────────────────────────────────────────────┘
                              │ HTTPS
                              ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    VPS KVM 2 — tarotestrellas.com                              │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                          NGINX (443/80)                              │   │
│  └─────────┬──────────────────┬──────────────────┬─────────────────────┘   │
│            │                  │                  │                          │
│            ▼                  ▼                  ▼                          │
│  ┌─────────────────┐ ┌────────────────┐ ┌────────────────┐                 │
│  │ Landing + SPA   │ │  API Laravel   │ │  Admin Filament│                 │
│  │   /             │ │  /api/*        │ │  /admin        │                 │
│  │  (React build)  │ │  (PHP-FPM)     │ │  (PHP-FPM)     │                 │
│  └─────────────────┘ └───────┬────────┘ └────────┬───────┘                 │
│                              │                    │                         │
│                              ▼                    ▼                         │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                   CAPA DE SERVICIOS (Laravel)                        │   │
│  │                                                                      │   │
│  │  AgendamientoService  PagoService      VideoService                  │   │
│  │  TranscripcionService NotificacionSvc  AgenteIAService               │   │
│  └─────────┬──────────────────┬──────────────────┬─────────────────────┘   │
│            │                  │                  │                          │
│            ▼                  ▼                  ▼                          │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────────────────┐    │
│  │   MariaDB      │  │     Redis      │  │   Queue Workers            │    │
│  │                │  │                │  │   (Horizon + Supervisor)   │    │
│  │  • usuarios    │  │  • cache       │  │                            │    │
│  │  • citas       │  │  • sesiones    │  │  • enviar_email            │    │
│  │  • pagos       │  │  • colas       │  │  • procesar_transcripcion  │    │
│  │  • consultas   │  │                │  │  • generar_resumen_ia      │    │
│  │  • transcrip.  │  │                │  │  • recordatorios           │    │
│  └────────────────┘  └────────────────┘  └────────────────────────────┘    │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │              CRON / SCHEDULER (cada minuto)                          │   │
│  │  • Recordatorios 48h/3h/30min  • Limpieza videos >3 meses           │   │
│  │  • Reportes diarios             • Ejecutar jobs programados          │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼  (webhooks entrantes y llamadas API salientes)
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SERVICIOS EXTERNOS                                   │
│                                                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │  Stripe  │  │  Daily.co│  │  OpenAI  │  │ Anthropic│  │  Resend  │      │
│  │  (pagos) │  │  (video) │  │ (Whisper)│  │ (Claude) │  │  (email) │      │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘  └──────────┘      │
│                                                                             │
│  ┌──────────┐  ┌──────────────┐  ┌────────┐  ┌─────────┐                   │
│  │ YCloud/  │  │ Cloudflare R2│  │ Sentry │  │  Umami  │                   │
│  │Meta WA   │  │  (storage)   │  │(errores)│  │(analit.)│                   │
│  └──────────┘  └──────────────┘  └────────┘  └─────────┘                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.3 Stack tecnológico completo

**Backend (sobre VPS KVM 2):**

| Componente | Tecnología | Versión | Propósito |
|------------|-----------|---------|-----------|
| Lenguaje | PHP | 8.3 | Runtime del backend |
| Framework | Laravel | 11.x | Framework principal |
| Servidor web | Nginx | 1.24+ | Reverse proxy y servidor HTTP |
| Proceso PHP | PHP-FPM | 8.3 | Gestor de procesos PHP |
| Base de datos | MariaDB | 10.11+ | Persistencia estructurada |
| Cache y colas | Redis | 7.x | Cache, sesiones, colas asíncronas |
| Monitor de colas | Laravel Horizon | — | Dashboard y supervisión de jobs |
| Autenticación API | Laravel Sanctum | — | Tokens para frontend SPA y móvil |
| Login social | Laravel Socialite | — | Google (F1), Apple + Facebook (F2) |
| Pasarela pagos | Laravel Cashier (Stripe) | — | SDK oficial Stripe |
| Panel admin | Filament | 3.x | Admin panel para Chachita |
| Proceso manager | Supervisor | — | Mantiene workers corriendo |

**Frontend cliente (SPA):**

| Componente | Tecnología | Versión | Propósito |
|------------|-----------|---------|-----------|
| Framework UI | React | 18+ | Librería principal |
| Build tool | Vite | 5.x | Bundler y dev server |
| Lenguaje | TypeScript | 5.x | Tipado estático |
| Estilos | Tailwind CSS | 3.x | Sistema de diseño utility-first |
| Animaciones 2D | Framer Motion | 11.x | Transiciones y gestos |
| Animaciones 3D | Three.js + React Three Fiber | — | Escenas 3D (cartas, constelaciones) |
| Estado global | Zustand | 4.x | Gestión simple de estado |
| Llamadas API | TanStack Query | 5.x | Cache, re-fetching, optimistic updates |
| Formularios | React Hook Form + Zod | — | Validación tipada |
| Videollamada | @daily-co/daily-js | — | SDK de Daily.co |
| Empaquetado móvil (F2) | Capacitor | 6.x | Wrapper nativo iOS/Android |
| Iconos | Lucide React | — | Set de iconos consistente |
| Routing | React Router | 6.x | Navegación client-side |

**Servicios externos (SaaS):**

| Servicio | Uso | Plan inicial | Costo estimado mensual |
|----------|-----|--------------|------------------------|
| Stripe | Pagos internacionales | Pay-as-you-go | ~5% de transacciones internacionales |
| Daily.co | Videollamadas + grabación | Free tier | $0 hasta 10k min/mes |
| Cloudflare R2 | Storage videos/audios/backups | Free tier | $0 hasta 10 GB; luego $0.015/GB |
| Cloudflare (proxy) | CDN + DDoS + WAF | Free | $0 |
| OpenAI | Whisper (transcripción) | API | ~$11 USD a volumen Chachita |
| Anthropic | Claude (agente + resúmenes) | API | ~$10 USD a volumen Chachita |
| Resend | Email transaccional | Free tier | $0 hasta 3k emails/mes |
| Meta WhatsApp Cloud API / YCloud | WhatsApp Business API | Pay-as-you-go | ~$0.03-0.08 USD por conversación (varía por país) |
| Sentry | Monitoreo de errores | Developer (free) | $0 hasta 5k errores/mes |
| Umami | Analytics | Self-hosted gratis | $0 |
| GitHub | Repositorio + CI/CD | Free | $0 |

**Total estimado servicios externos a volumen inicial**: ~$25-40 USD/mes (variable según volumen de consultas y conversaciones WhatsApp).

### 3.4 Infraestructura

**3.4.1 VPS KVM 2 (Hostinger)**

Es el corazón operativo del sistema. Aloja todo excepto las grabaciones (que van a R2) y los servicios externos SaaS.

Especificaciones aproximadas (verificar exactas en panel Hostinger):
- 2 vCPU
- 8 GB RAM
- 100 GB SSD
- Ubuntu 24.04 LTS
- Acceso root vía SSH

Software instalado:
- Nginx, PHP 8.3 + FPM, MariaDB, Redis, Composer, Node.js + npm, Supervisor, Certbot (Let's Encrypt), UFW firewall, Fail2ban, Git.

Estructura de directorios en el VPS:
```
/var/www/tarotestrellas/
├── current → symlink a release activa
├── releases/
│   ├── 20260416120000/
│   ├── 20260415093000/
│   └── ...
├── shared/
│   ├── .env
│   ├── storage/
│   └── uploads-temp/
└── logs/
```

El despliegue mantiene las últimas 5 releases para rollback rápido.

**3.4.2 Cloudflare (proxy + DNS)**

Cloudflare actúa como proxy frontal. Todo el tráfico pasa por Cloudflare antes de llegar al VPS. Beneficios:
- Oculta la IP real del VPS
- Absorbe ataques DDoS
- Cachea assets estáticos (imágenes, JS, CSS) en ubicaciones cercanas al usuario
- Provee SSL/TLS gratis (además del Let's Encrypt del VPS)
- Firewall WAF con reglas personalizables
- Rate limiting global configurable

DNS configurado en Cloudflare (registros A/CNAME apuntando al VPS, con proxy "naranja" activado).

**3.4.3 Cloudflare R2 (object storage)**

Almacena todo lo que no entra bien en base de datos:
- Grabaciones de video de consultas (retención 3 meses)
- Archivos de audio extraídos para transcripción
- PDFs de recibos generados
- Backups diarios comprimidos de la base de datos
- Avatares e imágenes subidas por usuarios

R2 tiene una ventaja clave sobre S3 de AWS: **egreso gratuito** (no se cobra descarga). Esto es fundamental porque los clientes van a descargar videos y reproducir audios muchas veces.

Estructura de buckets:
```
tarotestrellas-media/
├── videos/
│   └── {consulta_id}/grabacion.mp4
├── audios/
│   └── {consulta_id}/audio.mp3
├── avatars/
│   └── {user_id}/avatar.webp
└── recibos/
    └── {pago_id}/recibo.pdf

tarotestrellas-backups/
├── db/
│   └── YYYY-MM-DD_HHMMSS.sql.gz
└── storage/
    └── YYYY-MM-DD_HHMMSS.tar.gz
```

**3.4.4 GitHub (repositorio y CI/CD)**

Un único repositorio privado en GitHub (monorepo) con esta estructura:
```
tarotestrellas/
├── backend/        # Laravel
├── frontend/       # React SPA
├── shared/         # Tipos TypeScript compartidos, constantes
├── docs/           # Este documento, diagramas, ADRs
├── scripts/        # Scripts de deploy y mantenimiento
└── .github/
    └── workflows/  # GitHub Actions (CI/CD)
```

Flujo de ramas:
- `main` → producción (auto-deploy al VPS al hacer push)
- `develop` → integración continua (tests automáticos)
- `feature/*` → ramas de feature
- `hotfix/*` → correcciones urgentes

### 3.5 Flujos de datos críticos

**3.5.1 Flujo de agendamiento y pago**

```
1. Cliente elige "Tarot" en catálogo → Frontend llama GET /api/consultas/tipos
2. Cliente elige fecha/hora → Frontend llama GET /api/disponibilidad?fecha=X&duracion=120min
3. Backend calcula slots disponibles en hora de Chile, los convierte a zona horaria del cliente
4. Cliente confirma slot → POST /api/citas crea cita en estado "pendiente_abono"
5. Backend crea PaymentIntent en Stripe por el 20% → devuelve client_secret
6. Frontend muestra Stripe Elements, cliente paga
7. Stripe envía webhook payment_intent.succeeded → POST /api/webhooks/stripe
8. Backend verifica firma, actualiza cita a "reservada", envía email + WhatsApp de confirmación
9. Backend programa jobs: recordatorio 48h, 3h, 30min antes
10. 24h antes: backend envía recordatorio de pago del 80% → cliente paga segundo PaymentIntent
11. Cita pasa a estado "confirmada", se genera sala Daily.co y URL única
```

**3.5.2 Flujo de consulta en vivo + grabación**

```
1. A la hora de la cita, cliente y Chachita reciben notificación "sala lista"
2. Frontend verifica que la cita esté "confirmada" (100% pagado) → habilita botón "Entrar"
3. Cliente hace click → muestra checkbox consentimiento de grabación
4. Cliente acepta → frontend inicializa SDK Daily.co, abre sala embebida
5. Daily.co graba automáticamente (configuración pre-seteada)
6. Al finalizar la sesión, Daily.co procesa la grabación (~2-5 min)
7. Daily.co sube directo a Cloudflare R2 (configurado vía API)
8. Daily.co envía webhook recording.ready → POST /api/webhooks/daily
9. Backend guarda URL de R2 en tabla `grabaciones` vinculada a la cita
10. Backend encola job ProcesarTranscripcion → queue worker lo toma
11. Worker descarga audio de R2, llama Whisper API, obtiene transcripción
12. Worker guarda transcripción en tabla `transcripciones`
13. Worker encola GenerarResumen → Claude Haiku genera resumen de 3 párrafos
14. Worker actualiza índice de embeddings para búsqueda semántica
15. Cliente recibe email "Tu transcripción está lista" con link al historial
```

**3.5.3 Flujo del agente IA conversacional**

```
1. Chachita abre perfil del cliente María → UI muestra botón "Preguntar al agente"
2. Chachita escribe: "¿Qué temas recurrentes trae María?"
3. Frontend llama POST /api/agente/consultar con cliente_id + pregunta
4. Backend recupera todas las transcripciones y resúmenes de María
5. Backend arma prompt con contexto (rol: asistente de especialista esotérica) + historial
6. Backend llama Claude Sonnet API con el prompt
7. Claude responde con síntesis (ej: "María consulta recurrentemente sobre...")
8. Backend guarda la interacción en tabla `agente_conversaciones` (auditoría)
9. Frontend muestra respuesta con streaming en tiempo real
```

**3.5.4 Flujo de manejo de zonas horarias**

Todo el sistema almacena fechas en UTC en la base de datos. La conversión ocurre en los bordes:

- **Entrada (usuario crea cita)**: Frontend envía hora local + timezone IANA (ej: "America/Santiago") → Backend convierte a UTC antes de persistir.
- **Salida (usuario lee cita)**: Backend devuelve UTC → Frontend convierte a timezone del usuario usando su perfil.
- **Chachita siempre ve hora Chile** independiente de dónde esté físicamente.
- Las reglas de disponibilidad de Chachita se definen en hora de Chile y el cálculo de slots disponibles se hace en hora de Chile, convirtiendo solo al presentar al cliente.

Librería usada: `date-fns-tz` en frontend, `Carbon` en backend (Laravel).

### 3.6 Seguridad y autenticación

**3.6.1 Autenticación de usuarios**

El backend usa **Laravel Sanctum** para autenticación de APIs. Flujo:

1. Usuario hace login (email + password, o login social) → `POST /api/auth/login`
2. Backend verifica credenciales, genera token Bearer
3. Frontend guarda token (localStorage en web, secure storage en Capacitor)
4. Todas las requests siguientes incluyen header `Authorization: Bearer {token}`
5. Tokens expiran a los 30 días de inactividad (configurable)
6. Logout → `POST /api/auth/logout` → revoca el token en backend

**2FA opcional**: cliente puede activarlo desde su perfil. Usa TOTP (Google Authenticator, Authy).

**Verificación de email**: obligatoria antes de poder agendar primera consulta.

**Autenticación de Chachita (admin)**: usa el login estándar de Filament (usuario + password + 2FA obligatorio). Panel admin protegido con middleware de rol `admin`.

**3.6.2 Seguridad de endpoints**

- **Rate limiting**: 60 requests/minuto por IP para endpoints públicos, 120/minuto para usuarios autenticados. Configurado en Laravel + refuerzo en Cloudflare.
- **CORS**: configurado para permitir solo orígenes autorizados (dominio de producción + localhost en desarrollo).
- **Validación de entrada**: toda request valida contra Form Requests de Laravel (esquemas estrictos).
- **SQL injection**: Eloquent ORM usa prepared statements siempre.
- **XSS**: React escapa por defecto, más Content Security Policy en Nginx.
- **CSRF**: endpoints de escritura protegidos con tokens CSRF (excepto webhooks, que validan firma).

**3.6.3 Validación de webhooks**

Los webhooks entrantes son un vector de ataque común. Cada proveedor se valida:

- **Stripe**: verificación de firma HMAC-SHA256 con `stripe-signature` header usando endpoint secret.
- **Daily.co**: verificación de firma HMAC con webhook secret configurado en dashboard Daily.
- **Meta WhatsApp / YCloud**: verificación de firma del webhook con app secret de Meta o API key de YCloud.

Endpoints de webhooks están en rutas separadas (`/api/webhooks/*`) sin autenticación de usuario, pero con middleware de validación de firma.

**3.6.4 Cifrado y secretos**

- **En tránsito**: TLS 1.3 obligatorio (Cloudflare + Let's Encrypt).
- **En reposo**:
  - Contraseñas: hash bcrypt (cost 12)
  - Tokens de terceros (OpenAI keys, Stripe keys): cifrados con Laravel `encrypt()` usando APP_KEY
  - Datos personales sensibles (fecha nacimiento, hora nacimiento, lugar): cifrados a nivel de columna usando Laravel Encrypted Casts
- **Archivos en R2**: cifrado en reposo (SSE-S3) habilitado por Cloudflare por defecto.
- **Variables de entorno**: archivo `.env` en VPS con permisos 600, nunca versionado en Git.
- **Secretos de CI/CD**: guardados en GitHub Secrets.

**3.6.5 Política de consentimiento y privacidad (GDPR)**

- Checkbox obligatorio de ToS y Política de Privacidad al registro.
- Checkbox granular de consentimientos (grabación, transcripción, marketing, mejora de IA).
- Antes de cada videollamada: reconfirmación de consentimiento de grabación.
- Endpoints de derechos GDPR:
  - `GET /api/me/datos-personales` — exportación completa en JSON
  - `DELETE /api/me/cuenta` — derecho al olvido (borra datos respetando obligaciones legales)
  - `PATCH /api/me/consentimientos` — revocar consentimientos granularmente

### 3.7 Escalabilidad y performance

**3.7.1 Capacidad inicial vs objetivo**

El VPS KVM 2 con el stack propuesto puede manejar cómodamente:
- ~500 usuarios concurrentes navegando
- ~50 requests/segundo sostenidos
- ~50 consultas por día (con video + transcripción)

Esto excede por mucho las necesidades de Chachita (20 consultas/mes) y da margen para crecimiento significativo antes de requerir escalar.

**3.7.2 Estrategias de performance**

- **Cache agresivo en Redis**:
  - Catálogo de consultas (TTL 1 hora, invalidado al editar)
  - Slots disponibles (TTL 5 minutos, invalidado al agendar)
  - Sesiones de usuario
- **Cache HTTP en Cloudflare**: assets estáticos, imágenes, fuentes (cache 1 año con hash en nombre).
- **Lazy loading en frontend**: componentes pesados (Three.js) cargan solo cuando se necesitan.
- **Imágenes optimizadas**: formato WebP, tamaños responsive, lazy loading nativo.
- **Queries N+1 evitadas**: Eloquent eager loading (`with()`) estandarizado.
- **Índices de base de datos**: en todas las foreign keys, fechas consultadas, y columnas de búsqueda.

**3.7.3 Trabajo asíncrono**

Todo lo que tarda más de 200ms se mueve a colas Redis:
- Envío de emails
- Envío de WhatsApp
- Procesamiento de transcripciones (minutos)
- Generación de resúmenes IA
- Procesamiento de webhooks pesados
- Limpieza programada de videos vencidos

Horizon muestra dashboard con todas las colas y workers en tiempo real, accesible en `/admin/horizon`.

**3.7.4 Plan de escalado (si llega el momento)**

Si el volumen crece significativamente (ej: 10+ especialistas, 500+ consultas/mes):
1. Separar base de datos a VPS dedicado
2. Sumar read replicas de MariaDB
3. Mover workers a VPS separado
4. Usar Cloudflare Images en vez de servir desde R2
5. Upgrade a VPS KVM 4 u 8, o migración a arquitectura cloud (AWS/GCP)

### 3.8 Monitoreo y logging

**3.8.1 Sentry (errores)**

Integrado tanto en backend (`sentry/sentry-laravel`) como frontend (`@sentry/react`). Captura:
- Excepciones no manejadas con stack trace completo
- Contexto del usuario (sin datos sensibles) y de la request
- Performance traces de endpoints lentos
- Breadcrumbs de navegación del usuario antes del error

Alertas por email al equipo (tú) cuando se detecta un nuevo error en producción.

**3.8.2 Logs de aplicación**

Laravel genera logs estructurados (JSON) en `/var/www/tarotestrellas/shared/storage/logs/`. Niveles:
- `debug` / `info`: flujo normal, solo en desarrollo
- `warning`: situaciones no críticas (retry de webhook, deprecación, etc.)
- `error`: excepciones manejadas
- `critical`: requieren atención inmediata

Rotación diaria, retención 14 días local + backup mensual a R2.

**3.8.3 Logs de Nginx**

- `/var/log/nginx/access.log` — toda petición (formato combinado)
- `/var/log/nginx/error.log` — errores de configuración y upstream
- Rotación semanal con `logrotate`

**3.8.4 Umami (analytics)**

Auto-hosteado en un subdirectorio del VPS o como SaaS (tiene tier gratuito). Trackea:
- Páginas vistas, referrers, países, dispositivos
- Eventos custom: registro completado, consulta agendada, pago iniciado, pago exitoso, videollamada iniciada
- **Respeta GDPR**: sin cookies, sin fingerprinting, sin compartir datos con terceros

**3.8.5 Health checks**

Endpoint `/api/health` devuelve:
- Estado de MariaDB (conexión y latencia)
- Estado de Redis
- Estado de workers (últimos jobs procesados)
- Estado de servicios externos críticos (Stripe, Daily, OpenAI, Anthropic)

Monitoreado externamente con UptimeRobot (tier gratuito, check cada 5 minutos). Alerta por email si el sistema cae.

### 3.9 Decisiones de arquitectura registradas (ADRs resumidos)

Esta sección resume las decisiones arquitectónicas clave y su razón de ser. Útil para entender el "por qué" detrás de cada elección.

| # | Decisión | Razón |
|---|----------|-------|
| ADR-01 | Laravel sobre WordPress | Laravel es framework moderno con soporte nativo para APIs, colas, webhooks y integraciones complejas. WordPress sería un parche de plugins frágil. |
| ADR-02 | MariaDB sobre PostgreSQL | MariaDB es 100% compatible con el stack Laravel default y funciona out-of-the-box en Hostinger VPS. |
| ADR-03 | Cloudflare R2 sobre AWS S3 | R2 no cobra egreso, crítico para reproducción repetida de videos. Precio competitivo, API compatible con S3. |
| ADR-04 | Daily.co sobre Jitsi self-hosted | Daily.co tiene tier gratuito suficiente + grabación y transcripción integradas. Jitsi requeriría más VPS e ingeniería. |
| ADR-05 | Separación frontend/backend | Permite empaquetar frontend en Capacitor sin tocar backend. Desacople facilita cambios independientes. |
| ADR-06 | Single domain con paths sobre subdominios | Menos complejidad DNS/SSL para solo-developer. Suficiente para el volumen esperado. |
| ADR-07 | Filament para admin sobre custom React | Ahorra semanas de desarrollo. Chachita obtiene UI profesional inmediatamente. |
| ADR-08 | Whisper + Claude combinado | Whisper es el mejor transcriptor, Anthropic no lo ofrece. Claude es superior en contextos largos multisession. |
| ADR-09 | Arquitectura preparada multi-especialista desde día 1 | Cambio futuro será trivial (activar flujos) en vez de reescritura. |
| ADR-10 | UTC en BD, conversión en bordes | Estándar de la industria, evita bugs sutiles con DST y viajes del usuario. |

---

## 4. Modelo de Datos

### 4.1 Principios de diseño

El modelo de datos de TarotEstrellas sigue estos principios:

**Convenciones generales:**
- Nombres de tablas en plural, snake_case (`users`, `tipos_consulta`, `comprobantes_transferencia`).
- Nombres de columnas en snake_case.
- Toda tabla tiene `created_at` y `updated_at` (timestamps de Laravel).
- Foreign keys con naming `{tabla_singular}_id` (ej: `user_id`, `cita_id`).
- Campos monetarios almacenados en **enteros** (centavos/pesos) para evitar errores de punto flotante.
- Campos de fecha/hora en **UTC** siempre; la zona horaria se aplica en presentación.
- Campos sensibles (datos natales, tokens, comprobantes) **cifrados a nivel columna** usando Laravel Encrypted Casts.

**Estrategia de IDs (mixta, aprobada):**
- **UUIDs** para entidades expuestas en URLs públicas o compartidas externamente:
  `citas`, `pagos`, `grabaciones`, `transcripciones`, `sesiones_video`, `comprobantes_transferencia`, `reembolsos`, `agente_conversaciones`, `membresias`.
- **Enteros autoincrementales** para tablas internas o de configuración:
  `users`, `roles`, `tipos_consulta`, `paquetes`, `cupones`, `plantillas_notificacion`, `configuracion_sistema`, `audit_log`.

**Soft deletes (selectivo, aprobado):**
- **Activado** en: `users`, `citas`, `pagos`, `grabaciones`, `transcripciones`, `tipos_consulta`, `paquetes`, `cupones`, `membresias`, `sesiones_video`.
- **Desactivado** en (append-only): `audit_log`, `notificaciones_enviadas`, `validaciones_agente`, `jobs_fallidos`, `embeddings_transcripciones`, `citas_estados_historial`.

**Índices y performance:**
- Índice en todas las foreign keys.
- Índice compuesto en columnas frecuentes de búsqueda (ej: `citas.inicio_utc + citas.estado`).
- Índice único en columnas de lookup (ej: `users.email`, `cupones.codigo`).

### 4.2 Diagrama de entidades principales

```
                          ┌──────────────┐
                          │    users     │
                          └──────┬───────┘
                                 │ 1
        ┌────────────────────────┼────────────────────────┐
        │ 1                      │ 1                      │ 1
        ▼                        ▼                        ▼
┌──────────────┐         ┌──────────────┐         ┌─────────────────┐
│user_profiles │         │datos_natales │         │consentimientos  │
└──────────────┘         └──────────────┘         └─────────────────┘
        │ 1
        │ N
        ▼
┌──────────────────────────────────────────────────────────┐
│                         citas                             │
│ cliente_id, especialista_id, tipo_consulta_id, estado... │
└──┬─────────┬──────────────┬─────────────┬────────────────┘
   │ 1      │ 1            │ 1           │ 1
   │ N      │ N            │ 1           │ N
   ▼        ▼              ▼             ▼
┌─────┐ ┌─────────┐ ┌──────────────┐ ┌────────────────┐
│pagos│ │sesiones │ │notificaciones│ │reagendamientos │
│     │ │_video   │ │_enviadas     │ │                │
└──┬──┘ └────┬────┘ └──────────────┘ └────────────────┘
   │ 1      │ 1
   │ N      │ 1
   ▼        ▼
┌────────────────┐ ┌────────────────┐
│comprobantes_   │ │  grabaciones   │
│transferencia   │ └────┬───────────┘
└────┬───────────┘      │ 1
     │ 1                │ 1
     │ 1                ▼
     ▼            ┌──────────────────┐
┌──────────────┐  │ transcripciones  │
│validaciones_ │  └────┬─────────────┘
│agente        │       │ 1
└──────────────┘       │ 1
                       ▼
                 ┌──────────────────┐
                 │resumenes_consulta│
                 └──────────────────┘
```

### 4.3 Tablas del Dominio 1 — Usuarios

#### 4.3.1 `users` (tabla base Laravel)

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK, auto-increment | Identificador interno |
| uuid | CHAR(36) | UNIQUE, NOT NULL | UUID público para URLs |
| email | VARCHAR(255) | UNIQUE, NOT NULL | Email de login |
| email_verified_at | TIMESTAMP | NULL | Fecha de verificación de email |
| password | VARCHAR(255) | NOT NULL | Hash bcrypt |
| provider | VARCHAR(50) | NULL | `google`, `apple`, `facebook`, `email` |
| provider_id | VARCHAR(255) | NULL | ID del proveedor social |
| two_factor_secret | TEXT | NULL, ENCRYPTED | Secret TOTP para 2FA |
| two_factor_confirmed_at | TIMESTAMP | NULL | Fecha activación 2FA |
| last_login_at | TIMESTAMP | NULL | Último login exitoso |
| last_login_ip | VARCHAR(45) | NULL | IP del último login |
| remember_token | VARCHAR(100) | NULL | Token "recordarme" |
| created_at | TIMESTAMP | NOT NULL | |
| updated_at | TIMESTAMP | NOT NULL | |
| deleted_at | TIMESTAMP | NULL | Soft delete |

**Índices:** `email` (unique), `uuid` (unique), `provider + provider_id` (compuesto).

#### 4.3.2 `user_profiles`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id, UNIQUE | Un perfil por usuario |
| nombre | VARCHAR(100) | NOT NULL | |
| apellido | VARCHAR(100) | NULL | |
| telefono | VARCHAR(20) | NULL | Formato E.164: +56912345678 |
| telefono_pais | VARCHAR(2) | NULL | Código ISO país (CL, VE, MX...) |
| pais_residencia | VARCHAR(2) | NOT NULL | ISO-3166 alpha-2 |
| zona_horaria | VARCHAR(50) | NOT NULL, default 'America/Santiago' | IANA timezone |
| idioma | VARCHAR(5) | NOT NULL, default 'es' | Locale |
| genero | ENUM('femenino','masculino','no_binario','prefiero_no_decir') | NULL | |
| fecha_nacimiento_publica | DATE | NULL | Fecha de nacimiento no-sensible (para cumpleaños, descuentos). Hora y lugar van en `datos_natales` |
| avatar_url | VARCHAR(500) | NULL | URL en R2 o proveedor social |
| biografia | TEXT | NULL | Texto libre corto |
| timestamps | | | created_at, updated_at |

**Índices:** `user_id` (unique), `pais_residencia`.

#### 4.3.3 `datos_natales` (datos sensibles cifrados)

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id, UNIQUE | |
| fecha_nacimiento | TEXT | ENCRYPTED | Cifrada en BD |
| hora_nacimiento | TEXT | ENCRYPTED, NULL | Cifrada |
| hora_desconocida | BOOLEAN | default false | Flag si el cliente no sabe la hora |
| lugar_nacimiento | TEXT | ENCRYPTED | Ciudad, país |
| latitud | DECIMAL(10,7) | NULL | Para cálculos astrológicos |
| longitud | DECIMAL(10,7) | NULL | |
| zona_horaria_natal | VARCHAR(50) | NULL | IANA de la ciudad de nacimiento |
| timestamps | | | |

**Índices:** `user_id` (unique).

**Notas:** Los datos de nacimiento son información **altamente sensible** bajo GDPR y leyes chilenas. Cifrados en reposo usando Laravel Encrypted Casts con la APP_KEY.

#### 4.3.4 `roles`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | SMALLINT UNSIGNED | PK, auto-increment | |
| nombre | VARCHAR(50) | UNIQUE, NOT NULL | `super_admin`, `admin_especialista`, `cliente` |
| descripcion | VARCHAR(255) | NULL | |
| permisos | JSON | NULL | Array de permisos (opcional, para RBAC granular futuro) |
| timestamps | | | |

**Seeds iniciales:** 3 roles creados al instalar.

#### 4.3.5 `user_roles`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| user_id | BIGINT UNSIGNED | FK → users.id, PK compuesta | |
| role_id | SMALLINT UNSIGNED | FK → roles.id, PK compuesta | |
| asignado_en | TIMESTAMP | default CURRENT_TIMESTAMP | |
| asignado_por | BIGINT UNSIGNED | FK → users.id, NULL | Quién asignó el rol |

**PK compuesta:** `(user_id, role_id)`.

#### 4.3.6 `consentimientos`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| tipo | VARCHAR(50) | NOT NULL | `terminos_condiciones`, `privacidad`, `cookies`, `grabacion`, `transcripcion`, `marketing`, `mejora_ia` |
| version_documento | VARCHAR(20) | NOT NULL | Versión del ToS/Privacy aceptada (ej: "1.0", "2024-01") |
| otorgado | BOOLEAN | NOT NULL | true=aceptado, false=rechazado |
| otorgado_en | TIMESTAMP | NOT NULL | |
| revocado_en | TIMESTAMP | NULL | Si se revoca después |
| ip_otorgamiento | VARCHAR(45) | NOT NULL | IP al aceptar (prueba legal) |
| user_agent | VARCHAR(500) | NULL | Navegador usado |
| timestamps | | | |

**Índices:** `user_id + tipo` (compuesto para búsquedas).

### 4.4 Tablas del Dominio 2 — Catálogo

#### 4.4.1 `tipos_consulta`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | SMALLINT UNSIGNED | PK, auto-increment | |
| slug | VARCHAR(50) | UNIQUE, NOT NULL | `tarot`, `cartas_espanolas`, `carta_astral` |
| nombre | VARCHAR(100) | NOT NULL | Nombre visible |
| descripcion | TEXT | NULL | Descripción larga para catálogo |
| duracion_minutos | SMALLINT UNSIGNED | NOT NULL | 30, 60, 90, 120... |
| imagen_url | VARCHAR(500) | NULL | Imagen del tipo de consulta |
| color_hex | VARCHAR(7) | NULL | Color de marca para UI (ej: `#C9A961`) |
| requiere_datos_natales | BOOLEAN | default false | Si requiere fecha/hora/lugar nacimiento |
| orden_visualizacion | SMALLINT | default 0 | Orden en el catálogo |
| activo | BOOLEAN | default true | Visible en catálogo |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | Soft delete |

**Índices:** `slug` (unique), `activo + orden_visualizacion` (compuesto).

#### 4.4.2 `tipos_consulta_precios`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| tipo_consulta_id | SMALLINT UNSIGNED | FK → tipos_consulta.id | |
| moneda | CHAR(3) | NOT NULL | ISO-4217: CLP, USD, MXN, EUR |
| precio_centavos | BIGINT UNSIGNED | NOT NULL | Precio en la menor unidad (ej: 5500000 = $55.000 CLP) |
| vigente_desde | DATE | NOT NULL | |
| vigente_hasta | DATE | NULL | NULL = vigente indefinidamente |
| timestamps | | | |

**Índices:** `tipo_consulta_id + moneda + vigente_desde` (compuesto).

**Notas:** Permite histórico de precios. Al calcular precio actual se toma el registro con `vigente_desde <= hoy` y `vigente_hasta IS NULL OR vigente_hasta >= hoy`.

#### 4.4.3 `paquetes`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | SMALLINT UNSIGNED | PK | |
| slug | VARCHAR(50) | UNIQUE, NOT NULL | `membresia_anual_12x2`, `pack_3_consultas` |
| nombre | VARCHAR(100) | NOT NULL | |
| descripcion | TEXT | NULL | |
| tipo | ENUM('paquete','membresia') | NOT NULL | Distingue paquete fijo vs membresía recurrente |
| consultas_incluidas | SMALLINT UNSIGNED | NOT NULL | Cantidad de consultas |
| vigencia_dias | SMALLINT UNSIGNED | NULL | Duración (ej: 365 para membresía anual) |
| precio_centavos | BIGINT UNSIGNED | NOT NULL | |
| moneda | CHAR(3) | NOT NULL | |
| descuento_porcentaje | DECIMAL(5,2) | NULL | Descuento implícito vs comprar por separado |
| activo | BOOLEAN | default true | |
| imagen_url | VARCHAR(500) | NULL | |
| destacado | BOOLEAN | default false | Badge "destacado" en UI |
| orden_visualizacion | SMALLINT | default 0 | |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | Soft delete |

#### 4.4.4 `paquete_tipos_consulta`

Relación N:M entre paquetes y tipos de consulta aplicables.

| Campo | Tipo | Restricciones |
|-------|------|---------------|
| paquete_id | SMALLINT UNSIGNED | FK → paquetes.id |
| tipo_consulta_id | SMALLINT UNSIGNED | FK → tipos_consulta.id |

**PK compuesta:** `(paquete_id, tipo_consulta_id)`.

**Nota:** Si no hay registros para un paquete, significa que aplica a todos los tipos.

#### 4.4.5 `cupones`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | INT UNSIGNED | PK | |
| codigo | VARCHAR(30) | UNIQUE, NOT NULL | Código que ingresa el cliente (ej: `LUNA2026`) |
| descripcion | VARCHAR(255) | NULL | |
| tipo_descuento | ENUM('porcentaje','monto_fijo') | NOT NULL | |
| valor_descuento | DECIMAL(10,2) | NOT NULL | 10.00 = 10% o $10 según tipo |
| moneda | CHAR(3) | NULL | Solo si tipo=monto_fijo |
| uso_maximo_total | INT | NULL | NULL = ilimitado |
| uso_maximo_por_cliente | SMALLINT | default 1 | |
| usos_totales | INT | default 0 | Contador |
| vigente_desde | TIMESTAMP | NOT NULL | |
| vigente_hasta | TIMESTAMP | NULL | |
| monto_minimo_centavos | BIGINT UNSIGNED | NULL | Compra mínima para aplicar |
| solo_primera_consulta | BOOLEAN | default false | Solo primera consulta del cliente |
| activo | BOOLEAN | default true | |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | |

**Índices:** `codigo` (unique), `activo + vigente_desde + vigente_hasta` (compuesto).

**Tabla relacionada opcional:** `cupon_tipos_consulta` (N:M con `tipos_consulta` si el cupón solo aplica a ciertos tipos). Lo dejo para implementar si se necesita en Fase 1.

### 4.5 Tablas del Dominio 3 — Agendamiento

#### 4.5.1 `disponibilidad_base`

Horario semanal recurrente de Chachita (en hora Chile).

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | INT UNSIGNED | PK | |
| especialista_id | BIGINT UNSIGNED | FK → users.id | Preparado para multi-especialista |
| dia_semana | TINYINT | NOT NULL | 0=domingo, 1=lunes... 6=sábado |
| hora_inicio | TIME | NOT NULL | Ej: 10:00:00 |
| hora_fin | TIME | NOT NULL | Ej: 18:00:00 |
| activo | BOOLEAN | default true | |
| timestamps | | | |

**Índices:** `especialista_id + dia_semana + activo` (compuesto).

**Validaciones:** `hora_fin > hora_inicio`.

#### 4.5.2 `bloqueos_agenda`

Excepciones al horario base: feriados, vacaciones, bloqueos puntuales, horarios extra abiertos.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| especialista_id | BIGINT UNSIGNED | FK → users.id | |
| tipo | ENUM('bloqueo','apertura_extra') | NOT NULL | bloqueo=no disponible; apertura_extra=disponible fuera del horario base |
| motivo | ENUM('feriado','vacaciones','descanso','emergencia','evento','otro') | NOT NULL | |
| descripcion | VARCHAR(255) | NULL | Texto libre explicativo |
| fecha_inicio_utc | DATETIME | NOT NULL | |
| fecha_fin_utc | DATETIME | NOT NULL | |
| all_day | BOOLEAN | default false | Si es día completo (entonces inicio y fin son inicio/fin del día) |
| timestamps | | | |

**Índices:** `especialista_id + fecha_inicio_utc + fecha_fin_utc` (compuesto para búsquedas).

#### 4.5.3 `citas`

**Tabla central del sistema.** Toda la operación gira alrededor de ella.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE, NOT NULL | UUID público |
| codigo_referencia | VARCHAR(20) | UNIQUE, NOT NULL | Código `TE-M4K7-A3B9` para transferencias |
| cliente_id | BIGINT UNSIGNED | FK → users.id | |
| especialista_id | BIGINT UNSIGNED | FK → users.id | |
| tipo_consulta_id | SMALLINT UNSIGNED | FK → tipos_consulta.id | |
| paquete_id | SMALLINT UNSIGNED | FK → paquetes.id, NULL | Si la cita usa un paquete/membresía |
| membresia_id | CHAR(36) | FK → membresias.uuid, NULL | Si consume membresía activa |
| cupon_id | INT UNSIGNED | FK → cupones.id, NULL | Si se usó cupón |
| inicio_utc | DATETIME | NOT NULL | Inicio en UTC |
| fin_utc | DATETIME | NOT NULL | Fin en UTC |
| duracion_minutos | SMALLINT UNSIGNED | NOT NULL | Redundante para fácil consulta |
| zona_horaria_cliente | VARCHAR(50) | NOT NULL | Snapshot de la TZ cuando agendó |
| estado | ENUM | NOT NULL | Ver tabla de estados abajo |
| canal_pago | ENUM('stripe','transferencia') | NOT NULL | |
| precio_total_centavos | BIGINT UNSIGNED | NOT NULL | Precio original |
| precio_final_centavos | BIGINT UNSIGNED | NOT NULL | Después de descuentos |
| moneda | CHAR(3) | NOT NULL | |
| tema_principal | VARCHAR(50) | NULL | `amor`, `trabajo`, `familia`, `salud`, `general` |
| notas_cliente | TEXT | NULL | Pregunta específica del cliente |
| notas_chachita | TEXT | NULL | Notas privadas de Chachita |
| reservada_hasta | TIMESTAMP | NULL | Expiración de reserva temporal (30 min para transferencia) |
| confirmada_en | TIMESTAMP | NULL | Cuando pasó a estado 'confirmada' |
| finalizada_en | TIMESTAMP | NULL | Cuando terminó la videollamada |
| cancelada_en | TIMESTAMP | NULL | |
| motivo_cancelacion | VARCHAR(255) | NULL | |
| es_primera_consulta | BOOLEAN | default false | Flag para descuento de primera consulta |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | Soft delete |

**Estados posibles (`estado` ENUM):**
- `pendiente_abono` — cita creada, esperando pago del 20%
- `reservada` — 20% pagado, esperando saldo
- `confirmada` — 100% pagado, lista para videollamada
- `en_curso` — videollamada en progreso
- `completada` — sesión finalizada correctamente
- `cancelada_cliente` — cliente canceló
- `cancelada_chachita` — Chachita canceló
- `reagendada` — se movió a otra fecha (cita nueva creada)
- `expirada` — se venció el tiempo de reserva sin pagar
- `no_show` — nadie se presentó en el horario

**Índices:**
- `uuid` (unique)
- `codigo_referencia` (unique)
- `cliente_id + estado` (compuesto)
- `especialista_id + inicio_utc` (compuesto, clave para el calendario)
- `estado + inicio_utc` (compuesto)
- `reservada_hasta` (para job de expiración de reservas)

#### 4.5.4 `citas_estados_historial`

Log append-only de cambios de estado (para auditoría y reportes).

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| cita_id | BIGINT UNSIGNED | FK → citas.id | |
| estado_anterior | VARCHAR(30) | NULL | NULL para el estado inicial |
| estado_nuevo | VARCHAR(30) | NOT NULL | |
| motivo | VARCHAR(255) | NULL | |
| cambiado_por | BIGINT UNSIGNED | FK → users.id, NULL | NULL si fue automático (sistema) |
| cambiado_en | TIMESTAMP | default CURRENT_TIMESTAMP | |
| metadatos | JSON | NULL | Datos contextuales (ej: ID de webhook que disparó el cambio) |

**Índices:** `cita_id + cambiado_en`.

#### 4.5.5 `reagendamientos`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| cita_original_id | BIGINT UNSIGNED | FK → citas.id | Cita que se movió |
| cita_nueva_id | BIGINT UNSIGNED | FK → citas.id | Cita nueva creada |
| motivo | VARCHAR(255) | NULL | |
| gratuito | BOOLEAN | NOT NULL | true = primer reagendamiento gratis |
| reagendado_por | BIGINT UNSIGNED | FK → users.id | Cliente o especialista |
| timestamps | | | |

**Índices:** `cita_original_id`, `cita_nueva_id`.

### 4.6 Tablas del Dominio 4 — Pagos

#### 4.6.1 `metodos_pago_chachita`

Cuentas bancarias de Chachita para recibir transferencias.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | SMALLINT UNSIGNED | PK | |
| especialista_id | BIGINT UNSIGNED | FK → users.id | |
| tipo | ENUM('cuenta_corriente','cuenta_vista','cuenta_rut') | NOT NULL | |
| banco | VARCHAR(100) | NOT NULL | `Banco de Chile`, `BancoEstado`, `Santander`, etc. |
| numero_cuenta | TEXT | NOT NULL, ENCRYPTED | Cifrado |
| rut_titular | VARCHAR(12) | NOT NULL | RUT con formato XX.XXX.XXX-X |
| nombre_titular | VARCHAR(150) | NOT NULL | |
| email_transferencia | VARCHAR(255) | NULL | Email al que llegan avisos de transferencia |
| principal | BOOLEAN | default false | Cuenta principal a mostrar al cliente |
| activo | BOOLEAN | default true | |
| timestamps | | | |

#### 4.6.2 `pagos`

Registro de cada intento de pago (sea exitoso o no).

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| cita_id | BIGINT UNSIGNED | FK → citas.id | |
| tipo | ENUM('abono_20','saldo_80','pago_total','extra','reembolso') | NOT NULL | |
| canal | ENUM('stripe','transferencia','credito_cliente','membresia') | NOT NULL | |
| monto_centavos | BIGINT UNSIGNED | NOT NULL | Monto del intento |
| moneda | CHAR(3) | NOT NULL | |
| monto_equivalente_clp | BIGINT UNSIGNED | NULL | Conversión a CLP para reportes |
| tasa_cambio | DECIMAL(15,8) | NULL | Tasa usada en la conversión |
| estado | ENUM('pendiente','procesando','completado','fallido','reembolsado','parcial_reembolsado') | NOT NULL | |
| stripe_payment_intent_id | VARCHAR(100) | NULL | ID en Stripe |
| stripe_charge_id | VARCHAR(100) | NULL | |
| pagado_en | TIMESTAMP | NULL | Cuando se completó |
| fallo_razon | VARCHAR(255) | NULL | Si falló, por qué |
| metadata | JSON | NULL | Datos adicionales del proveedor |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | |

**Índices:** `uuid` (unique), `cita_id + tipo + estado`, `stripe_payment_intent_id` (unique donde no NULL), `pagado_en`.

#### 4.6.3 `comprobantes_transferencia`

Archivos de comprobantes subidos por clientes chilenos.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| pago_id | BIGINT UNSIGNED | FK → pagos.id | |
| archivo_url | VARCHAR(500) | NOT NULL | URL en R2 |
| archivo_tipo | VARCHAR(20) | NOT NULL | `image/jpeg`, `image/png`, `application/pdf` |
| tamano_bytes | INT UNSIGNED | NOT NULL | |
| datos_extraidos | JSON | NULL | Output del OCR (cuenta, monto, fecha, ID transacción, referencia) |
| estado_validacion | ENUM('pendiente','aprobado_automatico','aprobado_manual','rechazado_automatico','rechazado_manual','revision_requerida') | NOT NULL | |
| validado_por | BIGINT UNSIGNED | FK → users.id, NULL | NULL si fue el agente IA |
| validado_en | TIMESTAMP | NULL | |
| razon_rechazo | VARCHAR(500) | NULL | Si rechazado, por qué |
| id_transaccion_bancaria | VARCHAR(100) | NULL | ID único extraído del comprobante |
| timestamps | | | |

**Índices:** `uuid` (unique), `pago_id`, `estado_validacion`, `id_transaccion_bancaria` (para detectar duplicados).

#### 4.6.4 `validaciones_agente`

Log de cada ejecución del agente IA de validación.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| comprobante_id | BIGINT UNSIGNED | FK → comprobantes_transferencia.id | |
| regla_1_cuenta_ok | BOOLEAN | NULL | Cuenta destino correcta |
| regla_2_monto_ok | BOOLEAN | NULL | Monto ≥ acordado |
| regla_3_referencia_ok | BOOLEAN | NULL | Código de referencia coincide |
| regla_4_unicidad_ok | BOOLEAN | NULL | ID transacción no duplicado |
| regla_5_ventana_tiempo_ok | BOOLEAN | NULL | Transferencia dentro de 30min |
| decision | ENUM('aprobar','rechazar','revision_manual') | NOT NULL | |
| razon | VARCHAR(500) | NOT NULL | Explicación textual |
| modelo_ia | VARCHAR(50) | NOT NULL | Ej: `claude-3-5-sonnet-vision` |
| tokens_usados | INT | NULL | |
| duracion_ms | INT | NOT NULL | Duración del análisis |
| created_at | TIMESTAMP | NOT NULL | |

**Índices:** `comprobante_id`, `decision + created_at`.

**Sin soft delete** (append-only para auditoría).

#### 4.6.5 `membresias`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| cliente_id | BIGINT UNSIGNED | FK → users.id | |
| paquete_id | SMALLINT UNSIGNED | FK → paquetes.id | |
| pago_inicial_id | BIGINT UNSIGNED | FK → pagos.id | Pago que dio origen |
| fecha_inicio | DATE | NOT NULL | |
| fecha_fin | DATE | NOT NULL | |
| consultas_incluidas | SMALLINT UNSIGNED | NOT NULL | |
| consultas_usadas | SMALLINT UNSIGNED | default 0 | |
| estado | ENUM('activa','expirada','cancelada','agotada') | NOT NULL | |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | |

**Índices:** `uuid` (unique), `cliente_id + estado`, `fecha_fin` (para job de expiración).

#### 4.6.6 `creditos_cliente`

Saldos a favor del cliente (excedentes, compensaciones).

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| cliente_id | BIGINT UNSIGNED | FK → users.id | |
| monto_centavos | BIGINT | NOT NULL | Puede ser positivo (crédito) o negativo (usado) |
| moneda | CHAR(3) | NOT NULL | |
| origen | ENUM('excedente_transferencia','reembolso','compensacion','uso_crédito','ajuste_manual') | NOT NULL | |
| referencia_pago_id | BIGINT UNSIGNED | FK → pagos.id, NULL | Pago que originó el crédito |
| cita_aplicada_id | BIGINT UNSIGNED | FK → citas.id, NULL | Si fue aplicado a una cita |
| descripcion | VARCHAR(255) | NULL | |
| expira_en | DATE | NULL | Si tiene vencimiento |
| estado | ENUM('disponible','aplicado','expirado') | NOT NULL | |
| timestamps | | | |

**Índices:** `cliente_id + estado + moneda` (para calcular saldo disponible).

#### 4.6.7 `reembolsos`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| cita_id | BIGINT UNSIGNED | FK → citas.id | |
| cliente_id | BIGINT UNSIGNED | FK → users.id | |
| pago_id | BIGINT UNSIGNED | FK → pagos.id, NULL | Pago asociado cuando existe transacción original |
| monto_centavos | BIGINT UNSIGNED | NOT NULL | |
| moneda | CHAR(3) | NOT NULL | |
| razon | VARCHAR(50) | NOT NULL | Ej: `cancelacion_24h`, `cancelacion_chachita`, `ajuste_manual`, etc. |
| metodo | VARCHAR(30) | NOT NULL, default `mismo_medio_pago` | `mismo_medio_pago`, `transferencia_manual`, `credito_cliente` |
| estado | VARCHAR(30) | NOT NULL, default `pendiente` | `pendiente`, `completado`, `fallido` |
| solicitado_en | TIMESTAMP | NULL | Momento de creación/solicitud |
| procesado_en | TIMESTAMP | NULL | |
| metadata | JSON | NULL | Payload extendido (stripe response, errores, referencias manuales, auditoría) |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | Soft delete |

**Índices:** `uuid` (unique), `cliente_id + razon + estado`, `cita_id + estado`.

### 4.7 Tablas del Dominio 5 — Consultas

#### 4.7.1 `sesiones_video`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| cita_id | BIGINT UNSIGNED | FK → citas.id, UNIQUE | Una sesión por cita |
| daily_room_name | VARCHAR(100) | UNIQUE, NOT NULL | Nombre en Daily.co |
| daily_room_url | VARCHAR(500) | NOT NULL | URL de acceso |
| daily_room_id | VARCHAR(100) | NULL | ID interno Daily |
| cliente_token | TEXT | NULL, ENCRYPTED | Token único para el cliente |
| especialista_token | TEXT | NULL, ENCRYPTED | Token único para Chachita |
| inicio_real | TIMESTAMP | NULL | Hora real de inicio |
| fin_real | TIMESTAMP | NULL | Hora real de fin |
| duracion_real_segundos | INT UNSIGNED | NULL | |
| estado | ENUM('pendiente','en_curso','finalizada','fallida') | NOT NULL | |
| cliente_entro_en | TIMESTAMP | NULL | |
| especialista_entro_en | TIMESTAMP | NULL | |
| consentimiento_grabacion_aceptado | BOOLEAN | default false | |
| consentimiento_aceptado_en | TIMESTAMP | NULL | |
| metadata | JSON | NULL | Datos adicionales de Daily.co |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | |

#### 4.7.2 `grabaciones`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| sesion_video_id | BIGINT UNSIGNED | FK → sesiones_video.id | |
| daily_recording_id | VARCHAR(100) | UNIQUE | ID de la grabación en Daily |
| r2_key | VARCHAR(500) | NOT NULL | Clave del objeto en R2 |
| r2_bucket | VARCHAR(50) | NOT NULL | `tarotestrellas-media` |
| url_firmada | VARCHAR(1000) | NULL | URL firmada temporal (se regenera) |
| url_firmada_expira | TIMESTAMP | NULL | |
| tamano_bytes | BIGINT UNSIGNED | NOT NULL | |
| duracion_segundos | INT UNSIGNED | NOT NULL | |
| formato | VARCHAR(10) | NOT NULL | `mp4`, `webm` |
| resolucion | VARCHAR(20) | NULL | `1280x720` |
| estado | ENUM('procesando','disponible','eliminada','fallida') | NOT NULL | |
| programada_eliminar_en | DATE | NOT NULL | Fecha de eliminación automática (3 meses) |
| eliminada_en | TIMESTAMP | NULL | |
| descargada_por_cliente | BOOLEAN | default false | Si el cliente la descargó antes de eliminarla |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | |

**Índices:** `uuid` (unique), `daily_recording_id` (unique), `sesion_video_id`, `programada_eliminar_en` (para job de limpieza).

#### 4.7.3 `transcripciones`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| sesion_video_id | BIGINT UNSIGNED | FK → sesiones_video.id, UNIQUE | |
| grabacion_id | BIGINT UNSIGNED | FK → grabaciones.id, NULL | La transcripción puede sobrevivir a la eliminación de la grabación |
| contenido_completo | LONGTEXT | NOT NULL | Transcripción completa |
| segmentos_json | JSON | NULL | Segmentos con timestamps (inicio, fin, hablante, texto) |
| idioma_detectado | VARCHAR(10) | NOT NULL | `es`, `en`, etc. |
| proveedor | VARCHAR(50) | NOT NULL | `whisper-1`, `whisper-large-v3` |
| confianza_promedio | DECIMAL(4,3) | NULL | 0.000 - 1.000 |
| palabras_total | INT UNSIGNED | NULL | |
| duracion_audio_segundos | INT UNSIGNED | NULL | |
| procesamiento_duracion_segundos | INT UNSIGNED | NULL | Tiempo que tardó en procesarse |
| costo_usd_centavos | INT UNSIGNED | NULL | Centavos de USD (costo Whisper) |
| timestamps | | | |
| deleted_at | TIMESTAMP | NULL | |

**Índices:** `uuid` (unique), `sesion_video_id` (unique), `grabacion_id`.

**Nota clave:** La transcripción **se conserva indefinidamente** aunque la grabación se borre a los 3 meses. Esto es lo que mantiene viva la memoria del agente IA.

#### 4.7.4 `resumenes_consulta`

Resumen generado por IA después de cada consulta.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| transcripcion_id | BIGINT UNSIGNED | FK → transcripciones.id, UNIQUE | |
| resumen_corto | TEXT | NOT NULL | 2-3 párrafos |
| resumen_largo | TEXT | NULL | Versión extendida opcional |
| temas_detectados | JSON | NULL | Array de temas: `["amor", "trabajo", "autoconocimiento"]` |
| emociones_detectadas | JSON | NULL | Array de emociones |
| preguntas_cliente | JSON | NULL | Preguntas formuladas por el cliente |
| recomendaciones | JSON | NULL | Recomendaciones dadas por Chachita |
| modelo_ia | VARCHAR(50) | NOT NULL | `claude-3-5-haiku` |
| tokens_usados | INT | NULL | |
| timestamps | | | |

### 4.8 Tablas del Dominio 6 — Agente IA

#### 4.8.1 `agente_conversaciones`

Conversaciones de Chachita o del cliente con el agente IA.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| usuario_id | BIGINT UNSIGNED | FK → users.id | Quién está consultando |
| cliente_objetivo_id | BIGINT UNSIGNED | FK → users.id, NULL | Sobre qué cliente es la consulta (NULL si es general) |
| scope | ENUM('chachita_sobre_cliente','cliente_sobre_si_mismo','chachita_general') | NOT NULL | |
| titulo | VARCHAR(255) | NULL | Título autogenerado |
| iniciada_en | TIMESTAMP | NOT NULL | |
| ultima_actividad | TIMESTAMP | NOT NULL | |
| mensajes_total | INT | default 0 | |
| tokens_total | INT | default 0 | Para control de costos |
| archivada | BOOLEAN | default false | |
| timestamps | | | |

**Índices:** `uuid` (unique), `usuario_id + ultima_actividad` (para listar conversaciones recientes), `cliente_objetivo_id`.

#### 4.8.2 `agente_mensajes`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| conversacion_id | BIGINT UNSIGNED | FK → agente_conversaciones.id | |
| rol | ENUM('user','assistant','system') | NOT NULL | Estándar OpenAI/Anthropic |
| contenido | LONGTEXT | NOT NULL | |
| tokens_input | INT | NULL | |
| tokens_output | INT | NULL | |
| modelo | VARCHAR(50) | NULL | Solo para rol=assistant |
| duracion_ms | INT | NULL | Latencia del modelo |
| transcripciones_citadas | JSON | NULL | IDs de transcripciones usadas como contexto |
| created_at | TIMESTAMP | NOT NULL | |

**Sin soft delete** (append-only).

**Índices:** `conversacion_id + created_at`.

#### 4.8.3 `embeddings_transcripciones`

Vectores semánticos para búsqueda rápida en el agente.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| transcripcion_id | BIGINT UNSIGNED | FK → transcripciones.id | |
| fragmento | TEXT | NOT NULL | Chunk de texto (250-500 tokens) |
| fragmento_orden | SMALLINT UNSIGNED | NOT NULL | Orden dentro de la transcripción |
| embedding | JSON | NOT NULL | Vector (array de floats) |
| modelo_embedding | VARCHAR(50) | NOT NULL | `text-embedding-3-small` |
| dimensiones | SMALLINT UNSIGNED | NOT NULL | Típicamente 1536 |
| tokens | INT | NULL | |
| created_at | TIMESTAMP | NOT NULL | |

**Índices:** `transcripcion_id + fragmento_orden`.

**Nota técnica:** MariaDB 10.11 no tiene tipo nativo de vectores eficiente. Alternativas si crece el volumen:
- Opción económica inicial: guardar embeddings como JSON y hacer búsqueda en PHP (funciona hasta miles de fragmentos)
- Opción escalable: mover a Pinecone o Qdrant (servicios especializados de vectores) cuando lo justifique

**Sin soft delete** (append-only).

### 4.9 Tablas del Dominio 7 — Notificaciones

#### 4.9.1 `plantillas_notificacion`

Plantillas editables por Chachita desde admin.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | SMALLINT UNSIGNED | PK | |
| slug | VARCHAR(100) | UNIQUE, NOT NULL | Ej: `cita_confirmada_email`, `recordatorio_48h_whatsapp` |
| canal | ENUM('email','whatsapp','sms','push') | NOT NULL | |
| evento | VARCHAR(100) | NOT NULL | `cita_reservada`, `recordatorio_48h`, etc. |
| asunto | VARCHAR(255) | NULL | Solo para email |
| cuerpo | TEXT | NOT NULL | HTML para email, texto plano para WA |
| variables_disponibles | JSON | NOT NULL | Lista de variables: `{nombre_cliente}`, `{fecha_cita}`, etc. |
| activa | BOOLEAN | default true | |
| idioma | VARCHAR(5) | NOT NULL, default 'es' | |
| timestamps | | | |

#### 4.9.2 `notificaciones_enviadas`

Log de todas las notificaciones enviadas.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| destinatario_id | BIGINT UNSIGNED | FK → users.id | |
| canal | ENUM('email','whatsapp','sms','push') | NOT NULL | |
| plantilla_id | SMALLINT UNSIGNED | FK → plantillas_notificacion.id, NULL | NULL si fue custom |
| evento | VARCHAR(100) | NOT NULL | Redundante para queries rápidas |
| asunto | VARCHAR(255) | NULL | |
| destinatario_valor | VARCHAR(255) | NOT NULL | Email o teléfono al que se envió |
| estado | ENUM('pendiente','enviado','entregado','leido','fallido','rebotado') | NOT NULL | |
| proveedor | VARCHAR(50) | NULL | `resend`, `meta_whatsapp` o `ycloud` |
| proveedor_message_id | VARCHAR(255) | NULL | ID del proveedor |
| entidad_tipo | VARCHAR(50) | NULL | Ej: `cita`, `pago` |
| entidad_id | BIGINT UNSIGNED | NULL | ID de la entidad relacionada |
| error_mensaje | VARCHAR(500) | NULL | |
| intentos | TINYINT | default 0 | |
| enviado_en | TIMESTAMP | NULL | |
| created_at | TIMESTAMP | NOT NULL | |

**Sin soft delete** (append-only).

**Índices:** `destinatario_id + created_at`, `estado + created_at`, `proveedor_message_id`.

#### 4.9.3 `preferencias_notificacion`

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id, UNIQUE | |
| email_activo | BOOLEAN | default true | |
| whatsapp_activo | BOOLEAN | default true | |
| sms_activo | BOOLEAN | default false | |
| push_activo | BOOLEAN | default true | |
| marketing_activo | BOOLEAN | default false | |
| recordatorios_activo | BOOLEAN | default true | |
| transaccionales_obligatorias | BOOLEAN | default true, NOT NULL | Nunca se desactiva (confirmaciones, recibos) |
| timestamps | | | |

### 4.10 Tablas del Dominio 8 — Auditoría y Sistema

#### 4.10.1 `audit_log`

Registro de acciones críticas en el sistema.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT UNSIGNED | PK | |
| usuario_id | BIGINT UNSIGNED | FK → users.id, NULL | Quién hizo la acción (NULL = sistema) |
| accion | VARCHAR(100) | NOT NULL | `cita.creada`, `pago.aprobado`, `user.2fa_activado` |
| entidad_tipo | VARCHAR(50) | NULL | Modelo afectado |
| entidad_id | BIGINT UNSIGNED | NULL | ID del modelo |
| datos_antes | JSON | NULL | Snapshot antes del cambio |
| datos_despues | JSON | NULL | Snapshot después |
| ip | VARCHAR(45) | NULL | IP del actor |
| user_agent | VARCHAR(500) | NULL | |
| resultado | ENUM('exito','fallo') | NOT NULL | |
| nivel | ENUM('info','warning','critical') | default 'info' | |
| created_at | TIMESTAMP | NOT NULL | |

**Sin soft delete** (append-only). **Nunca se elimina**, solo se archiva a largo plazo si crece mucho.

**Índices:** `usuario_id + created_at`, `entidad_tipo + entidad_id`, `accion + created_at`, `nivel + created_at`.

#### 4.10.2 `configuracion_sistema`

Parámetros globales editables desde admin.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | SMALLINT UNSIGNED | PK | |
| clave | VARCHAR(100) | UNIQUE, NOT NULL | Ej: `MINUTOS_VENTANA_TRANSFERENCIA` |
| valor | VARCHAR(500) | NOT NULL | Valor como string |
| tipo | ENUM('int','float','bool','string','json') | NOT NULL | Para casting correcto |
| categoria | VARCHAR(50) | NOT NULL | Agrupación en UI: `pagos`, `agendamiento`, `notificaciones` |
| descripcion | VARCHAR(500) | NULL | Qué hace este parámetro |
| editable_admin | BOOLEAN | default true | Si Chachita puede editarlo |
| timestamps | | | |

**Seeds iniciales (valores por defecto):**

| Clave | Valor | Categoría |
|-------|-------|-----------|
| `MINUTOS_VENTANA_TRANSFERENCIA` | 30 | pagos |
| `MINUTOS_EXTENSION_TRANSFERENCIA` | 10 | pagos |
| `EXTENSIONES_PERMITIDAS` | 1 | pagos |
| `MINUTOS_ANTIGUEDAD_COMPROBANTE` | 30 | pagos |
| `PORCENTAJE_ABONO` | 20 | pagos |
| `HORAS_RECORDATORIO_1` | 48 | notificaciones |
| `HORAS_RECORDATORIO_2` | 3 | notificaciones |
| `MINUTOS_RECORDATORIO_3` | 30 | notificaciones |
| `DIAS_ANTICIPACION_MINIMA` | 1 | agendamiento |
| `DIAS_ANTICIPACION_MAXIMA` | 60 | agendamiento |
| `SESIONES_CONSECUTIVAS_MAX` | 2 | agendamiento |
| `MINUTOS_DESCANSO_OBLIGATORIO` | 30 | agendamiento |
| `HORAS_REEMBOLSO_ANTICIPACION` | 24 | cancelaciones |
| `REAGENDAMIENTOS_GRATUITOS` | 1 | cancelaciones |
| `DIAS_RETENCION_VIDEO` | 90 | privacidad |
| `DIAS_AVISO_ELIMINACION_VIDEO` | 7 | privacidad |
| `DESCUENTO_PRIMERA_CONSULTA_PCT` | 10 | promociones |
| `ZONA_HORARIA_ESPECIALISTA` | America/Santiago | agendamiento |

#### 4.10.3 `jobs_fallidos`

Tabla estándar de Laravel, gestionada por Horizon. La incluimos por completitud.

| Campo | Tipo | Restricciones |
|-------|------|---------------|
| id | BIGINT UNSIGNED | PK |
| uuid | CHAR(36) | UNIQUE |
| connection | TEXT | NOT NULL |
| queue | TEXT | NOT NULL |
| payload | LONGTEXT | NOT NULL |
| exception | LONGTEXT | NOT NULL |
| failed_at | TIMESTAMP | default CURRENT_TIMESTAMP |

### 4.11 Tablas técnicas adicionales de Laravel

Laravel requiere algunas tablas de sistema que no se detallan por ser estándar:

- `password_reset_tokens` — recuperación de contraseña
- `personal_access_tokens` — tokens Sanctum para API
- `jobs` — cola de trabajos pendientes (Redis la reemplaza en producción)
- `cache` y `cache_locks` — cache (Redis la reemplaza en producción)
- `sessions` — sesiones web (Redis la reemplaza)
- `migrations` — historial de migraciones ejecutadas

### 4.12 Reglas de integridad y constraints

**Constraints de negocio relevantes:**

- `citas.fin_utc > citas.inicio_utc` (check constraint)
- `citas.precio_final_centavos <= citas.precio_total_centavos` (después de descuentos)
- `tipos_consulta.duracion_minutos > 0`
- `pagos.monto_centavos > 0`
- `disponibilidad_base.hora_fin > disponibilidad_base.hora_inicio`
- `membresias.consultas_usadas <= membresias.consultas_incluidas`
- `membresias.fecha_fin > membresias.fecha_inicio`

**Cascadas ON DELETE:**

- `user_profiles`, `datos_natales`, `consentimientos`, `preferencias_notificacion` → CASCADE cuando `users` se elimina físicamente
- `comprobantes_transferencia` → CASCADE cuando `pagos` se elimina físicamente
- `agente_mensajes` → CASCADE cuando `agente_conversaciones` se elimina
- `embeddings_transcripciones` → CASCADE cuando `transcripciones` se elimina
- `tipos_consulta_precios` → CASCADE cuando `tipos_consulta` se elimina

Todas las demás relaciones usan **RESTRICT** por defecto (no permite eliminar si hay dependencias).

### 4.13 Datos iniciales (seeds)

Al instalar el sistema se cargan estos datos base:

**Roles:** `super_admin`, `admin_especialista`, `cliente`.

**Usuario admin inicial:** se crea con seeder el usuario de Chachita con rol `admin_especialista`.

**Tipos de consulta iniciales (12):**
cartas_espanolas, tarot, carta_astral, astrologia_revolucion_solar, consulta_rapida, sinastria, limpieza_energetica, numerologia, runas, lectura_cafe, quiromancia, pendulo.

**Precios iniciales en CLP y USD** según tabla acordada en Decisión 9.

**Plantillas de notificación iniciales:** se crean todas las plantillas base en español para los eventos matriz definidos en Decisión 12 (aproximadamente 20-25 plantillas entre email y WhatsApp).

**Configuración de sistema:** todos los valores por defecto listados en tabla de `configuracion_sistema` arriba.

**Paquetes iniciales:** solo la "Membresía Anual 12x2" (aprobada en Decisión 9.3).

**Cupones iniciales:** ninguno por defecto; se crean desde admin cuando se necesiten.

### 4.14 Estimación de volumen y crecimiento

Basado en 20 consultas/mes iniciales de Chachita:

| Tabla | Registros año 1 | Registros año 3 | Observación |
|-------|-----------------|------------------|-------------|
| users | 200-500 | 2,000-5,000 | Depende de adopción |
| citas | 240 | 720+ | Si crece a multi-especialista, x10 |
| pagos | 480-720 | 1,500-3,000 | 2 pagos por cita (20% y 80%) |
| grabaciones | 240 | 720+ | Se eliminan a los 3 meses |
| transcripciones | 240 | 720+ | Se mantienen indefinidamente |
| agente_mensajes | 1,000-5,000 | 10,000-50,000 | Según uso del agente |
| embeddings | 5,000-15,000 | 50,000-150,000 | Varios fragmentos por transcripción |
| notificaciones_enviadas | 5,000 | 50,000+ | Varias por cita |
| audit_log | 10,000+ | 100,000+ | Crece rápido |

**Conclusión:** MariaDB en el VPS KVM 2 maneja cómodamente estos volúmenes durante años. No hay problema de performance en el horizonte cercano con los índices correctos.

---

## 5. Módulos Funcionales

### 5.1 Registro y autenticación

#### 5.1.1 Propósito del módulo

Gestionar el ciclo de vida completo de la identidad del usuario: desde su primer registro hasta el cierre definitivo de la cuenta, incluyendo todos los flujos de acceso, recuperación de credenciales, verificación de identidad y cumplimiento de derechos GDPR.

Este módulo cubre tres tipos de usuarios con flujos diferenciados:
- **Cliente** (quien agenda y recibe consultas)
- **Admin-Especialista** (Chachita)
- **Super Admin** (tú/desarrollo)

#### 5.1.2 Flujo de registro de cliente

**Entrada al flujo:** el cliente llega a la app sin cuenta (desde landing, link de Instagram, referido, etc.).

**Paso 1 — Elección del método:**
El cliente ve tres opciones equivalentes:
- Registro con email y contraseña
- Continuar con Google
- Continuar con Apple (Fase 2)
- Continuar con Facebook (Fase 2)

**Paso 2 — Formulario de registro (si eligió email):**

Campos obligatorios:
- Nombre completo
- Email (validación formato, verificación de unicidad)
- Contraseña (mínimo 10 caracteres, al menos un número y una letra)
- Teléfono con selector de código país (componente tipo `react-phone-input-2`)
- País de residencia (select con detección automática por IP como sugerencia)
- Zona horaria (autodetectada del navegador, editable)

Checkboxes obligatorios:
- Acepto los Términos y Condiciones (enlace abre modal)
- Acepto la Política de Privacidad (enlace abre modal)
- Confirmo ser mayor de 18 años

Checkboxes opcionales:
- Quiero recibir promociones y novedades
- Acepto que mis datos se usen para mejorar el servicio

**Paso 3 — Validaciones:**

El backend valida:
- Email no registrado previamente (con otro o mismo método)
- Teléfono con formato E.164 válido para el país seleccionado
- Contraseña cumple criterios de seguridad
- Consentimientos obligatorios otorgados
- País no está en lista de bloqueos (si aplica en el futuro)

Si alguna validación falla, el formulario muestra el error específico inline (sin recargar página).

**Paso 4 — Creación de cuenta:**

Al aprobarse la validación, el sistema ejecuta en una transacción atómica:
1. Crea registro en `users` con password hasheada (bcrypt cost 12)
2. Crea registro en `user_profiles` con los datos del formulario
3. Crea registros en `consentimientos` con IP, user-agent y versión del documento aceptado
4. Crea registro en `preferencias_notificacion` con defaults
5. Asigna el rol `cliente` en `user_roles`
6. Genera token de verificación de email (válido 24 horas)
7. Envía email de verificación via Resend
8. Envía WhatsApp de bienvenida via Meta WhatsApp Cloud API o YCloud (usando plantilla aprobada de Meta)
9. Registra evento en `audit_log` como `user.registered`
10. Retorna JWT de sesión para login inmediato

**Paso 5 — Verificación de email:**

El usuario queda logueado pero con un banner persistente en la app: *"Verifica tu email para poder agendar tu primera consulta. ¿No recibiste el email? [Reenviar]"*.

Mientras el email no esté verificado:
- Puede navegar la app, ver catálogo, completar perfil, leer contenido público
- **No puede**: agendar consultas, usar cupones, acceder al agente IA

Al hacer click en el link del email:
- Sistema valida el token (no expirado, no usado)
- Marca `email_verified_at` con timestamp actual
- Invalida el token
- Redirige a `/app/mi-cuenta` con mensaje de éxito
- Registra evento `user.email_verified`

#### 5.1.3 Flujo de registro con login social (Google)

**Paso 1:** cliente hace click en "Continuar con Google" → redirección a OAuth de Google

**Paso 2:** Google devuelve tras aprobación con: email, nombre, picture_url, google_id

**Paso 3:** el sistema busca en `users`:
- Si email existe **con provider=email**: muestra error *"Ya tienes una cuenta con este email registrada con contraseña. Inicia sesión y vincula tu cuenta de Google desde tu perfil"*
- Si email existe **con provider=google**: login directo, actualiza `last_login_at`, genera JWT
- Si email no existe: crea cuenta nueva con `provider='google'` y `provider_id={google_id}`, `email_verified_at=now()` (Google ya lo verificó), pide completar teléfono y país en pantalla siguiente antes de habilitar agendamiento

**Para Apple y Facebook (Fase 2):** mismo flujo con diferencias específicas de cada proveedor (Apple permite "Hide My Email" que devuelve email relay tipo `xxx@privaterelay.appleid.com`).

#### 5.1.4 Flujo de login

**Entrada:** email + contraseña (o botón social)

**Paso 1 — Rate limiting:**
Máximo 5 intentos fallidos por IP en 15 minutos. Al exceder, la IP queda bloqueada por 30 minutos y se registra en `audit_log` como evento warning.

**Paso 2 — Validación:**
- Busca usuario por email
- Si no existe: devuelve error genérico *"Email o contraseña incorrectos"* (no revela si el email está registrado, para evitar enumeración)
- Si existe pero `deleted_at IS NOT NULL`: mismo mensaje genérico
- Si existe pero password no coincide: mismo mensaje, incrementa contador de intentos fallidos
- Si existe y password coincide:
  - Si tiene 2FA activado: pasa al Paso 3
  - Si no tiene 2FA: genera JWT, actualiza `last_login_at` e `last_login_ip`, registra evento `user.login`

**Paso 3 — 2FA (si aplica):**
- Pide código de 6 dígitos de la app autenticadora
- Máximo 3 intentos antes de invalidar la sesión parcial
- Al validar: genera JWT completo

**Paso 4 — Respuesta al frontend:**
```json
{
  "token": "eyJhbGc...",
  "user": {
    "uuid": "...",
    "nombre": "Juan Pérez",
    "email": "juan@...",
    "avatar_url": "...",
    "roles": ["cliente"],
    "email_verified": true
  },
  "expires_at": "2026-05-16T12:00:00Z"
}
```

Frontend guarda el token en localStorage (web) o Secure Storage (Capacitor en Fase 2).

#### 5.1.5 Flujo de recuperación de contraseña

**Paso 1:** cliente hace click en "¿Olvidaste tu contraseña?" en pantalla de login

**Paso 2:** ingresa su email

**Paso 3:** sistema siempre responde con el mismo mensaje (independientemente de si el email existe o no): *"Si el email está registrado, recibirás instrucciones para restablecer tu contraseña"*. Esto evita enumeración de emails.

**Paso 4 (background):** si el email existe:
- Genera token único (válido 1 hora)
- Lo guarda en tabla `password_reset_tokens`
- Envía email con link `/auth/reset-password?token=XXX`

**Paso 5:** cliente hace click en link, ingresa nueva contraseña (con confirmación), validaciones estándar.

**Paso 6:** sistema actualiza password, invalida el token, invalida todas las sesiones activas del usuario (fuerza re-login en todos los dispositivos), envía email de confirmación de cambio.

**Paso 7:** registra evento `user.password_changed` en `audit_log`.

#### 5.1.6 Activación de 2FA

**Desde:** perfil del usuario → "Seguridad" → "Activar autenticación de dos factores"

**Paso 1:** sistema genera secret TOTP, lo guarda cifrado en `users.two_factor_secret`

**Paso 2:** muestra código QR + código manual al usuario para escanear en Google Authenticator / Authy / 1Password

**Paso 3:** pide al usuario ingresar un código actual para confirmar que está funcionando

**Paso 4:** al validar, activa 2FA (marca `two_factor_confirmed_at`) y muestra 8 **códigos de respaldo** de un solo uso (por si pierde el dispositivo). El usuario debe descargarlos o imprimirlos antes de cerrar el modal.

**Paso 5:** desde ese momento, todo login pide el código TOTP después de la contraseña.

**Desactivación de 2FA:** requiere ingresar password + código TOTP actual para desactivar. Previene que alguien que robe la sesión pueda quitar 2FA.

#### 5.1.7 Derechos GDPR (autoservicio)

Desde la sección "Mi cuenta" → "Privacidad", el cliente tiene 4 acciones disponibles:

**Exportar mis datos:**
- Click genera un job asíncrono que compila todos los datos del usuario
- En 24 horas máximo, recibe email con link de descarga (link válido 7 días)
- Contenido: JSON estructurado con perfil, datos natales, citas, pagos, transcripciones (solo texto), preferencias, consentimientos otorgados/revocados
- **No incluye**: grabaciones de video (muy pesadas; deben solicitar explícitamente)

**Revocar consentimientos:**
- Muestra lista de consentimientos otorgados con toggle para revocar
- Consentimientos obligatorios (grabación, transcripción) no se pueden revocar sin cerrar cuenta
- Consentimientos opcionales (marketing, mejora IA) se revocan al instante
- Revocación queda registrada en `consentimientos.revocado_en`

**Eliminar mi cuenta:**
- Warning modal: *"Esta acción es irreversible. Se eliminarán todas tus consultas, transcripciones, grabaciones y datos personales. Las citas pasadas pagadas no se reembolsan. ¿Estás seguro?"*
- Requiere ingresar password
- Requiere escribir la palabra "ELIMINAR" como confirmación final
- Al confirmar: sistema ejecuta **soft delete** del usuario (se marca `deleted_at`), anonimiza datos sensibles (nombre → "Usuario eliminado", email → `deleted-{id}@tarotestrellas.com`)
- Los datos de facturación/citas se conservan 7 años por obligación tributaria chilena (Ley 19.628 y Ley 19.799)
- Envía email de confirmación de eliminación

**Editar mis datos:**
- Pantalla de perfil editable con cada campo
- Cambios a datos natales requieren re-autenticación (password o 2FA) por ser sensibles
- Cambios quedan en `audit_log`

#### 5.1.8 Login de Chachita (admin)

Chachita accede por URL separada: `tarotestrellas.com/admin`

Diferencias con el login de cliente:
- **2FA obligatorio** (no opcional)
- Sesión más corta: 8 horas de inactividad la expira (vs 30 días del cliente)
- Logs de acceso detallados en `audit_log`
- IP allowlist opcional (configurable desde super_admin): Chachita puede restringir acceso solo a sus IPs habituales
- Rate limiting más estricto: 3 intentos por hora

El panel admin usa la autenticación nativa de Filament, pero con middleware custom que verifica el rol `admin_especialista` o `super_admin`.

#### 5.1.9 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| POST | `/api/auth/register` | Registro nuevo |
| POST | `/api/auth/login` | Login email/password |
| POST | `/api/auth/logout` | Logout (revoca token) |
| GET | `/api/auth/social/{provider}/redirect` | Redirige a OAuth |
| GET | `/api/auth/social/{provider}/callback` | Callback OAuth |
| POST | `/api/auth/forgot-password` | Solicita reset |
| POST | `/api/auth/reset-password` | Ejecuta reset con token |
| POST | `/api/auth/verify-email/{token}` | Verifica email |
| POST | `/api/auth/resend-verification` | Reenvía email verificación |
| POST | `/api/auth/2fa/enable` | Inicia activación 2FA |
| POST | `/api/auth/2fa/confirm` | Confirma código para activar |
| POST | `/api/auth/2fa/disable` | Desactiva 2FA |
| POST | `/api/auth/2fa/challenge` | Valida código en login |
| GET | `/api/me` | Datos del usuario actual |
| PATCH | `/api/me/profile` | Actualiza perfil |
| PATCH | `/api/me/datos-natales` | Actualiza datos natales |
| GET | `/api/me/export` | Inicia exportación GDPR |
| DELETE | `/api/me/account` | Elimina cuenta |
| PATCH | `/api/me/consentimientos` | Revoca consentimientos |

#### 5.1.10 Consideraciones de seguridad del módulo

- Todas las respuestas de error usan mensajes genéricos para evitar enumeración de usuarios
- Passwords nunca se devuelven en respuestas API ni se loguean
- Tokens de verificación y reset son criptográficamente seguros (random_bytes)
- Rate limiting configurado a nivel de endpoint y de usuario
- Eventos críticos (registro, login, 2FA, cambio password, eliminación cuenta) siempre en `audit_log`
- Session fixation prevenida: al login se genera token nuevo, no se reutiliza el anterior
- CSRF tokens en todos los endpoints de escritura

---

### 5.2 Catálogo de consultas

#### 5.2.1 Propósito del módulo

Presentar al cliente la oferta completa de servicios de Chachita de forma atractiva, informativa y que facilite la decisión de compra. Es la **vitrina del negocio** y uno de los puntos más importantes para la conversión.

#### 5.2.2 Vistas del catálogo

**Vista pública (sin login):**

Accesible desde `tarotestrellas.com` y `tarotestrellas.com/servicios`. No requiere autenticación. Incluye:

- Grid visual con las 12 consultas disponibles
- Cada card muestra: imagen, nombre, duración, precio en moneda del visitante (detectada por IP con fallback a USD), badge "Más popular" o "Nuevo" si aplica
- Click en una card abre página de detalle completa
- CTA "Agendar" que redirige a registro/login si no está autenticado
- Filtros: por duración, por rango de precio, por categoría (tarot / astrología / otros)

**Vista autenticada:**

Misma estructura pero con:
- Precios ya convertidos a la moneda del perfil del usuario
- Badges personalizados: "Tu favorito" (más consultado), "10% off en tu primera consulta" si aplica
- Botón "Agendar" funcional (sin redirección a login)
- Atajos rápidos: "Consultar de nuevo" para tipos ya consultados

#### 5.2.3 Página de detalle de consulta

Cuando el cliente hace click en una consulta específica ve:

**Sección hero:**
- Imagen grande de alta calidad (tematizada con la animación 3D correspondiente: cartas flotando para tarot, constelación para carta astral, etc.)
- Nombre del servicio en tipografía serif elegante
- Descripción corta evocativa (ej: *"Un viaje profundo a través de los 78 arcanos para revelar lo que tu alma necesita saber"*)
- Duración, precio, botón CTA "Agendar ahora"

**Sección "Qué incluye":**
- Lista con iconos de lo que recibe el cliente: 1 sesión en vivo de X minutos, grabación disponible por 3 meses, transcripción completa permanente, acceso al agente IA con historial personal, recordatorios automáticos, soporte vía WhatsApp

**Sección "Ideal para ti si...":**
- Lista de situaciones típicas donde esa consulta aporta valor (ej: *"Estás en una encrucijada y necesitas claridad"*, *"Quieres entender el momento astrológico que atraviesas"*)

**Sección "Cómo prepararte":**
- Recomendaciones: ambiente tranquilo, audífonos, pregunta clara en mente, datos natales listos (si la consulta lo requiere)

**Sección "Preguntas frecuentes" (expandibles):**
- ¿Qué pasa si me desconecto durante la sesión?
- ¿Puedo cancelar o reagendar?
- ¿Quién más puede ver mi sesión?
- ¿Cómo sé que mi pago es seguro?

**Sección "Testimonios" (si existen):**
- Reseñas de clientes previos (con permiso, anónimos o con nombre según eligió el cliente)

**CTA final flotante:**
- Botón sticky en móvil con el precio y "Agendar" siempre visible al hacer scroll

#### 5.2.4 Reglas de visualización y lógica

**Precios multi-moneda:**
- Sistema detecta país del usuario (de su perfil si está logueado, o por IP de Cloudflare como fallback)
- Busca en `tipos_consulta_precios` el precio vigente para esa moneda/fecha
- Si no hay precio definido para la moneda detectada, usa USD con conversión a la moneda del usuario usando tasa de cambio del día (cacheada 6 horas, fuente: exchangerate-api.com)
- Muestra el precio en moneda del usuario con nota pequeña: *"Pago procesado en USD — $70"*

**Activación de consultas:**
- Solo se muestran tipos con `tipos_consulta.activo = true`
- Chachita puede desactivar/activar desde admin sin eliminar histórico
- Al desactivar, las citas existentes no se cancelan pero ya no se pueden agendar nuevas

**Orden de visualización:**
- Campo `orden_visualizacion` controla la secuencia
- Chachita lo ajusta desde admin con drag-and-drop

**Destacados y promociones:**
- Badge "Promoción" aparece automáticamente si hay cupón público o descuento vigente aplicable a esa consulta
- Badge "Primera consulta 10% off" aparece solo a usuarios nuevos sin citas previas

**Animaciones 3D contextuales:**
- Al cargar la página de detalle, la animación 3D de fondo corresponde al tipo de consulta:
  - Tarot → carta girando
  - Cartas españolas → cartas flotando en abanico
  - Carta astral → constelación animada
  - Runas → runas grabándose en piedra
  - Otros → partículas doradas genéricas
- Performance: la animación se detiene si el dispositivo tiene batería baja o modo ahorro detectado

#### 5.2.5 Búsqueda y filtros

**Búsqueda:**
- Campo de texto que busca en: nombre, descripción, tags, categoría
- Sugerencias mientras escribe (autocomplete)
- Resultados en tiempo real sin recargar

**Filtros:**
- Duración: 30 min / 1 h / 1.5 h / 2 h
- Rango de precio: sliders
- Categoría: tarot / astrología / otros
- Requiere datos natales: sí / no / cualquiera

Los filtros son combinables y la URL refleja el estado (ej: `/servicios?categoria=astrologia&duracion=2h`) para compartir búsquedas.

#### 5.2.6 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/public/tipos-consulta` | Lista pública del catálogo |
| GET | `/api/public/tipos-consulta/{slug}` | Detalle de una consulta |
| GET | `/api/tipos-consulta` | Lista para usuario autenticado (con precios personalizados) |
| GET | `/api/tipos-consulta/{slug}/disponibilidad-rapida` | Próximos 3 slots disponibles (preview) |

#### 5.2.7 Gestión desde admin

Chachita puede desde el panel admin:
- Crear nuevos tipos de consulta (formulario completo)
- Editar existentes (incluyendo precios multi-moneda con historial)
- Desactivar sin eliminar
- Reordenar con drag-and-drop
- Subir imágenes (se optimizan automáticamente a WebP)
- Previsualizar cómo se ve la página de detalle antes de publicar
- Ver estadísticas por consulta (cantidad agendada, ingresos, tasa de conversión desde página de detalle)

---

### 5.3 Historial de clientes

#### 5.3.1 Propósito del módulo

Dar a cada usuario acceso completo, organizado y útil a todo su historial dentro de la plataforma. Es el módulo que genera mayor valor percibido a largo plazo: mientras más consultas acumula un cliente, más rica y útil se vuelve su ficha personal.

Existen dos vistas independientes con reglas distintas:
- **Vista del cliente**: ve solo lo suyo
- **Vista de Chachita**: ve todo de todos sus clientes

#### 5.3.2 Vista del cliente: "Mis consultas"

Accesible desde el menú principal en `/app/mis-consultas`.

**Sección superior — Resumen visual:**

Pequeñas tarjetas estadísticas:
- Total de consultas realizadas
- Horas totales invertidas en autoconocimiento
- Primera consulta: fecha
- Última consulta: fecha
- Tipo más consultado

**Filtros y búsqueda:**

- Por estado: todas / completadas / próximas / canceladas
- Por tipo de consulta: selector múltiple
- Por rango de fechas: date picker
- Búsqueda de texto: busca en notas propias y en transcripciones (si el consentimiento lo permite)
- Orden: más recientes primero (default), más antiguas, por tipo

**Listado de consultas:**

Cada consulta se muestra como una tarjeta expandible con:

- Fecha y hora de la consulta (en zona horaria del cliente)
- Tipo de consulta con icono
- Estado (chip de color: verde=completada, azul=próxima, gris=cancelada)
- Duración real (si ya se realizó)
- Tema principal (si lo definió al agendar)
- Acciones rápidas al costado:
  - Ver detalle
  - Ver grabación (si disponible)
  - Ver transcripción
  - Ver resumen IA
  - Preguntar al agente sobre esta consulta
  - Reagendar (si aún no realizada)
  - Cancelar (si aún no realizada y cumple política)

**Click en "Ver detalle" abre la página completa de la consulta:**

Estructura de la página de detalle de una consulta pasada:

- **Header:** fecha, hora, tipo, duración, Chachita como especialista
- **Tab 1 — Resumen IA:** el resumen de 2-3 párrafos generado automáticamente + temas y emociones detectadas
- **Tab 2 — Transcripción completa:** texto dividido en segmentos con timestamps, buscable
- **Tab 3 — Grabación:** reproductor de video (solo si aún está dentro de los 3 meses); opción de descargar
- **Tab 4 — Mis notas privadas:** campo de texto libre donde el cliente escribe sus reflexiones post-consulta (no accesible a Chachita ni al agente IA)
- **Tab 5 — Preguntar al agente:** chat con el agente IA con contexto de esta consulta específica

**Aviso visual de eliminación próxima:**

Cuando una grabación está dentro de los 7 días previos a su eliminación automática, aparece banner amarillo: *"Tu grabación será eliminada el 15 de mayo. [Descargar ahora] [Ver detalles]"*. El aviso también se envía por email.

#### 5.3.3 Vista del cliente: página de detalle histórica

Cuando el cliente abre una consulta de hace tiempo (ej: 6 meses atrás), aunque la grabación ya no exista, todos los demás elementos siguen presentes:
- Transcripción completa ✅
- Resumen IA ✅
- Notas privadas ✅
- Metadatos de la cita (fecha, tipo, duración, pagos) ✅
- Chat con agente IA ✅
- Grabación: mensaje *"Esta grabación se eliminó el [fecha] según nuestra política de retención de 3 meses"* ❌

Esto refuerza el diseño de que **el valor persistente está en las transcripciones**, no en los videos.

#### 5.3.4 Exportación del historial

Desde "Mis consultas" el cliente puede hacer click en "Exportar historial completo":
- Genera un PDF estructurado con todas sus consultas completadas
- Por cada una: fecha, tipo, resumen IA, sus notas privadas, temas tratados
- Opción alternativa: exportar en formato JSON para análisis
- Útil para clientes que quieren llevar su proceso personal de forma tangible

#### 5.3.5 Vista de Chachita: ficha del cliente

Accesible desde `/admin/clientes/{uuid}`. Es el **centro de operaciones** de Chachita antes de cada sesión.

**Header de la ficha:**

- Foto del cliente (avatar subido o inicial)
- Nombre completo
- Edad (calculada de fecha nacimiento)
- País + zona horaria
- Signo solar (calculado automáticamente)
- Chips: "Cliente frecuente" (>5 consultas), "Nuevo" (<3 consultas), "Membresía activa"
- Fecha de cumpleaños próximo con countdown (útil para enviar saludo)

**Tab 1 — Resumen General:**

- Total de consultas realizadas
- Tipos más consultados
- Frecuencia promedio (ej: "consulta cada 45 días")
- Ingresos generados por este cliente (cifra y moneda)
- Temas recurrentes (extraídos del análisis IA de todas sus transcripciones): *"Amor (8), Trabajo (5), Familia (3)"*
- Últimas 3 reflexiones/conclusiones del agente IA sobre este cliente

**Tab 2 — Cronología de consultas:**

Timeline visual con cada consulta en orden cronológico. Cada punto en la línea de tiempo muestra fecha, tipo, y acceso a la transcripción/resumen.

**Tab 3 — Datos natales y astrológicos:**

- Carta astral en imagen (generada con servicio externo tipo Astro.com API o similar en Fase 2)
- Datos natales explícitos
- Notas astrológicas de Chachita (campo libre para observaciones recurrentes)

**Tab 4 — Preguntar al agente IA:**

Chat con el agente con acceso al historial completo del cliente. Interfaz conversacional estilo Claude/ChatGPT. Preguntas típicas:
- *"¿Qué temas recurrentes trae este cliente?"*
- *"¿Qué le dije en la consulta de marzo sobre su pareja?"*
- *"¿Qué ha evolucionado en su proceso desde la primera consulta?"*
- *"Resúmeme sus 3 últimas consultas en 5 líneas cada una"*

**Tab 5 — Pagos e historial financiero:**

- Lista de pagos realizados
- Créditos disponibles
- Membresías activas
- Facturas/recibos descargables

**Tab 6 — Notas privadas de Chachita:**

Editor de texto enriquecido donde Chachita escribe observaciones que solo ella ve. Ejemplos:
- "Muy sensible al tema familiar, preferir abordarlo con delicadeza"
- "Prefiere las lecturas intuitivas a las analíticas"
- "Su tía falleció en febrero, mencionar con cuidado"
- "Espera signos concretos, darle acciones prácticas"

Estas notas nunca se comparten con el cliente ni con el agente IA (son reflexiones privadas profesionales).

**Tab 7 — Preparación para próxima consulta:**

Si el cliente tiene una cita agendada, Chachita ve:
- Countdown hasta la cita
- Tipo y tema declarado (si lo definió)
- Pregunta específica del cliente (si la escribió)
- Botón "Generar briefing IA": el agente IA genera un briefing personalizado con lo que Chachita debe tener presente basado en el historial completo

#### 5.3.6 Búsqueda global de clientes (Chachita)

Desde `/admin/clientes` Chachita accede a:
- Lista completa paginada
- Búsqueda por nombre, email, teléfono, país
- Filtros: membresía activa / clientes frecuentes / inactivos más de X meses / por país / por signo solar / primera consulta sin repetir
- Ordenamiento por: nombre, fecha de registro, última consulta, cantidad total de consultas, ingresos generados
- Acción masiva: exportar selección a Excel

#### 5.3.7 Estadísticas visuales para Chachita

En el dashboard admin:
- Gráfico de consultas por mes (últimos 12 meses)
- Top 10 clientes por consultas realizadas
- Distribución geográfica de clientes (mapa mundial con chips de cantidad)
- Distribución por signo solar (curiosidad)
- Tasa de retención (clientes que vuelven)

#### 5.3.8 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/me/consultas` | Lista del cliente autenticado |
| GET | `/api/me/consultas/{uuid}` | Detalle de una consulta |
| GET | `/api/me/consultas/{uuid}/transcripcion` | Transcripción completa |
| GET | `/api/me/consultas/{uuid}/grabacion-url` | URL firmada de grabación |
| GET | `/api/me/consultas/{uuid}/resumen` | Resumen IA |
| PATCH | `/api/me/consultas/{uuid}/notas-privadas` | Actualiza notas del cliente |
| GET | `/api/me/consultas/export/pdf` | Genera PDF de historial |
| GET | `/api/admin/clientes` | Lista admin de clientes |
| GET | `/api/admin/clientes/{uuid}` | Ficha completa |
| PATCH | `/api/admin/clientes/{uuid}/notas` | Notas de Chachita |
| GET | `/api/admin/clientes/{uuid}/briefing` | Genera briefing IA |
| GET | `/api/admin/clientes/{uuid}/estadisticas` | Stats del cliente |

#### 5.3.9 Privacidad y reglas de acceso

- El cliente solo puede ver SUS consultas. Middleware verifica `cita.cliente_id = auth_user.id`
- Chachita puede ver TODAS las consultas de TODOS sus clientes (en Fase 2 multi-especialista: solo las suyas)
- Super admin puede ver todo con fines de soporte, pero los accesos quedan en `audit_log`
- Las **notas privadas del cliente** (Tab 4 vista cliente) **nunca** son accesibles a Chachita ni al agente IA
- Las **notas privadas de Chachita** (Tab 6 vista admin) **nunca** son accesibles al cliente ni al agente IA
- URLs de grabaciones son **firmadas y temporales** (válidas 1 hora), se regeneran en cada acceso
- Acceso a grabación se registra en `audit_log` para trazabilidad

---

### 5.4 Agendamiento y calendario

#### 5.4.1 Propósito del módulo

Permitir al cliente encontrar el horario perfecto para su consulta y reservarlo, manejando correctamente zonas horarias, reglas de disponibilidad de Chachita, descansos obligatorios, validaciones de anticipación y transiciones de estado. Este módulo es el que más lógica de negocio concentra y donde más bugs sutiles pueden aparecer si no se diseña bien.

#### 5.4.2 Flujo completo de agendamiento (cliente)

**Paso 1 — Selección de tipo de consulta:**

El cliente ya sea desde el catálogo o directo desde la ficha de un tipo de consulta, hace click en "Agendar". El sistema pasa a selección de fecha y hora.

**Paso 2 — Vista de calendario:**

Se muestra un calendario con las siguientes características:

- **Vista mensual por defecto** con toggle a vista semanal
- **Días con slots disponibles**: se marcan con puntos dorados (uno por cada slot disponible, hasta 3 visibles)
- **Días completos/no laborables**: en gris claro, no clickables
- **Días muy lejanos (>60 días)**: en gris, con tooltip *"Puedes agendar hasta el [fecha máxima]"*
- **Días muy cercanos (<24h)**: en gris claro, con tooltip *"Requiere mínimo 24h de anticipación"*
- **Hoy**: resaltado en borde dorado
- **Selector de mes**: flechas de navegación + selector rápido
- **Toggle de zona horaria**: muestra *"Ver en mi hora local (Caracas, GMT-4)"* o *"Ver en hora de Chile (GMT-4)"*

**Paso 3 — Selección de horario:**

Al hacer click en un día disponible, aparece panel lateral con los slots disponibles:

- Cada slot es un card clickable con la hora de inicio y fin (en zona horaria del cliente por default)
- Mini nota debajo: *"Equivale a las 15:00 hora Chile"* (si la TZ del cliente no es Chile)
- Si quedan pocos slots (≤3 en el día), aparece badge *"Últimos disponibles"* para crear urgencia honesta
- Slots están ordenados cronológicamente

**Paso 4 — Información adicional:**

Después de elegir slot, el cliente llena un formulario corto:

- **Tema principal** (opcional, selector): Amor / Trabajo / Familia / Salud / Dinero / Espiritual / General
- **Pregunta específica** (opcional, textarea 500 caracteres): *"¿Qué te gustaría que Chachita aborde en esta sesión?"*
- **Datos natales** (solo si la consulta los requiere y el cliente no los tiene en su perfil aún): formulario inline para completarlos

**Paso 5 — Resumen y elección de pago:**

Pantalla de resumen claro antes de pagar:

```
Tu consulta está casi lista

📅 Miércoles 22 de abril de 2026
🕐 15:00 - 17:00 hora Caracas (equivale a 15:00-17:00 Chile)
🔮 Tarot (2 horas)
👤 Con Chachita
💰 Precio total: 65 USD ($ equivale a ~1.250 CLP)
   💵 Abono hoy (20%): 13 USD
   💵 Saldo antes de la sesión (80%): 52 USD

¿Cómo quieres pagar el abono?

  ○ Tarjeta internacional (Stripe) — Se procesa al instante
  ○ Transferencia bancaria en Chile (solo clientes chilenos)

[ Cancelar ]  [ Continuar al pago → ]
```

**Paso 6 — Pago:**

Según elección, va al flujo de Stripe o al de transferencia (Módulo 5.5). Al confirmarse el pago del 20%, la cita queda reservada y el flujo cierra con pantalla de éxito.

**Paso 7 — Confirmación y siguientes pasos:**

Pantalla de éxito:
- ✅ Animación de constelación que se ilumina (animación 3D)
- Resumen de la cita confirmada
- Botón "Agregar a mi calendario" (descarga ICS para Google/Apple/Outlook)
- Recordatorio del pago del 80% pendiente y cuándo se cobrará
- Link a "Mis consultas"
- Email + WhatsApp de confirmación ya enviados automáticamente

#### 5.4.3 Cálculo de disponibilidad (el algoritmo)

Este es el corazón lógico del módulo. Calcular los slots disponibles tiene varios niveles de complejidad.

**Input del algoritmo:**
- `tipo_consulta_id` (define duración)
- `fecha_inicio_rango` y `fecha_fin_rango` (típicamente 1 mes adelante)
- `timezone_cliente`

**Output:**
- Lista de slots disponibles, cada uno con fecha/hora inicio y fin en UTC y en TZ cliente

**Pasos del algoritmo:**

1. **Obtener horario base de Chachita** de `disponibilidad_base` para todos los días del rango
2. **Convertir horario base a UTC** (Chachita define en hora Chile, almacenamos/calculamos en UTC)
3. **Aplicar bloqueos** de `bloqueos_agenda` tipo `bloqueo` (restan tiempo disponible)
4. **Aplicar aperturas extra** de `bloqueos_agenda` tipo `apertura_extra` (suman tiempo disponible)
5. **Obtener citas existentes** no canceladas ni expiradas para el rango
6. **Restar las citas existentes** del tiempo disponible
7. **Generar slots candidatos** de la duración requerida (ej: si la consulta dura 2h, generar slots cada 30min: 10:00-12:00, 10:30-12:30, 11:00-13:00...)
8. **Aplicar regla de anticipación mínima**: eliminar slots que empiecen antes de `ahora + 24h`
9. **Aplicar regla de anticipación máxima**: eliminar slots más allá de `ahora + 60 días`
10. **Aplicar regla de sesiones consecutivas máximas**: para cada slot candidato, verificar si al tomarlo haría que Chachita tenga >2 sesiones sin 30min de descanso
11. **Aplicar descanso obligatorio**: no ofrecer slots que rompan descansos ya programados
12. **Convertir slots finales a TZ del cliente** para presentación

**Performance:**
- Cálculo cachea en Redis por 5 minutos con key `disponibilidad:{tipo_id}:{fecha}:{tz_cliente}`
- Cache se invalida inmediatamente cuando:
  - Se agenda nueva cita
  - Se cancela una cita
  - Chachita modifica disponibilidad base o bloqueos
- Respuesta típica: <100ms con cache, <800ms sin cache

#### 5.4.4 Reservas temporales y expiración

Cuando el cliente llega a la pantalla de pago, la cita se crea inmediatamente en estado `pendiente_abono` y se marca con `reservada_hasta = ahora + X minutos` (X viene de `configuracion_sistema.MINUTOS_VENTANA_TRANSFERENCIA`, default 30).

**Propósito:** evitar que dos clientes agendan el mismo slot en paralelo.

**Mientras está en `pendiente_abono`:**
- El slot NO aparece como disponible para otros clientes
- Si el cliente completa el pago: pasa a `reservada`
- Si no completa en los 30 minutos: un job en cola pasa la cita a `expirada` y libera el slot

**Job `ExpirarReservasJob`** (corre cada minuto):
```php
UPDATE citas
SET estado = 'expirada', deleted_at = NOW()
WHERE estado = 'pendiente_abono'
  AND reservada_hasta < NOW()
```

Además, notifica al cliente por email/WhatsApp: *"Tu reserva para [fecha] expiró. El horario está disponible de nuevo si quieres agendar."*

#### 5.4.5 Gestión de zonas horarias (detalle crítico)

Todas las horas en BD están en **UTC**. Las conversiones ocurren en los bordes del sistema.

**Al mostrar al cliente:**
```javascript
// Frontend — convertir UTC a TZ del cliente
const horaCliente = formatInTimeZone(
  cita.inicio_utc,
  usuario.zona_horaria, // ej: "America/Caracas"
  "dd 'de' MMMM yyyy, HH:mm"
);
```

**Al recibir del cliente:**
```javascript
// Frontend envía siempre UTC + metadatos de TZ
body: {
  inicio_utc: zonedTimeToUtc(slotSeleccionado, usuario.zona_horaria),
  zona_horaria_cliente: usuario.zona_horaria
}
```

**Al calcular disponibilidad de Chachita:**
```php
// Backend — trabaja siempre con horario Chile
$chileNow = Carbon::now('America/Santiago');
$horarioBase = DisponibilidadBase::query()->where('dia_semana', $chileNow->dayOfWeek)->get();

// Luego convierte los slots a UTC para comparar con citas existentes
$inicioSlotUtc = Carbon::parse("{$fecha} {$horaInicio}", 'America/Santiago')->setTimezone('UTC');
```

**Manejo de cambios de horario (DST):**
Chile cambia hora en abril y septiembre. Venezuela no usa DST. USA sí. España sí.
- `DateTimeZone` de PHP y `date-fns-tz` de JS manejan esto automáticamente
- **Riesgo particular**: una cita agendada el 1 de abril para el 15 de abril en Chile. Si el cambio de hora ocurre entre esas fechas, la conversión puede fallar si el código no es cuidadoso. Por eso SIEMPRE almacenamos UTC.

#### 5.4.6 Reagendamientos

**Desde el cliente:**

Flujo desde "Mis consultas" → detalle de cita no realizada → botón "Reagendar":

1. Sistema verifica que la cita sea reagendable (no iniciada, con más de 24h de anticipación, reagendamientos disponibles > 0)
2. Si no cumple: muestra razón específica (ej: *"Faltan menos de 24 horas, no es posible reagendar"*)
3. Si cumple: muestra calendario normal para elegir nueva fecha
4. Al confirmar nueva fecha:
   - Crea registro en `reagendamientos` con referencia a cita original
   - Crea nueva cita vinculada con el mismo pago original (no se cobra de nuevo el abono)
   - Marca cita original como estado `reagendada`
   - El pago se transfiere a la nueva cita
   - Si era el primer reagendamiento: marca `gratuito = true`, resta 1 al contador disponible del cliente
   - Si ya usó su reagendamiento gratuito: muestra mensaje *"Este es tu segundo reagendamiento, se perderá el abono del 20%. ¿Estás seguro?"*
5. Notifica cambio por email + WhatsApp
6. Actualiza el calendario de Chachita automáticamente

**Desde Chachita (emergencia):**

Flujo desde admin → calendario → click en cita → "Mover cita":

1. Chachita selecciona nueva fecha/hora disponible
2. Puede ingresar motivo (*"imprevisto personal"*, *"problema de salud"*, etc.)
3. El sistema actualiza la cita, crea entrada en `reagendamientos` con `reagendado_por = chachita`
4. Notifica al cliente automáticamente (email + WhatsApp): *"Chachita necesitó mover tu cita. Nueva fecha: [X]. Si no te acomoda, puedes reagendar a otro horario sin costo o solicitar reembolso total."*
5. El contador de reagendamientos gratuitos del cliente NO se descuenta (porque fue iniciativa de Chachita)

**Reagendamiento masivo (Chachita):**

Cuando Chachita necesita mover TODAS las citas de un día (ej: emergencia familiar):

1. Desde admin → calendario → vista de día → botón "Mover todas las citas de este día"
2. Selecciona a dónde (día siguiente / día específico / cancelar todas con reembolso)
3. Sistema intenta acomodar las citas en los mismos horarios del día destino si están disponibles
4. Si hay conflictos, Chachita resuelve uno a uno
5. Notificación masiva a todos los clientes afectados
6. Cada cliente recibe link para reagendar si el nuevo horario no le acomoda

#### 5.4.7 Cancelaciones

**Política (ya definida en Decisión 4):**
- **Más de 24h antes**: reembolso del 100% del abono (máximo 1 vez por cliente para evitar abusos)
- **Menos de 24h antes**: se pierde el abono
- **No-show**: se pierde el abono completo

**Desde el cliente:**

Flujo desde "Mis consultas" → detalle → botón "Cancelar":

1. Sistema calcula política aplicable
2. Muestra resumen claro:
   - Si >24h: *"Puedes cancelar sin costo (tu crédito será reembolsado). Tienes 1 cancelación gratuita disponible este año."*
   - Si <24h: *"Estás cancelando con menos de 24 horas de anticipación. Perderás el abono del 20% ($13 USD)."*
3. Requiere confirmación con modal (doble check)
4. Solicita motivo (opcional, dropdown): *"Emergencia personal / Cambio de planes / Problema de salud / Otro"*
5. Al confirmar:
   - Cita pasa a estado `cancelada_cliente`
   - Si aplica reembolso: crea registro en `reembolsos` con estado `pendiente`, job procesa el refund en Stripe o genera crédito
   - Libera el slot en el calendario
   - Notifica a Chachita (email + WhatsApp)
6. Cliente recibe email de confirmación con detalles

**Desde Chachita:**

Puede cancelar cualquier cita desde admin. Siempre reembolsa el 100% al cliente (se asume su responsabilidad). Registra motivo obligatorio para auditoría.

#### 5.4.8 No-show (cliente no se presenta)

Detección automática:
- Job `DetectarNoShowJob` corre 15 minutos después de la hora de inicio de cada cita `confirmada`
- Si el cliente no entró a la sala Daily.co (verifica via API de Daily): marca cita como `no_show`
- Si Chachita tampoco entró: marca la cita como `cancelada_chachita` con motivo "no asistió a la sesión" (caso raro, genera refund al cliente)

Política:
- Cliente pierde el pago completo (20% + 80% si ya se cobró)
- Notificación automática al cliente: *"Lamentamos que no pudiste asistir. Si hubo una emergencia, contacta a Chachita por WhatsApp antes de 24h para evaluar reagendamiento."*
- Chachita puede revertir manualmente el estado a `reagendada` si el cliente justifica

#### 5.4.9 Panel de gestión de disponibilidad (Chachita)

Chachita accede desde admin a `/admin/calendario`:

**Vista Agenda (por defecto):**
- Semana actual con slots de 30min visuales
- Slots ocupados en dorado (con nombre del cliente y tipo de consulta)
- Slots bloqueados en gris (con motivo si se hover)
- Slots disponibles en crema claro
- Click en slot disponible → opción "Bloquear este slot" (con motivo)
- Click en slot bloqueado → opción "Desbloquear" o "Cambiar motivo"
- Click en slot con cita → abre detalle de la cita

**Configuración de horario base:**
- Para cada día de la semana: ON/OFF y rango de horas
- Puede definir múltiples rangos (ej: Lunes 10-13 y 15-19 con pausa de 13-15 para almuerzo)
- Cambios aplican a partir de la próxima semana (no afecta citas ya agendadas)

**Bloqueos y excepciones:**
- Formulario para crear bloqueo: fecha inicio, fecha fin, tipo (bloqueo/apertura), motivo
- Vista de lista con todos los bloqueos activos y pasados
- Botón "Agregar feriado chileno" que pre-carga los feriados del año actual (18 feriados oficiales)

**Configuración avanzada:**
- Parámetros editables: sesiones consecutivas máximas, minutos de descanso, anticipación mínima/máxima
- Cambios se guardan en `configuracion_sistema`
- Confirmación antes de aplicar cambios que afecten citas futuras

#### 5.4.10 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/disponibilidad` | Slots disponibles por tipo y rango |
| POST | `/api/citas` | Crear cita (estado pendiente_abono) |
| GET | `/api/citas/{uuid}` | Detalle de cita |
| POST | `/api/citas/{uuid}/reagendar` | Reagendar cita del cliente |
| POST | `/api/citas/{uuid}/cancelar` | Cancelar cita |
| GET | `/api/citas/{uuid}/calendario-ics` | Descargar archivo .ics |
| GET | `/api/admin/calendario` | Vista agenda de Chachita |
| POST | `/api/admin/bloqueos` | Crear bloqueo |
| PATCH | `/api/admin/bloqueos/{id}` | Editar bloqueo |
| DELETE | `/api/admin/bloqueos/{id}` | Eliminar bloqueo |
| GET | `/api/admin/disponibilidad-base` | Obtener horario base |
| PUT | `/api/admin/disponibilidad-base` | Actualizar horario base |
| POST | `/api/admin/citas/{uuid}/mover` | Mover cita (admin) |
| POST | `/api/admin/citas/mover-dia-completo` | Mover todas las citas de un día |

#### 5.4.11 Edge cases importantes

- **Cliente intenta agendar un slot que se tomó mientras veía el calendario**: el servidor valida al crear la cita. Si el slot ya no está disponible, devuelve error 409 con mensaje amigable y muestra el calendario refrescado.
- **Cliente abre dos ventanas y agenda dos citas simultáneamente**: la segunda falla porque la primera bloqueó el slot.
- **Cliente cambia de zona horaria durante una sesión** (ej: viaja): sus citas existentes mantienen su UTC, solo cambia cómo se muestran. El frontend detecta el cambio y pide actualizar el perfil.
- **Chachita bloquea una hora donde ya hay cita agendada**: el sistema NO permite el bloqueo y pide resolver el conflicto primero (cancelar o reagendar la cita).
- **Cliente agenda para Chile en cambio de horario (DST)**: la librería de fechas maneja correctamente, pero agregamos tests específicos para estas fechas (abril y septiembre).
- **Membresía activa + agendamiento**: si el cliente tiene membresía con consultas disponibles, el sistema ofrece usarla en lugar de cobrar (el pago pasa a `canal: membresia`).
- **Agendamiento con cupón**: cupón se valida al crear la cita y se aplica al precio final. Si el cupón expira entre la reserva y el pago, se respeta el descuento reservado.

---

### 5.5 Pagos

#### 5.5.1 Propósito del módulo

Procesar todos los flujos de cobro del sistema de forma segura, auditable y con la mejor experiencia posible para clientes de distintos países y medios de pago. Es el módulo que más tolerancia a cero errores necesita: cada bug aquí afecta directamente el dinero de Chachita y la confianza de los clientes.

Dos flujos principales de pago:
- **Internacional**: Stripe para cualquier cliente fuera de Chile o chileno que prefiera pagar con tarjeta
- **Chile**: Transferencia bancaria directa con validación automática vía IA

Dos momentos de pago por cita:
- **Abono (20%)**: al reservar
- **Saldo (80%)**: antes de entrar a la videollamada

#### 5.5.2 Flujo Stripe — pago con tarjeta

**Integración técnica:**

Se usa **Stripe Elements** en el frontend y **Laravel Cashier** en el backend. Stripe Elements es un componente que renderiza el formulario de tarjeta en un iframe controlado por Stripe, lo que significa que los datos de tarjeta **nunca tocan nuestro servidor** (compliance PCI-DSS automático).

**Flujo detallado:**

**Paso 1 — Crear PaymentIntent (backend):**

Cuando el cliente confirma la cita y elige tarjeta, el frontend llama `POST /api/citas/{uuid}/pagar/stripe` con `{tipo: "abono_20"}`. El backend:

1. Valida que la cita exista, esté en estado correcto, y pertenezca al cliente
2. Calcula el monto: `cita.precio_final_centavos * 0.20`
3. Calcula la moneda: usa la moneda del perfil del cliente (CLP, USD, MXN, EUR)
4. Llama a Stripe API `paymentIntents.create()`:
   ```json
   {
     "amount": 1300,
     "currency": "usd",
     "automatic_payment_methods": {"enabled": true},
     "metadata": {
       "cita_uuid": "...",
       "tipo": "abono_20",
       "cliente_id": "...",
       "user_email": "..."
     },
     "description": "TarotEstrellas - Abono Tarot 22 abr 2026"
   }
   ```
5. Guarda en `pagos` el registro con estado `pendiente` y `stripe_payment_intent_id`
6. Devuelve al frontend `{client_secret, publishable_key}`

**Paso 2 — Frontend muestra Stripe Elements:**

Carga el SDK de Stripe con la publishable key, inicializa Elements con el client_secret, y renderiza el formulario:
- Campo de tarjeta (número + expiración + CVC en una sola línea, controlado por Stripe)
- Nombre del titular
- Dirección (solo país + código postal, mínimos requeridos)
- Botón "Pagar $13 USD"

**Paso 3 — Confirmación del pago:**

Al hacer click en Pagar, el frontend llama `stripe.confirmPayment()` con el client_secret. Stripe:
- Valida la tarjeta directamente con el emisor
- Si requiere autenticación 3D Secure (común en Latinoamérica), muestra el flujo
- Si aprueba: el PaymentIntent pasa a `succeeded`
- Si rechaza: muestra error específico al cliente (tarjeta inválida, fondos insuficientes, etc.)

**Paso 4 — Webhook confirma el pago (backend):**

Stripe envía webhook `payment_intent.succeeded` a `POST /api/webhooks/stripe`. El backend:

1. Verifica la firma HMAC-SHA256 del webhook (rechaza si firma inválida)
2. Idempotencia: verifica si ya procesó este evento (`stripe_event_id` en tabla de webhooks)
3. Busca el `pago` por `stripe_payment_intent_id`
4. Actualiza pago: `estado = completado`, `pagado_en = now()`
5. Si era abono_20: actualiza cita a estado `reservada`
6. Si era saldo_80: actualiza cita a estado `confirmada` y crea sala Daily.co
7. Dispara evento `CitaReservadaEvent` o `CitaConfirmadaEvent`
8. Estos eventos a su vez disparan:
   - Envío de email de confirmación
   - Envío de WhatsApp de confirmación
   - Generación de recibo PDF y envío
   - Programación de jobs de recordatorios (48h, 3h, 30min antes)
9. Registra evento en `audit_log`

**Paso 5 — Frontend muestra éxito:**

Frontend hace polling al endpoint `GET /api/citas/{uuid}` y detecta el cambio de estado. Muestra pantalla de éxito con animación de constelación.

**Manejo de errores Stripe:**

- Tarjeta rechazada → mensaje específico, cliente puede reintentar con otra tarjeta (la cita sigue en estado `pendiente_abono` con timer)
- Timeout de red → el PaymentIntent queda en `processing`, webhook lo actualiza cuando confirme
- Autenticación 3DS fallida → mensaje, cliente puede reintentar
- Webhook no llega (muy raro) → job `VerificarPagosPendientesJob` corre cada 10 minutos y consulta estado actual de PaymentIntents en `processing` hace >5 min

#### 5.5.3 Flujo Transferencia Bancaria Chile con validación IA

**Paso 1 — Elección de transferencia:**

El cliente (con `pais_residencia = CL` y tipo de pago = transferencia) ve pantalla con:

```
Datos para tu transferencia:

  🏦 Banco:        Banco de Chile
  👤 Titular:      Chachita Pérez González
  📋 RUT:          12.345.678-9
  💳 Cuenta:       Cuenta Corriente N°0123456789
  📧 Email:        transferencias@tarotestrellas.com
  💰 Monto:        $6.500 CLP (20% abono)

  📝 IMPORTANTE: Al transferir, incluye este código en el mensaje/glosa:

        ┌─────────────────────────┐
        │    TE-M4K7-A3B9         │ [📋 Copiar]
        └─────────────────────────┘

  ⏱️ Tiempo restante: 29:42

  [ 📤 Ya transferí, subir comprobante ]
```

**Paso 2 — Cliente hace la transferencia desde su banco:**

Fuera del sistema. Cliente usa su app bancaria.

**Paso 3 — Cliente sube comprobante:**

Click en "Subir comprobante" abre modal con:
- Dropzone para arrastrar foto/PDF
- Botón "Tomar foto" (usa cámara del dispositivo)
- Preview del archivo seleccionado
- Tipos aceptados: JPG, PNG, PDF (máx 5MB)
- Botón "Enviar para validación"

Al subir:
- Archivo se sube a Cloudflare R2 (directamente desde el frontend con presigned URL, sin pasar por el backend)
- Frontend llama `POST /api/citas/{uuid}/pagar/transferencia/comprobante` con la URL de R2
- Backend crea registro en `comprobantes_transferencia` con estado `pendiente` y dispara job `ValidarComprobanteJob`

**Paso 4 — Agente IA valida (job asíncrono):**

Job ejecuta el siguiente pipeline:

1. **Descarga la imagen de R2** al worker
2. **Llama a Claude API con visión**:
   ```json
   {
     "model": "claude-3-5-sonnet-20241022",
     "messages": [{
       "role": "user",
       "content": [
         {"type": "image", "source": {"type": "base64", "data": "..."}},
         {"type": "text", "text": "Este es un comprobante de transferencia bancaria chilena. Extrae estos datos en JSON estricto: {banco_origen, banco_destino, cuenta_destino, rut_titular_destino, monto_clp, fecha_transferencia, hora_transferencia, id_transaccion, mensaje_glosa, tipo_transferencia}. Si algún campo no es legible, ponlo como null. No inventes datos."}
       ]
     }],
     "max_tokens": 500
   }
   ```
3. **Parsea el JSON de la respuesta** y guarda en `comprobantes_transferencia.datos_extraidos`
4. **Aplica las 5 reglas de validación** (decisión 16):
   - Regla 1: cuenta destino coincide con `metodos_pago_chachita.numero_cuenta`
   - Regla 2: monto ≥ monto acordado en `pagos.monto_centavos`
   - Regla 3: `mensaje_glosa` contiene el código de referencia de la cita
   - Regla 4: `id_transaccion` no existe en `comprobantes_transferencia` previos
   - Regla 5: `fecha_transferencia + hora_transferencia` está dentro de los últimos 30 min (hora Chile)
5. **Guarda resultado** en `validaciones_agente` con cada regla (true/false/null)
6. **Toma decisión**:
   - Todas las reglas OK → `aprobar` automático
   - Regla 3 fallida (código ausente) + otras OK → activa lógica flexible (Opción B): busca citas pendientes del cliente, si hay 1 sola → aprobar, si hay varias → `revision_manual`, si hay 0 → `rechazar`
   - Cualquier regla crítica fallida (monto menor, cuenta incorrecta, duplicado, fuera de ventana) → `rechazar`
   - OCR no pudo extraer datos claros → `revision_manual`

**Paso 5 — Acciones según decisión:**

**Si `aprobar`:**
- Actualiza `pago.estado = completado`, `pagado_en = now()`
- Actualiza cita a `reservada` o `confirmada`
- Dispara flujos de confirmación (email, WhatsApp, recibo)
- Tiempo total del proceso: ~15-30 segundos desde subir comprobante

**Si `rechazar`:**
- Registra razón específica
- Envía notificación al cliente con la razón: *"El monto transferido ($5.000) es menor al requerido ($6.500). Por favor transfiere la diferencia o contacta a Chachita."*
- Si es rechazo por duplicado o cuenta incorrecta → alerta urgente a Chachita como posible fraude

**Si `revision_manual`:**
- Notifica a Chachita con resumen del caso y el comprobante
- Chachita abre la cita en admin, ve el comprobante, los datos extraídos, y decide:
  - Aprobar manualmente (firma que se hizo manual en el log)
  - Rechazar con motivo
- Tiempo de respuesta esperado: horas (no bloquea al cliente si el slot aún está vigente)

#### 5.5.4 Flujo del pago del saldo (80%)

Recordatorios automáticos:
- **48h antes**: email + WhatsApp: *"Tu consulta es en 2 días. Recuerda completar el pago del 80% ($52 USD) antes de la sesión. [Pagar ahora]"*
- **3h antes**: segundo recordatorio si aún no pagó
- **30min antes**: último aviso urgente

El link de pago va al mismo flujo Stripe o transferencia, pero con tipo `saldo_80`. Misma validación, mismos estados. Al completarse, la cita pasa a `confirmada` y se genera la sala Daily.co.

Si llega la hora de la cita y el saldo no está pagado:
- Sistema bloquea el acceso a la sala de video
- Cita queda en estado `reservada` (no `confirmada`)
- Cliente ve mensaje: *"Completa el pago del 80% para acceder a tu sesión"*
- Chachita ve en admin el estado y puede decidir: esperar, contactar al cliente, o liberar el horario

#### 5.5.5 Reembolsos

**Tipos de reembolso:**

- **Automático vía Stripe** (para pagos con tarjeta): usa API de Stripe `refunds.create()`. El dinero vuelve a la tarjeta del cliente en 5-10 días hábiles.
- **Manual por transferencia** (para pagos chilenos): Chachita transfiere al cliente desde su banco. Sistema registra la intención, Chachita marca como "procesado" al hacerlo.
- **Crédito en el sistema** (alternativa): en lugar de devolver dinero, acredita el monto en `creditos_cliente` para futuras consultas. Opción ofrecida al cliente en algunas cancelaciones.

**Flujo:**

Desde admin o automáticamente por cancelación del cliente (si aplica política):
1. Crea registro en `reembolsos` con estado `pendiente`
2. Job `ProcesarReembolsoJob` lo toma
3. Según método:
   - Stripe → llama API, guarda `stripe_refund_id`
   - Transferencia manual → marca como "esperando acción de Chachita", le notifica
   - Crédito → crea registro en `creditos_cliente` con `origen = reembolso`
4. Actualiza estado del reembolso
5. Notifica al cliente

#### 5.5.6 Manejo multi-moneda

**Conversión de monedas:**

- Sistema soporta CLP, USD, MXN, EUR (y puede agregar más)
- Precios base se definen en múltiples monedas en `tipos_consulta_precios`
- Cuando no hay precio para la moneda del cliente, se toma el USD y se convierte con tasa del día
- Tasas obtenidas de exchangerate-api.com (tier gratuito: 1,500 calls/mes, cacheadas 6h)
- Al generar PaymentIntent en Stripe, se envía en la moneda del cliente
- Para reportes de Chachita, todos los montos se convierten a CLP usando la tasa del día de la transacción (no la actual) y se guarda en `pagos.monto_equivalente_clp`

**Consideraciones legales:**

Stripe procesa y deposita en Chile en CLP (tras conversión automática con su tasa). Chachita recibe CLP independientemente de la moneda que pagó el cliente. Esto simplifica su contabilidad.

#### 5.5.7 Recibos y comprobantes al cliente

Al completarse cualquier pago:
- Job `GenerarReciboJob` crea PDF usando la librería `barryvdh/laravel-dompdf`
- Template incluye: logo, datos de Chachita (razón social, RUT), datos del cliente, detalle del servicio, monto, método de pago, fecha, número correlativo, disclaimer
- PDF se sube a R2 en `recibos/{pago_id}/recibo.pdf`
- Se adjunta al email de confirmación enviado al cliente
- Queda disponible en "Mis consultas → Detalle → Descargar recibo"

**Nota:** En Fase 1 estos son recibos internos (no documento tributario). En Fase 2 se integra con SII Chile para boletas electrónicas reales.

#### 5.5.8 Membresía 12x2 y paquetes

**Compra de membresía:**

Cliente ve promoción desde home o catálogo: *"Membresía Anual — 12 consultas por el precio de 2 — Ahorra 83%"*. Click abre página detalle con:
- Qué incluye
- Tipos de consulta aplicables
- Condiciones (1 por mes, no acumulable, vigencia 12 meses)
- CTA "Comprar membresía"

El cliente paga el monto completo de una sola vez (Stripe o transferencia). Al confirmar:
- Se crea registro en `membresias` con `fecha_inicio = hoy`, `fecha_fin = hoy + 365 días`, `consultas_incluidas = 12`, `consultas_usadas = 0`
- Cliente recibe email con detalles
- En futuras agendaciones, si la membresía aplica, se ofrece usarla en lugar de pagar

**Uso de membresía al agendar:**

Durante el flujo de agendamiento (paso 5):
- Sistema detecta membresía activa del cliente con consultas disponibles
- Muestra en el paso de pago: *"Tienes membresía activa con 8 consultas disponibles. ¿Usar una consulta de tu membresía? [Sí, usar membresía] [Pagar esta consulta normalmente]"*
- Si elige membresía:
  - Cita se crea directamente en estado `confirmada` (sin pago del 20%)
  - `canal_pago = membresia`
  - `precio_final_centavos = 0`
  - `membresia.consultas_usadas += 1`
  - Se crea registro en `pagos` con monto 0 y canal `membresia` para mantener trazabilidad
- Si ya no tiene consultas disponibles o expiró: sistema ofrece renovar membresía

**Expiración:**

Job diario `ExpirarMembresiasJob`:
```php
UPDATE membresias SET estado = 'expirada'
WHERE fecha_fin < CURDATE() AND estado = 'activa'
```

Notifica al cliente 7 días antes del vencimiento con opción de renovar.

#### 5.5.9 Cupones promocionales

**Creación (Chachita):**

Desde admin → "Promociones" → "Crear cupón":
- Código: `LUNA2026` (editable, validación de unicidad)
- Descripción interna: *"Campaña Instagram abril"*
- Tipo: porcentaje o monto fijo
- Valor: 15
- Moneda (si monto fijo)
- Usos máximos totales: 100 (o ilimitado)
- Usos por cliente: 1 (o más)
- Vigencia: desde/hasta
- Monto mínimo de compra: $20 USD (o ninguno)
- Solo primera consulta: sí/no
- Tipos de consulta aplicables: todos o selección

**Uso (cliente):**

En el paso 5 de agendamiento, antes del pago, campo "¿Tienes un código de descuento?":
- Cliente ingresa código
- Sistema valida:
  - Cupón existe y está activo
  - No excedió usos máximos totales
  - No excedió usos del cliente
  - Está en vigencia
  - Monto cumple mínimo
  - Cliente califica (primera consulta si aplica)
  - Tipo de consulta aplicable
- Si válido: muestra descuento aplicado, actualiza precio
- Si inválido: mensaje específico (*"Este cupón ya expiró"* / *"Ya usaste este cupón"* / etc.)

Al pagar, el cupón se registra en `citas.cupon_id` y se incrementa `cupones.usos_totales`.

#### 5.5.10 Descuento de primera consulta (automático)

Se aplica automáticamente sin código manual:
- Sistema detecta que el cliente no tiene citas completadas previamente
- Muestra precio con descuento del 10% tachando el precio original: *~~$65~~ $58.50 USD — 10% primera consulta*
- Badge visual: *"¡Primera consulta! 10% de descuento aplicado"*
- Se marca `citas.es_primera_consulta = true`
- Si el cliente cancela y reagenda: mantiene el descuento si aún es su primera consulta realizada

#### 5.5.11 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| POST | `/api/citas/{uuid}/pagar/stripe` | Crear PaymentIntent |
| GET | `/api/citas/{uuid}/pagar/transferencia/datos` | Obtener datos bancarios |
| POST | `/api/citas/{uuid}/pagar/transferencia/comprobante` | Subir comprobante |
| GET | `/api/pagos/{uuid}` | Detalle de pago |
| POST | `/api/webhooks/stripe` | Webhook Stripe (público, con firma) |
| GET | `/api/me/membresias` | Mis membresías |
| POST | `/api/me/membresias/comprar` | Iniciar compra de membresía |
| POST | `/api/cupones/validar` | Validar cupón |
| GET | `/api/me/creditos` | Créditos disponibles |
| GET | `/api/admin/reembolsos` | Listado paginado de reembolsos |
| GET | `/api/admin/reembolsos/{uuid}` | Detalle + timeline de reembolso |
| GET | `/api/admin/reembolsos/metricas` | KPIs de reembolsos |
| GET | `/api/admin/reembolsos/export` | Export CSV de reembolsos |
| POST | `/api/admin/reembolsos` | Crear reembolso manual |
| POST | `/api/admin/reembolsos/{uuid}/procesar` | Procesar/reintentar/marcar completado |
| POST | `/api/admin/comprobantes/{id}/aprobar` | Aprobar manualmente |
| POST | `/api/admin/comprobantes/{id}/rechazar` | Rechazar manualmente |

#### 5.5.12 Seguridad y auditoría

- Todos los webhooks validan firma HMAC
- Idempotencia: cada evento de Stripe se procesa una sola vez (tabla `stripe_webhook_events` con `event_id` unique)
- Rate limiting específico en endpoints de pago: 10 req/min por usuario
- Comprobantes tienen URLs firmadas y temporales
- Todos los cambios de estado de pagos quedan en `audit_log`
- Montos siempre en centavos/enteros (nunca float)
- Tests automáticos cubren todos los edge cases (pagos fallidos, timeouts, doble-cobro, etc.)

---

### 5.6 Videollamada y grabación

#### 5.6.1 Propósito del módulo

Proveer el espacio virtual donde ocurre la consulta en sí: la videollamada entre Chachita y el cliente. Este módulo debe ser **absolutamente confiable** — cualquier problema técnico aquí arruina la experiencia (Chachita pierde tiempo, cliente pierde dinero, reputación dañada).

La integración es con Daily.co usando su SDK oficial `@daily-co/daily-js`.

#### 5.6.2 Creación de la sala

**Momento de creación:**

La sala Daily.co se crea **al confirmarse el pago del 80%** (cita pasa a `confirmada`), no al reservar. Esto evita crear salas para citas que nunca se concreten.

**Proceso:**

Job `CrearSalaDailyJob` se dispara cuando se confirma el pago:

1. Llama a Daily API `POST /rooms` con configuración:
   ```json
   {
     "name": "te-{cita_uuid_short}",
     "privacy": "private",
     "properties": {
       "max_participants": 2,
       "start_video_off": false,
       "start_audio_off": false,
       "exp": "unix_timestamp_cita_fin_utc + 1800",
       "enable_chat": true,
       "enable_recording": "cloud",
       "recording": {
         "type": "audio-video",
         "layout": {"preset": "default"}
       },
       "enable_transcription_storage": false
     }
   }
   ```
2. Daily devuelve `{name, url, id, config}`
3. Genera tokens de acceso con `POST /meeting-tokens`:
   - Token Chachita: con permisos de admin, owner, control de grabación
   - Token cliente: con permisos básicos de participante
4. Guarda todo en `sesiones_video` (tokens cifrados con Laravel encrypt)
5. Envía notificación a cliente y Chachita con link a la sala (no el link directo de Daily, sino el link interno `/app/sala/{cita_uuid}` que luego carga la sala embebida)

**Configuración de grabación:**

Daily.co graba en la nube con configuración:
- Formato: MP4 (H.264 + AAC)
- Resolución: 720p (1280x720) — balance entre calidad y tamaño
- Layout: default (grid de participantes)
- Audio: estéreo, 48kHz
- Upload automático a nuestro bucket R2 (configurado via Daily dashboard, Daily usa credenciales IAM de R2 que creamos)

#### 5.6.3 Flujo del cliente al entrar a la sala

**Paso 1 — Acceso a la sala:**

Cliente recibe notificación 30min antes. En "Mis consultas" aparece botón destacado "Entrar a mi sesión" que se activa a partir de 15min antes de la hora de inicio.

Click en el botón → navega a `/app/sala/{cita_uuid}`.

**Paso 2 — Verificaciones pre-sala:**

Antes de cargar la sala Daily, sistema verifica:
- Cita existe y pertenece al cliente
- Cita está en estado `confirmada` (pago completo)
- Hora actual está entre 15min antes y 30min después del fin programado
- Sala Daily fue creada

Si alguna falla, muestra mensaje específico (*"Tu pago del 80% está pendiente. [Pagar ahora]"* / *"Esta sesión ya terminó"* / etc.).

**Paso 3 — Consentimiento de grabación:**

Modal obligatorio antes de entrar a la sala:

```
  🔴 Esta sesión será grabada y transcrita

  Al iniciar la sesión:
  ✓ La conversación completa se graba automáticamente
  ✓ La grabación se conserva 3 meses
  ✓ La transcripción se conserva indefinidamente
  ✓ Solo tú y Chachita tienen acceso
  ✓ Puedes eliminar tus datos en cualquier momento desde tu perfil

  [ ] Confirmo haber leído y acepto la grabación y transcripción
      de esta sesión para mi beneficio y el servicio de Chachita.

  [ Cancelar ]     [ Entrar a la sala ]
```

Al aceptar:
- Se marca `sesiones_video.consentimiento_grabacion_aceptado = true`
- Se registra timestamp e IP
- Se crea consentimiento en tabla `consentimientos` con versión del documento

**Paso 4 — Test pre-sala (opcional pero recomendado):**

Pantalla breve con:
- Test de cámara: preview de la cámara del cliente
- Test de micrófono: visualizador de ondas de audio
- Selector de cámara/mic si tiene varios dispositivos
- Botón "Todo listo, entrar"

Daily.co SDK tiene este test integrado.

**Paso 5 — En la sala:**

Sala embebida ocupa toda la pantalla (modo inmersivo) con:
- Video de Chachita principal (lado grande)
- Video del cliente (esquina inferior derecha, pequeño)
- Controles abajo: mute audio, apagar cámara, chat, pantalla compartida, colgar
- Indicador rojo "🔴 Grabando" siempre visible
- Reloj de duración de la sesión
- Tiempo restante visible (duración contratada)

**Paso 6 — Finalización:**

- Cuando se cumple la duración contratada: aparece aviso a ambos *"Quedan 5 minutos"*, luego *"Quedan 2 minutos"*
- Cualquiera puede colgar con el botón de la UI
- Al colgar: Daily.co cierra la sala, marca la grabación como finalizada
- Sistema marca `sesiones_video.fin_real = now()`
- Pantalla post-sesión para el cliente: *"¡Gracias por tu consulta! Tu grabación y transcripción estarán disponibles en unos minutos."*

#### 5.6.4 Flujo de Chachita al entrar a la sala

Similar al cliente pero con diferencias:

- Entra desde admin → calendario → click en cita del día → botón "Iniciar sesión"
- No ve consentimiento de grabación (ella tiene consentimiento general como profesional, documentado aparte)
- Tiene controles de admin: puede silenciar al cliente si hay problema, finalizar la sesión, expulsar en caso extremo
- Ve el reloj de tiempo contratado con mayor prominencia (necesita gestionar el tiempo)
- Ve panel lateral con:
  - Pregunta del cliente (si la escribió)
  - Datos natales del cliente (si aplica)
  - Briefing IA del cliente (si lo generó antes)
  - Espacio para tomar notas que se guardan en la cita

#### 5.6.5 Problemas comunes y su manejo

**Cliente no puede conectarse:**
- Sistema detecta que pasaron 10 min de la hora de inicio sin que ingrese
- Envía WhatsApp: *"Chachita te está esperando. ¿Todo bien? [Reintentar conexión]"*
- Botón "Avisar a Chachita" que manda mensaje directo

**Se corta la conexión durante la sesión:**
- Daily.co intenta reconectar automáticamente
- Si la reconexión es exitosa (<60 seg), la sesión continúa sin perder la grabación
- Si falla, el cliente puede volver a entrar a la misma URL y la sala lo acepta (manteniendo la grabación en curso)

**Chachita no aparece:**
- Sistema detecta que pasaron 5 min de la hora de inicio sin Chachita
- Envía WhatsApp a Chachita con alerta urgente
- Cliente ve mensaje: *"Chachita debería conectarse pronto. Esperando..."*
- Si no aparece en 15 min: cliente tiene opción de reagendar sin costo o reembolso

**Grabación falla:**
- Daily.co notifica via webhook si la grabación falló
- Sistema marca `sesiones_video.estado = fallida`
- Alerta urgente a admin
- Se ofrece al cliente reagendar gratis o transcripción parcial si tenemos audio

**Cliente quiere finalizar antes:**
- Al colgar, pide confirmación: *"¿Estás seguro de terminar la sesión?"*
- Si confirma, sesión termina y se procesa normalmente
- Si la sesión duró <50% del tiempo contratado por decisión del cliente, no hay reembolso (política clara en ToS)

#### 5.6.6 Webhooks de Daily.co

Eventos que recibimos en `POST /api/webhooks/daily`:

- `meeting.started`: alguien entró a la sala por primera vez
- `meeting.ended`: sala cerró (todos salieron o timeout)
- `recording.started`: comenzó la grabación
- `recording.ready`: grabación lista en R2 (dispara pipeline de transcripción)
- `recording.error`: falló la grabación

Cada webhook:
- Valida firma HMAC con secret de Daily
- Es idempotente (procesa una sola vez)
- Actualiza estado de `sesiones_video` o `grabaciones`
- Dispara eventos internos para el siguiente paso del flujo

#### 5.6.7 Almacenamiento y eliminación de grabaciones

**Storage en R2:**
- Bucket: `tarotestrellas-media`
- Path: `videos/{cita_uuid}/grabacion.mp4`
- Cifrado en reposo (server-side encryption de R2)
- URLs firmadas temporales (1h) para reproducción

**Acceso:**
- Cliente: puede ver desde "Mis consultas" usando URL firmada que se regenera cada acceso
- Chachita: mismo mecanismo
- Nunca se expone URL pública directa de R2

**Eliminación automática a los 3 meses:**

Job diario `EliminarVideosVencidosJob`:
```php
$grabaciones = Grabacion::where('programada_eliminar_en', '<=', today())
    ->where('estado', 'disponible')
    ->get();

foreach ($grabaciones as $grabacion) {
    // 7 días antes: notificar al cliente
    // En el día programado: eliminar
    R2::delete($grabacion->r2_key);
    $grabacion->update([
        'estado' => 'eliminada',
        'eliminada_en' => now(),
    ]);
    // La transcripción permanece viva
}
```

**Pre-aviso 7 días antes:**

Job `AvisarEliminacionProximaJob`:
- Corre diariamente
- Encuentra grabaciones que se eliminarán en 7 días
- Envía email al cliente con link de descarga
- Marca flag para no reenviar

#### 5.6.8 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/citas/{uuid}/sala` | Info de la sala (url, tokens, configuración) |
| POST | `/api/citas/{uuid}/sala/consentimiento` | Registra consentimiento pre-entrada |
| POST | `/api/citas/{uuid}/sala/entrada` | Marca entrada del cliente |
| POST | `/api/citas/{uuid}/sala/salida` | Marca salida/finalización |
| POST | `/api/webhooks/daily` | Webhook Daily.co (con firma) |
| GET | `/api/grabaciones/{uuid}/url` | URL firmada temporal |
| POST | `/api/grabaciones/{uuid}/descarga` | Registrar descarga |

---

### 5.7 Transcripción y procesamiento IA

#### 5.7.1 Propósito del módulo

Convertir cada grabación en un activo de conocimiento permanente: transcripción escrita + resumen IA + embeddings semánticos. Este módulo es lo que hace que TarotEstrellas sea **más que una plataforma de videollamadas** — construye memoria persistente del proceso de cada cliente.

#### 5.7.2 Pipeline completo post-sesión

Cuando Daily.co notifica `recording.ready`, se dispara una cadena de jobs en cola:

```
recording.ready webhook
  ↓
Job 1: DescargarAudioJob
  ├─ Descarga mp4 de R2 al worker (temporal)
  ├─ Extrae audio con ffmpeg (convierte a mp3 16kHz mono)
  └─ Sube mp3 a R2 en audios/{cita_uuid}/audio.mp3
  ↓
Job 2: TranscribirAudioJob
  ├─ Llama a OpenAI Whisper API con el mp3
  ├─ Recibe transcripción con timestamps por segmento
  ├─ Guarda en transcripciones (contenido_completo + segmentos_json)
  └─ Elimina audio.mp3 de R2 (ya no se necesita)
  ↓
Job 3: GenerarResumenJob
  ├─ Llama a Claude Haiku con la transcripción completa
  ├─ Prompt: "Genera resumen de 2-3 párrafos, detecta temas, emociones..."
  ├─ Parsea JSON de respuesta
  └─ Guarda en resumenes_consulta
  ↓
Job 4: GenerarEmbeddingsJob
  ├─ Divide transcripción en fragmentos de ~500 tokens
  ├─ Llama a OpenAI text-embedding-3-small para cada fragmento
  └─ Guarda vectores en embeddings_transcripciones
  ↓
Job 5: NotificarClienteJob
  └─ Email + WhatsApp: "Tu transcripción y resumen están listos"
```

**Tiempo total del pipeline:** 2-5 minutos para una sesión de 2h, corriendo en paralelo donde es posible.

#### 5.7.3 Transcripción con Whisper

**Configuración API:**

```php
$response = OpenAI::audio()->transcribe([
    'model' => 'whisper-1',
    'file' => $audioStream,
    'response_format' => 'verbose_json',
    'language' => 'es',
    'timestamp_granularities' => ['segment'],
    'prompt' => 'Consulta de tarot y astrología con Chachita. Términos frecuentes: arcano, carta astral, ascendente, luna, sol, venus, marte, saturno, retrógrado.',
]);
```

**Notas importantes:**

- El parámetro `prompt` ayuda a Whisper a transcribir correctamente terminología esotérica que de otro modo podría malinterpretar
- Se especifica `language: 'es'` para evitar detección automática errónea en clientes con acento fuerte
- Costo: ~$0.006 USD por minuto de audio. Una sesión de 2h ≈ $0.72 USD
- Límite del API: 25MB de audio por request. Para sesiones >2h, se divide el audio y se transcriben chunks por separado, luego se concatenan

**Output guardado:**

```json
{
  "contenido_completo": "Chachita: Hola María, bienvenida...",
  "segmentos_json": [
    {
      "id": 0,
      "inicio_ms": 0,
      "fin_ms": 3500,
      "hablante": "speaker_0",
      "texto": "Hola María, bienvenida a tu sesión.",
      "confianza": 0.98
    },
    ...
  ],
  "idioma_detectado": "es",
  "palabras_total": 12450,
  "duracion_audio_segundos": 7200,
  "confianza_promedio": 0.94,
  "costo_usd_centavos": 72
}
```

**Identificación de hablantes (diarización):**

Whisper standard no hace diarización automática. Opciones:

- **Opción A (MVP)**: no identificar hablantes, dejar el texto plano. Chachita y cliente pueden inferirlos del contexto.
- **Opción B (Fase 2)**: integrar AssemblyAI o pyannote para diarización (quién dijo qué). Costo adicional ~$0.015/min.

**Recomendación MVP**: ir con Opción A. La transcripción sin diarización ya es muy útil. Diarización es nice-to-have para Fase 2.

#### 5.7.4 Generación de resumen con Claude

**Prompt para Claude Haiku:**

```
Eres un asistente que genera resúmenes de consultas de tarot y astrología.

Abajo está la transcripción de una sesión entre Chachita (la especialista) y un cliente.
Tu tarea es generar un resumen profesional, empático y útil.

Devuelve ESTRICTAMENTE un JSON con esta estructura:

{
  "resumen_corto": "Resumen de 2-3 párrafos que capture la esencia de la consulta...",
  "temas_detectados": ["amor", "trabajo", ...],
  "emociones_detectadas": ["incertidumbre", "esperanza", ...],
  "preguntas_cliente": ["pregunta 1", "pregunta 2", ...],
  "recomendaciones": ["recomendación 1", ...],
  "tono_general": "reflexivo | preocupado | optimista | buscando_claridad | ..."
}

Importante:
- No inventes contenido que no esté en la transcripción
- Respeta la privacidad: si hay nombres de terceros, anonimízalos (ej: "su pareja", "un familiar")
- El tono debe ser respetuoso y profesional
- Captura la esencia del proceso, no solo hechos

TRANSCRIPCIÓN:
{contenido_completo}
```

**Modelo y costo:**
- Claude 3.5 Haiku (más rápido y barato que Sonnet, suficiente para resúmenes)
- Tokens input: ~15-20k (transcripción de 2h)
- Tokens output: ~500
- Costo: ~$0.02-0.03 USD por resumen

**Validación:**

Al recibir respuesta, validamos que sea JSON válido con la estructura esperada. Si falla:
- Retry 1 vez con prompt reforzado
- Si falla 2 veces: marca para revisión manual, notifica al admin, y genera resumen genérico básico

#### 5.7.5 Generación de embeddings

**Propósito:**

Los embeddings son vectores numéricos que capturan el significado semántico del texto. Permiten búsquedas inteligentes como:
- *"¿Cuándo María me habló de su madre?"* → el agente busca en embeddings de todas las transcripciones de María
- *"¿Qué consultas tocaron temas de duelo?"* → búsqueda semántica cross-cliente (solo Chachita)

**Proceso:**

1. Fragmentación: divide transcripción en chunks de ~500 tokens con overlap de 50 tokens (para no perder contexto en los bordes)
2. Para cada fragmento, llama a OpenAI:
   ```php
   $embedding = OpenAI::embeddings()->create([
       'model' => 'text-embedding-3-small',
       'input' => $fragmento,
   ]);
   ```
3. Guarda el vector (1536 dimensiones) en `embeddings_transcripciones.embedding` como JSON array

**Costo:**
- text-embedding-3-small: $0.02 / 1M tokens
- Transcripción de 2h ≈ 20k tokens ≈ $0.0004 USD
- Irrelevante

**Uso en búsqueda semántica:**

Cuando Chachita o cliente hacen pregunta al agente IA:
1. Generamos embedding de la pregunta
2. Comparamos con embeddings de transcripciones relevantes (solo las del cliente consultado)
3. Calculamos similitud coseno en PHP (para volumen inicial bajo es suficiente)
4. Tomamos top-5 fragmentos más relevantes
5. Los incluimos como contexto en el prompt a Claude Sonnet

#### 5.7.6 Agente IA conversacional

**Arquitectura del agente:**

```
Usuario pregunta
  ↓
Sistema genera embedding de la pregunta
  ↓
Búsqueda en embeddings_transcripciones:
  ├─ Filtro por cliente (solo sus transcripciones)
  ├─ Top 5 fragmentos más similares
  └─ También incluye todos los resumenes_consulta del cliente
  ↓
Construye prompt con:
  ├─ System prompt (rol del agente)
  ├─ Contexto del cliente (datos básicos, cantidad de consultas, temas recurrentes)
  ├─ Fragmentos relevantes recuperados (con fechas y contexto)
  ├─ Resúmenes IA de todas las consultas (si es razonable en tokens)
  └─ Pregunta actual + historial de conversación previa
  ↓
Llama a Claude Sonnet API (streaming)
  ↓
Respuesta streamed al frontend en tiempo real
  ↓
Guarda en agente_conversaciones y agente_mensajes
```

**System prompt del agente (vista Chachita):**

```
Eres un asistente profesional que apoya a Chachita, especialista en tarot y astrología,
a preparar sus consultas y revisar el progreso de sus clientes.

Tu rol:
- Conocer el historial completo del cliente consultado
- Responder preguntas específicas sobre sus consultas previas
- Detectar patrones, temas recurrentes, evoluciones en el proceso del cliente
- Sugerir aspectos que podrían abordarse en la próxima sesión
- Mantener un tono profesional, empático y respetuoso

Restricciones importantes:
- Nunca inventes información que no esté en las transcripciones o resúmenes
- Si no sabes algo, dilo claramente
- Respeta la privacidad del cliente: no compartas con terceros, no especules
- No reemplaces el juicio profesional de Chachita, solo complementas
- Si detectas señales de crisis o riesgo (mención de autolesión, violencia, etc.), mencionarlo a Chachita de forma clara

Contexto del cliente:
{datos_cliente}

Historial resumido:
{resumenes_consultas}

Fragmentos específicos relevantes a la pregunta:
{fragmentos_relevantes}
```

**System prompt del agente (vista cliente):**

```
Eres un asistente personal del cliente {nombre_cliente}, ayudándole a reflexionar sobre
sus propias consultas de tarot y astrología con Chachita.

Tu rol:
- Acompañar al cliente en la reflexión sobre sus sesiones previas
- Responder preguntas específicas sobre lo que se habló
- Ayudarle a identificar patrones en su proceso personal
- Mantener un tono empático, cálido y respetuoso

Restricciones:
- Solo respondes sobre las consultas de este cliente, no sobre otros
- Nunca inventes contenido que no esté en las transcripciones
- No das consejos médicos, legales ni financieros — redirige a profesionales
- Si detectas señales de crisis (pensamientos de daño, etc.), sugiere buscar ayuda profesional inmediata
- Mantén el tono espiritual/reflexivo que corresponde al rubro, pero sin promesas falsas

Tu historial de consultas:
{resumenes_consultas_del_cliente}

Fragmentos relevantes:
{fragmentos_relevantes}
```

**Modelo y costo:**
- Claude 3.5 Sonnet (mejor comprensión de contextos largos y matices)
- Tokens por conversación típica: ~5-10k input, ~500-1000 output
- Costo: ~$0.03-0.08 USD por interacción
- Cliente promedio podría hacer 5-20 interacciones al mes → ~$1-5 USD/mes por cliente activo

**Límites de uso:**

Configurables desde admin:
- Cliente: 30 mensajes/día al agente sobre sí mismo
- Chachita: ilimitado (pero logueado para control de costos)
- Si un cliente excede límite: mensaje *"Llegaste a tu límite diario. Vuelve mañana o contacta a Chachita"*

#### 5.7.7 Calidad y supervisión

**Métricas que monitoreamos:**

- Tiempo de procesamiento del pipeline post-sesión
- Tasa de éxito vs fallos de transcripción
- Confianza promedio de transcripciones
- Tokens consumidos por cliente
- Costo total IA mensual (dashboard admin)

**Supervisión humana:**

Dashboard admin muestra:
- Transcripciones con confianza <85% (revisar calidad)
- Resúmenes que fallaron el parseo JSON
- Jobs IA fallidos pendientes de reintento
- Costo mensual IA con alertas si excede umbral

**Mejora continua:**

- Chachita puede reportar errores en transcripciones específicas → se marca para mejora
- Se puede editar manualmente una transcripción o resumen si tiene errores graves
- Ediciones quedan en historial (versionado)

#### 5.7.8 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/me/transcripciones/{uuid}` | Obtener transcripción |
| GET | `/api/me/resumenes/{uuid}` | Obtener resumen IA |
| POST | `/api/agente/conversaciones` | Iniciar nueva conversación |
| GET | `/api/agente/conversaciones` | Listar conversaciones |
| POST | `/api/agente/conversaciones/{uuid}/mensajes` | Enviar mensaje (stream) |
| GET | `/api/agente/conversaciones/{uuid}/mensajes` | Listar mensajes |
| DELETE | `/api/agente/conversaciones/{uuid}` | Archivar conversación |
| POST | `/api/admin/agente/briefing/{cliente_uuid}` | Generar briefing pre-consulta |
| POST | `/api/admin/transcripciones/{uuid}/corregir` | Editar transcripción |
| GET | `/api/admin/ia/metricas` | Métricas de uso IA |

---

### 5.8 Promociones, paquetes y membresías (gestión administrativa)

#### 5.8.1 Propósito del módulo

Centralizar la gestión de todas las estrategias comerciales de Chachita: cupones, paquetes, membresías, descuentos y promociones. La lógica de cómo se aplican al pagar ya se documentó en el Módulo 5.5. Aquí nos enfocamos en cómo Chachita las crea, administra, monitorea y optimiza desde el panel admin.

El módulo está diseñado para ser **100% autoservicio**: Chachita puede crear cualquier promoción sin necesitar soporte técnico.

#### 5.8.2 Dashboard de promociones

Accesible desde admin → "Promociones". Vista general con:

**Indicadores en tiempo real:**
- Cupones activos actualmente
- Membresías activas
- Paquetes activos
- Descuento de primera consulta: activado/desactivado
- Ingresos por promociones este mes (cuánto se facturó gracias a cupones/membresías)
- Ahorro otorgado a clientes este mes (cuánto se descontó en total)

**Tabla resumen:**

| Tipo | Nombre | Usos | Ingresos generados | Estado |
|------|--------|------|---------------------|--------|
| Cupón | LUNA2026 | 23/100 | $1,200 USD | Activo |
| Membresía | Anual 12x2 | 5 activas | $650 USD | Activo |
| Primera consulta | -10% automático | 45 usos | $2,800 USD | Activo |

#### 5.8.3 Gestión de cupones

**Crear cupón:**

Formulario completo desde admin → "Promociones" → "Nuevo cupón":

- **Código**: texto alfanumérico. Validación de unicidad en tiempo real. Botón "Generar aleatorio" (ej: `STARS-7K2M`)
- **Descripción interna**: solo visible en admin (ej: *"Campaña TikTok abril 2026"*)
- **Tipo de descuento**: porcentaje o monto fijo
- **Valor**: número. Si % → validación máximo 100. Si monto fijo → requiere moneda
- **Vigencia**: fecha inicio y fecha fin (date pickers)
- **Límites de uso**: uso máximo total (o ilimitado), uso máximo por cliente
- **Monto mínimo de compra**: monto en la moneda seleccionada (o sin mínimo)
- **Solo primera consulta**: toggle
- **Tipos de consulta aplicables**: multiselect (o "todos")
- **Acumulable con otros descuentos**: toggle (si un cliente tiene membresía + cupón, ¿aplican ambos?)
- **Preview**: antes de crear, muestra cómo se verá para el cliente

**Monitoreo de cupón activo:**

Página de detalle de cada cupón muestra:
- Código y configuración completa
- Gráfico de uso por día (últimos 30 días)
- Lista de clientes que lo usaron (con fecha, cita, monto descontado)
- Tasa de conversión: cuántos aplicaron el cupón vs cuántos completaron la cita
- Botón "Desactivar" (no elimina, solo lo hace no-aplicable)
- Botón "Duplicar" (crea otro cupón con misma configuración y código nuevo)
- Botón "Extender vigencia" (cambia fecha fin sin crear uno nuevo)

**Cupones especiales pre-configurados:**

Al instalar el sistema, Chachita tiene disponible una sección "Crear cupón rápido" con templates:
- **"Cupón de cumpleaños"**: 15% off, un solo uso, vigencia 7 días, se envía automáticamente al cliente en su cumpleaños
- **"Cupón de referido"**: 10% off para el nuevo cliente + crédito de $5 USD para el que refirió
- **"Cupón de regreso"**: 20% off para clientes inactivos por más de 3 meses, se envía automáticamente

Estos templates se pueden activar/desactivar desde admin. Cuando están activos, el sistema dispara los cupones automáticamente sin intervención de Chachita.

#### 5.8.4 Gestión de paquetes

**Crear paquete:**

Admin → "Promociones" → "Nuevo paquete":

- **Nombre**: texto libre (ej: *"Pack 3 consultas"*)
- **Descripción**: texto visible para el cliente
- **Tipo**: paquete fijo o membresía recurrente
- **Consultas incluidas**: número
- **Vigencia**: días desde compra (ej: 90 días para pack, 365 para membresía anual)
- **Precio**: monto + moneda
- **Ahorro vs compra individual**: se calcula automáticamente y se muestra (ej: *"Ahorras 83%"*)
- **Tipos de consulta aplicables**: multiselect o "todos"
- **Destacado**: toggle para mostrar con badge especial en UI
- **Imagen**: upload de imagen promocional

**Paquete "Membresía Anual 12x2" (pre-configurado):**

Ya definido en decisiones previas:
- 12 consultas en 12 meses (1 por mes)
- Precio equivalente a 2 consultas del tipo más popular
- Se consume al agendar si el cliente elige usarla
- No acumulable: si no usa la consulta del mes, la pierde (decisión que dejamos parametrizable)

**Monitoreo de paquetes:**

- Paquetes/membresías vendidos por periodo
- Consultas consumidas vs disponibles (tasa de utilización)
- Clientes con membresía próxima a vencer (para campaña de renovación)
- Ingresos por paquetes vs consultas individuales

#### 5.8.5 Descuento de primera consulta

**Configuración:**

Admin → "Promociones" → "Primera consulta":

- **Activo**: toggle global
- **Porcentaje de descuento**: configurable (default 10%)
- **Tipos de consulta aplicables**: todos o selección
- **Mensaje personalizado**: texto que se muestra al cliente (ej: *"¡Bienvenida! Tu primera consulta tiene 10% de descuento"*)

**Lógica automática:**

El sistema detecta automáticamente si el cliente nunca completó una cita:
- Si `citas.completada WHERE cliente_id = X` count = 0 → aplica descuento
- Se aplica automáticamente sin código manual
- Se muestra visualmente en el catálogo y en el checkout
- Se registra en `citas.es_primera_consulta = true`

**Reportes:**

- Cuántos clientes nuevos usaron el descuento
- Tasa de retorno después de la primera consulta con descuento (¿vuelven a agendar?)
- Ingresos perdidos vs ganados por el descuento (análisis ROI)

#### 5.8.6 Sistema de referidos (estructura para futuro)

No se implementa en Fase 1 pero la BD está preparada. En Fase 2:
- Cliente recibe código de referido único
- Al referir: nuevo cliente obtiene descuento, cliente referidor obtiene crédito
- Dashboard de referidos para cada cliente
- Top referidores con recompensas especiales

#### 5.8.7 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/admin/cupones` | Listar cupones |
| POST | `/api/admin/cupones` | Crear cupón |
| PATCH | `/api/admin/cupones/{id}` | Editar cupón |
| POST | `/api/admin/cupones/{id}/desactivar` | Desactivar |
| POST | `/api/admin/cupones/{id}/duplicar` | Duplicar |
| GET | `/api/admin/cupones/{id}/estadisticas` | Stats de uso |
| GET | `/api/admin/paquetes` | Listar paquetes |
| POST | `/api/admin/paquetes` | Crear paquete |
| PATCH | `/api/admin/paquetes/{id}` | Editar paquete |
| GET | `/api/admin/paquetes/{id}/estadisticas` | Stats de paquete |
| GET | `/api/admin/promociones/dashboard` | Dashboard general |
| PATCH | `/api/admin/primera-consulta/config` | Configurar descuento |

---

### 5.9 Notificaciones

#### 5.9.1 Propósito del módulo

Mantener informados a los clientes y a Chachita en cada momento relevante del flujo de negocio, usando el canal correcto para cada situación. Las notificaciones bien diseñadas reducen no-shows, aumentan pagos a tiempo y generan confianza profesional.

#### 5.9.2 Canales disponibles (Fase 1)

**Email (Resend):**
- Notificaciones formales: confirmaciones, recibos, documentos
- Contenido largo permitido (HTML con branding)
- Tracking de apertura y clicks
- Tier gratuito: 3,000 emails/mes (suficiente para inicio)

**WhatsApp (Meta WhatsApp Cloud API o YCloud):**
- Recordatorios urgentes y comunicación directa
- Templates aprobados por Meta (obligatorio)
- Contenido breve con emojis permitidos
- Meta Cloud API: gratis las primeras 1,000 conversaciones/mes (service conversations), luego ~$0.03-0.08 USD por conversación según país
- YCloud: alternativa con dashboard más simple y pricing transparente (~$0.05 USD por mensaje)

**Push web (Fase 1 limitado):**
- Notificaciones del navegador (solo si el usuario las acepta)
- Service Worker en el frontend React
- Costo: $0 (Firebase Cloud Messaging)

#### 5.9.3 Matriz completa de eventos y canales

**Eventos para el CLIENTE:**

| # | Evento | Email | WhatsApp | Timing |
|---|--------|-------|----------|--------|
| 1 | Registro exitoso (bienvenida) | ✅ | ✅ | Inmediato |
| 2 | Verificación de email | ✅ | ❌ | Inmediato |
| 3 | Cita reservada (20% pagado) | ✅ | ✅ | Inmediato |
| 4 | Cita confirmada (100% pagado) | ✅ | ✅ | Inmediato |
| 5 | Recordatorio 48h antes de cita | ✅ | ❌ | Programado |
| 6 | Recordatorio pagar 80% (si falta) | ✅ | ✅ | Programado |
| 7 | Recordatorio 3h antes de cita | ❌ | ✅ | Programado |
| 8 | Sala lista (15 min antes) | ❌ | ✅ | Programado |
| 9 | Consulta finalizada | ✅ | ❌ | Inmediato |
| 10 | Transcripción disponible | ✅ | ✅ | Cuando job termina |
| 11 | Reagendamiento por Chachita | ✅ | ✅ | Inmediato |
| 12 | Cancelación confirmada | ✅ | ❌ | Inmediato |
| 13 | Reembolso procesado | ✅ | ❌ | Inmediato |
| 14 | Aviso 7 días antes de eliminar video | ✅ | ❌ | Programado |
| 15 | Reserva expirada (no pagó a tiempo) | ✅ | ✅ | Automático |
| 16 | Membresía próxima a vencer (7 días) | ✅ | ❌ | Programado |
| 17 | Membresía expirada | ✅ | ❌ | Automático |
| 18 | Cupón de cumpleaños (si activado) | ✅ | ✅ | Día del cumpleaños |
| 19 | Cupón de regreso (si activado) | ✅ | ❌ | Tras 3 meses inactivo |
| 20 | Recibo/comprobante de pago | ✅ | ❌ | Inmediato (adjunto PDF) |
| 21 | Exportación GDPR lista | ✅ | ❌ | Cuando job termina |
| 22 | Recuperación de contraseña | ✅ | ❌ | Inmediato |
| 23 | Comprobante rechazado | ✅ | ✅ | Cuando agente decide |
| 24 | Comprobante aprobado | ✅ | ✅ | Cuando agente decide |

**Eventos para CHACHITA:**

| # | Evento | Email | WhatsApp | Timing |
|---|--------|-------|----------|--------|
| 1 | Nueva reserva de cliente | ✅ | ✅ | Inmediato |
| 2 | Cliente pagó el 80% | ✅ | ❌ | Inmediato |
| 3 | Cliente NO pagó 80% (3h antes de cita) | ❌ | ✅ | Programado |
| 4 | Cliente canceló cita | ✅ | ✅ | Inmediato |
| 5 | Cliente reagendó | ✅ | ❌ | Inmediato |
| 6 | Alerta de no-show detectado | ❌ | ✅ | 15 min después de inicio |
| 7 | Comprobante requiere revisión manual | ✅ | ✅ | Cuando agente decide |
| 8 | Alerta de posible fraude | ✅ | ✅ | Inmediato |
| 9 | Resumen diario (citas del día) | ✅ | ❌ | Cada día a las 8:00 Chile |
| 10 | Resumen semanal de ingresos | ✅ | ❌ | Cada lunes a las 9:00 |
| 11 | Resumen mensual completo | ✅ | ❌ | Día 1 de cada mes |
| 12 | Error de sistema (alerta) | ✅ | ✅ | Inmediato |
| 13 | Membresía vendida | ✅ | ❌ | Inmediato |
| 14 | Nuevo cliente registrado | ✅ | ❌ | Inmediato |

#### 5.9.4 Templates editables

Todas las plantillas son editables por Chachita desde admin → "Notificaciones" → "Plantillas".

**Editor de plantilla:**

- **Vista dual**: editor a la izquierda, preview a la derecha (actualización en tiempo real)
- **Variables disponibles**: mostradas como chips arrastrables: `{nombre_cliente}`, `{fecha_cita}`, `{hora_cita}`, `{tipo_consulta}`, `{precio}`, `{codigo_referencia}`, `{link_pago}`, `{link_sala}`, `{nombre_especialista}`
- **Templates de email**: editor rich text (HTML) con header/footer de marca (logo, colores, tipografía TarotEstrellas) pre-aplicados, no editables para mantener consistencia visual
- **Templates de WhatsApp**: editor de texto plano con emojis permitidos. Los templates deben ser **aprobados por Meta** antes de usarse (proceso de 24-48h). El sistema envía la plantilla a Meta para aprobación cuando Chachita la guarda.

**Ejemplo de template de email (cita reservada):**

```
Asunto: ✨ Tu consulta de {tipo_consulta} está reservada

Hola {nombre_cliente},

Tu consulta de {tipo_consulta} con {nombre_especialista} ha sido reservada 
para el {fecha_cita} a las {hora_cita}.

Recuerda completar el pago del 80% restante antes de la sesión.

[Completar pago →]

Con cariño,
El equipo de TarotEstrellas ✨
```

**Ejemplo de template de WhatsApp (recordatorio 3h):**

```
✨ Hola {nombre_cliente}! Tu consulta de {tipo_consulta} es en 3 horas 
({hora_cita}). ¿Ya completaste el pago? 
👉 {link_pago}
¡Te esperamos! 🌙
```

**Versionado:**

Cada vez que Chachita edita una plantilla, se guarda la versión anterior. Puede ver historial de cambios y revertir a versiones anteriores si algo sale mal.

**Plantillas por idioma (preparación Fase 2):**

Cada plantilla tiene campo `idioma` (default `es`). En Fase 2 se agregan versiones en inglés. El sistema elige la plantilla según el idioma del perfil del destinatario.

#### 5.9.5 Preferencias del cliente

Desde perfil del cliente → "Notificaciones":

- **Email**: activar/desactivar (excepto transaccionales obligatorias como recibos y confirmaciones de pago)
- **WhatsApp**: activar/desactivar completamente
- **Marketing**: activar/desactivar (cupones, promociones, novedades)
- **Recordatorios**: activar/desactivar (recordatorios de cita y de pago)

**Regla inviolable:** las notificaciones transaccionales (confirmación de pago, recibo, confirmación de cancelación, verificación de email) **siempre se envían por email** independientemente de las preferencias. Son obligatorias legalmente.

#### 5.9.6 Implementación técnica

**Envío de emails (Resend):**

Laravel usa el driver de Resend via `resend/resend-laravel`. Cada email se envía como job en cola:

```php
// Disparo del evento
event(new CitaReservadaEvent($cita));

// Listener que maneja el email
class EnviarEmailCitaReservada
{
    public function handle(CitaReservadaEvent $event)
    {
        $plantilla = PlantillaNotificacion::findBySlug('cita_reservada_email');
        $cuerpo = $this->renderTemplate($plantilla, $event->cita);
        
        Mail::to($event->cita->cliente->email)
            ->queue(new NotificacionEmail($cuerpo, $plantilla->asunto));
        
        NotificacionEnviada::create([...]);
    }
}
```

**Envío de WhatsApp (Meta Cloud API o YCloud):**

```php
class EnviarWhatsAppCitaReservada
{
    public function handle(CitaReservadaEvent $event)
    {
        if (!$event->cita->cliente->preferencias->whatsapp_activo) return;
        
        $plantilla = PlantillaNotificacion::findBySlug('cita_reservada_whatsapp');
        $variables = $this->buildVariables($event->cita);
        
        // Opción A: Meta Cloud API directa
        WhatsAppService::sendTemplate(
            to: $event->cita->cliente->telefono,
            templateName: $plantilla->meta_template_name,
            languageCode: 'es',
            components: $variables
        );
        
        // Opción B: YCloud SDK (alternativa)
        // YCloud::whatsapp()->sendTemplate([...]);
        
        NotificacionEnviada::create([...]);
    }
}
```

**Programación de recordatorios:**

Al crear una cita reservada, se programan automáticamente los jobs de recordatorio:

```php
// En CitaReservadaListener
$cita = $event->cita;

// Recordatorio 48h antes
RecordatorioCitaJob::dispatch($cita, '48h')
    ->delay($cita->inicio_utc->subHours(48));

// Recordatorio 3h antes
RecordatorioCitaJob::dispatch($cita, '3h')
    ->delay($cita->inicio_utc->subHours(3));

// Recordatorio 30min antes (sala lista)
RecordatorioSalaListaJob::dispatch($cita)
    ->delay($cita->inicio_utc->subMinutes(30));
```

Si la cita se cancela o reagenda antes de que el job se ejecute, los jobs pendientes se cancelan (usando job tags de Laravel Horizon).

#### 5.9.7 Resúmenes automáticos para Chachita

**Resumen diario (8:00 Chile):**

Email con:
- Citas del día: hora, cliente, tipo, estado de pago
- Avisos: clientes que aún no pagaron el 80%, clientes nuevos
- Ingresos del día anterior
- Formato: limpio, escaneable en 30 segundos

**Resumen semanal (lunes 9:00 Chile):**

Email con:
- Consultas realizadas en la semana
- Ingresos totales de la semana (CLP consolidado)
- Clientes nuevos registrados
- Tasa de no-show
- Top tipo de consulta más agendado
- Cupones más usados

**Resumen mensual (día 1, 10:00 Chile):**

Email con:
- Resumen financiero del mes completo
- Comparativa con mes anterior
- Clientes activos vs nuevos vs inactivos
- Métricas de uso del agente IA
- Costos IA del mes
- PDF adjunto con datos exportables

#### 5.9.8 Monitoreo de entregas

Dashboard admin → "Notificaciones" → "Monitor":

- **Tasa de entrega**: % de emails entregados vs rebotados
- **Tasa de apertura**: % de emails abiertos (tracking pixel de Resend)
- **WhatsApp entregados vs leídos**: datos de Meta Cloud API o YCloud dashboard
- **Errores recientes**: emails rebotados, WhatsApp fallidos (con razón: número inválido, bloqueado, etc.)
- **Log completo**: tabla de todas las notificaciones enviadas con filtros por cliente, canal, evento, estado

#### 5.9.9 Endpoints principales

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/admin/plantillas` | Listar plantillas |
| GET | `/api/admin/plantillas/{id}` | Detalle de plantilla |
| PATCH | `/api/admin/plantillas/{id}` | Editar plantilla |
| POST | `/api/admin/plantillas/{id}/preview` | Preview con datos de prueba |
| GET | `/api/admin/plantillas/{id}/versiones` | Historial de versiones |
| POST | `/api/admin/plantillas/{id}/revertir/{version}` | Revertir a versión anterior |
| GET | `/api/admin/notificaciones/monitor` | Dashboard de entregas |
| GET | `/api/admin/notificaciones/log` | Log completo |
| GET | `/api/me/preferencias-notificacion` | Preferencias del cliente |
| PATCH | `/api/me/preferencias-notificacion` | Actualizar preferencias |
| POST | `/api/admin/notificaciones/test` | Enviar notificación de prueba |

---

### 5.10 Panel admin

#### 5.10.1 Propósito del módulo

El panel admin es la **central de operaciones de Chachita**. Desde aquí gestiona todo su negocio sin necesidad de acceder al código, contactar soporte técnico, ni usar herramientas externas. Construido sobre **Filament 3.x** (framework admin para Laravel, open source, profesional).

El panel debe ser:
- **Rápido**: carga en <2 segundos, sin lag en acciones
- **Intuitivo**: Chachita no es técnica, la interfaz debe ser obvia
- **Completo**: todo lo que necesita está aquí
- **Seguro**: 2FA obligatorio, log de acciones, sesiones cortas

#### 5.10.2 Estructura de navegación del admin

**Sidebar principal (menú izquierdo):**

```
🏠 Dashboard
📅 Calendario
👥 Clientes
🔮 Consultas (citas)
💳 Pagos
📦 Catálogo de servicios
🎁 Promociones
📬 Notificaciones
🤖 Agente IA
📊 Reportes
⚙️ Configuración
```

#### 5.10.3 Dashboard (página de inicio del admin)

Al entrar, Chachita ve un dashboard visualmente claro con:

**Fila superior — KPIs del día:**

4 tarjetas grandes:
- **Citas hoy**: cantidad + lista rápida con hora y cliente
- **Ingresos hoy**: monto CLP consolidado
- **Clientes nuevos hoy**: cantidad
- **Alertas pendientes**: comprobantes por revisar, pagos pendientes, etc.

**Fila media — Gráficos rápidos:**

2 gráficos lado a lado:
- **Ingresos últimos 30 días**: gráfico de barras diario con trendline
- **Consultas por tipo (mes actual)**: gráfico de torta/donut

**Fila inferior — Acciones rápidas:**

Tarjetas clickables:
- "Próxima cita en 2h: María — Tarot" → abre ficha del cliente
- "3 comprobantes pendientes de revisión" → abre lista
- "Juan no ha pagado 80% (cita mañana)" → abre detalle de la cita
- "Membresía de Ana vence en 5 días" → abre perfil

**Resumen financiero del mes (sidebar derecho o tab):**

- Ingresos brutos del mes
- Comisiones Stripe del mes
- Ingresos netos estimados
- Comparativa con mes anterior (flecha verde/roja con %)

#### 5.10.4 Sección Calendario

Vista completa del calendario de Chachita con:

**3 vistas:**
- **Día**: slots de 30 min, citas en color según estado
- **Semana**: vista compacta 7 días
- **Mes**: vista birds-eye con puntos de color por cita

**Código de colores de citas:**
- 🟡 Amarillo: pendiente de abono
- 🔵 Azul: reservada (20% pagado, falta 80%)
- 🟢 Verde: confirmada (100% pagada)
- 🟣 Púrpura: en curso (videollamada activa)
- ⚪ Gris: completada
- 🔴 Rojo: cancelada / no-show
- ⬛ Negro: bloqueado (descanso, feriado, vacaciones)

**Acciones desde el calendario:**
- Click en slot vacío → bloquear o abrir horario extra
- Click en cita → panel lateral con detalle completo + acciones (mover, cancelar, entrar a sala, ver ficha del cliente)
- Drag-and-drop para mover citas entre slots (pide confirmación, notifica al cliente)
- Botón "Hoy" para volver a la fecha actual rápidamente
- Botón "Bloquear día completo" para feriados/vacaciones

#### 5.10.5 Sección Clientes

Ya documentada extensamente en Módulo 5.3 (vista admin). Acceso a:
- Lista completa con búsqueda y filtros avanzados
- Ficha de cada cliente con 7 tabs
- Exportación masiva a Excel
- Estadísticas de retención y geografía

#### 5.10.6 Sección Consultas (citas)

**Lista de citas con filtros potentes:**

- Por estado: todos los 10 estados como chips clickables
- Por tipo de consulta: multiselect
- Por fecha: rango con date picker
- Por cliente: búsqueda por nombre
- Por canal de pago: Stripe / transferencia / membresía
- Búsqueda libre en notas del cliente

**Tabla de citas:**

| Fecha | Hora | Cliente | Tipo | Estado | Pago | Acciones |
|-------|------|---------|------|--------|------|----------|
| 22/04 | 15:00 | María | Tarot | ✅ Confirmada | 100% ✅ | Ver / Mover / Cancelar |
| 23/04 | 10:00 | Juan | Carta astral | 🔵 Reservada | 20% | Ver / Recordar 80% |

**Vista de detalle de cita (desde admin):**

Panel completo con toda la información:
- Datos del cliente (link a ficha)
- Estado actual con timeline visual de transiciones
- Pagos asociados (abono, saldo, extras) con estado de cada uno
- Si hubo transferencia: comprobante subido + resultado del agente IA
- Si ya se realizó: link a grabación + transcripción + resumen
- Notas de Chachita para esta cita
- Historial de cambios de estado
- Acciones: mover, cancelar, reembolsar, marcar como no-show, generar link de pago extra (tiempo adicional)

#### 5.10.7 Sección Pagos

**Dashboard de pagos:**

- Ingresos del mes por canal (Stripe vs transferencia)
- Ingresos por moneda original
- Comisiones Stripe pagadas en el mes
- Pagos pendientes (80% no pagados)
- Reembolsos del mes

**Lista de pagos:**

Tabla filtrable por:
- Estado: completado / pendiente / fallido / reembolsado
- Canal: Stripe / transferencia / membresía / crédito
- Tipo: abono_20 / saldo_80 / pago_total / extra
- Fecha
- Cliente
- Moneda

**Comprobantes de transferencia:**

Sección dedicada a los comprobantes subidos por clientes chilenos:
- Lista filtrada por estado: pendiente de revisión / aprobados / rechazados
- Vista rápida del comprobante (imagen/PDF)
- Datos extraídos por el agente IA en formato legible
- Resultado de las 5 reglas de validación (verde/rojo por regla)
- Botones: "Aprobar" / "Rechazar" (con campo motivo)
- Estadísticas: tasa de aprobación automática vs manual

#### 5.10.8 Sección Catálogo de servicios

CRUD completo de tipos de consulta (documentado en Módulo 5.2). Características admin:

- Drag-and-drop para reordenar servicios
- Preview en vivo de cómo se ve la página de detalle
- Editor de precios multi-moneda con historial
- Toggle activo/inactivo con confirmación
- Estadísticas por tipo: veces agendado, ingresos, rating promedio

#### 5.10.9 Sección Reportes

**Reportes disponibles:**

1. **Reporte de ingresos**
   - Filtros: período, tipo de consulta, país del cliente, moneda, canal de pago
   - Gráficos: barras por día/semana/mes, torta por tipo de consulta
   - Tabla detallada con cada pago
   - Exportar a Excel + PDF

2. **Reporte de consultas**
   - Filtros similares
   - Métricas: total, por tipo, por estado, duración promedio
   - Tasa de cancelación y no-show
   - Horas trabajadas en el período

3. **Reporte de clientes**
   - Clientes nuevos por período
   - Clientes activos vs inactivos
   - Top 10 por ingresos generados
   - Top 10 por consultas realizadas
   - Distribución geográfica (gráfico de mapa)
   - Distribución por signo solar

4. **Reporte de promociones**
   - Uso de cupones por período
   - ROI estimado: ingresos generados con cupón vs sin cupón
   - Membresías vendidas y tasa de utilización
   - Efectividad del descuento de primera consulta

5. **Reporte fiscal**
   - Ingresos brutos mensuales en CLP
   - Desglose por tipo de servicio
   - Comisiones pagadas a Stripe
   - Ingresos netos
   - Formato compatible con declaración al SII
   - Exportar a PDF con formato de informe contable

6. **Reporte de costos IA**
   - Gasto mensual en Whisper, Claude, embeddings
   - Desglose por tipo de uso (transcripciones, resúmenes, agente)
   - Gasto por cliente (para análisis de rentabilidad)
   - Proyección del mes basada en tendencia actual

#### 5.10.10 Sección Configuración

**Subsecciones:**

**Mi perfil (Chachita):**
- Nombre, email, teléfono, avatar
- Cambio de contraseña
- Gestión de 2FA
- Datos bancarios para recibir transferencias

**Horario y disponibilidad:**
- Horario base semanal con editor visual
- Gestión de bloqueos y excepciones
- Feriados del año

**Parámetros del sistema:**
- Tabla completa de `configuracion_sistema` editables
- Organizados por categoría: pagos, agendamiento, notificaciones, privacidad, promociones
- Cada parámetro con descripción, valor actual, valor por defecto, y botón reset

**Integraciones:**
- Estado de conexión con servicios externos: Stripe (conectado/desconectado), Daily.co, OpenAI, Anthropic, Resend, Meta WhatsApp / YCloud
- Para cada uno: estado, último uso, errores recientes
- Links a dashboards de cada servicio externo

**Datos legales:**
- Editar Términos y Condiciones (editor rich text)
- Editar Política de Privacidad
- Editar Disclaimer
- Versionado automático con fecha de cada cambio
- Avisar a usuarios existentes cuando cambian los términos (toggle)

**Copias de seguridad:**
- Último backup exitoso (fecha, tamaño)
- Botón "Ejecutar backup ahora"
- Historial de backups con opción de descarga

#### 5.10.11 Roles y permisos en el admin

**Super Admin (tú/desarrollador):**
- Acceso total a todo
- Puede crear/editar usuarios admin
- Accede a logs técnicos, configuración de integraciones, backups
- Puede impersonar usuarios (ver la app como si fuera un cliente específico)

**Admin Especialista (Chachita):**
- Accede a todo excepto: configuración técnica de integraciones, logs del sistema, impersonación
- Puede editar catálogo, precios, promociones, disponibilidad, plantillas de notificación
- Puede ver y gestionar todos los clientes y citas
- Puede aprobar/rechazar comprobantes manualmente
- Puede ejecutar reembolsos

**Futuro (Fase 2 multi-especialista):**
- Rol "Especialista" (sin "admin"): solo ve sus propios clientes y citas, no puede editar catálogo ni precios globales
- Rol "Recepcionista": puede agendar en nombre de clientes, pero no accede a pagos ni transcripciones

#### 5.10.12 Auditoría de acceso

Toda acción en el admin queda registrada en `audit_log`:
- Login/logout del admin
- Visualización de datos de cliente (quién vio qué ficha, cuándo)
- Cambios de configuración
- Acciones sobre citas (cancelar, mover, reembolsar)
- Aprobación/rechazo manual de comprobantes
- Edición de plantillas de notificación
- Acceso a reportes financieros
- Exportaciones de datos

Esto cumple con GDPR y permite auditoría completa si hay disputas.

#### 5.10.13 Endpoints principales del admin

Los endpoints admin ya fueron listados en los módulos respectivos. Resumen de los adicionales específicos del panel:

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/admin/dashboard` | KPIs y datos del dashboard |
| GET | `/api/admin/dashboard/grafico-ingresos` | Datos del gráfico de ingresos |
| GET | `/api/admin/dashboard/grafico-tipos` | Datos del gráfico por tipo |
| GET | `/api/admin/reportes/ingresos` | Reporte de ingresos |
| GET | `/api/admin/reportes/consultas` | Reporte de consultas |
| GET | `/api/admin/reportes/clientes` | Reporte de clientes |
| GET | `/api/admin/reportes/fiscal` | Reporte fiscal |
| GET | `/api/admin/reportes/costos-ia` | Reporte de costos IA |
| POST | `/api/admin/reportes/{tipo}/exportar` | Exportar reporte (Excel/PDF) |
| GET | `/api/admin/configuracion` | Todos los parámetros |
| PATCH | `/api/admin/configuracion/{clave}` | Actualizar parámetro |
| POST | `/api/admin/configuracion/reset/{clave}` | Reset a valor por defecto |
| GET | `/api/admin/integraciones` | Estado de servicios externos |
| POST | `/api/admin/backups/ejecutar` | Ejecutar backup manual |
| GET | `/api/admin/backups` | Historial de backups |
| GET | `/api/admin/audit-log` | Log de auditoría |

---

## 6. Identidad Visual y UX

### 6.1 Filosofía de marca

**TarotEstrellas** es una plataforma que conecta al cliente con lo más profundo de sí mismo a través de la guía de una especialista. La identidad visual debe transmitir cinco sensaciones simultáneamente:

1. **Misterio**: hay algo más allá de lo visible, un conocimiento ancestral esperando ser revelado
2. **Elegancia**: esto no es charlatanería — es un servicio profesional, sofisticado y cuidado
3. **Calidez**: quien entre se siente acogido, no intimidado; es una experiencia femenina, envolvente
4. **Confianza**: el diseño comunica que la plataforma es segura, seria y confiable con tus datos y tu dinero
5. **Magia**: hay un encantamiento sutil en cada interacción — animaciones, detalles dorados, transiciones suaves

La estética es **místico-ornamentada**: no minimalista ni corporativa, pero tampoco recargada ni circense. El equilibrio es similar al de una joyería artesanal: pocas piezas, pero cada una exquisita.

### 6.2 Paleta de colores

**Colores primarios:**

| Nombre | Hex | Uso principal |
|--------|-----|---------------|
| **Noche Profunda** | `#0D0B1E` | Fondo principal de la app, cielo nocturno, sensación de profundidad |
| **Púrpura Cósmico** | `#1A0F2E` | Fondo secundario, paneles, modales, cards |
| **Oro Antiguo** | `#C9A84C` | Acentos principales, CTAs, bordes destacados, iconos activos |
| **Crema Lunar** | `#F5E6D3` | Texto principal sobre fondos oscuros, transmite calidez (no blanco puro) |

**Colores secundarios:**

| Nombre | Hex | Uso principal |
|--------|-----|---------------|
| **Violeta Místico** | `#6B3FA0` | Hover states, selecciones activas, badges, links |
| **Rosa Crepúsculo** | `#B8566E` | Alertas suaves, acentos femeninos, corazones, amor |
| **Cobre Rosado** | `#B8734F` | Detalles ornamentales, bordes decorativos |
| **Azul Estelar** | `#2E4A7A` | Información neutral, tooltips, textos secundarios |

**Colores funcionales:**

| Nombre | Hex | Uso |
|--------|-----|-----|
| **Verde Éxito** | `#4A8B6B` | Confirmaciones, pagos exitosos, estado completado |
| **Rojo Alerta** | `#B84A4A` | Errores, cancelaciones, alertas urgentes |
| **Ámbar Aviso** | `#C9944C` | Warnings, pendientes, estado en revisión |
| **Gris Sutil** | `#6B6B8A` | Texto terciario, placeholders, estados deshabilitados |

**Gradientes principales:**

```css
/* Gradiente de fondo principal (cielo nocturno) */
--gradient-sky: linear-gradient(180deg, #0D0B1E 0%, #1A0F2E 50%, #0D0B1E 100%);

/* Gradiente dorado (CTAs, highlights) */
--gradient-gold: linear-gradient(135deg, #C9A84C 0%, #E8D48B 50%, #C9A84C 100%);

/* Gradiente místico (cards especiales, hovers) */
--gradient-mystic: linear-gradient(135deg, #1A0F2E 0%, #2E1A4A 50%, #1A0F2E 100%);

/* Shimmer dorado (animación de brillo sutil) */
--gradient-shimmer: linear-gradient(90deg, transparent 0%, rgba(201,168,76,0.15) 50%, transparent 100%);
```

**Reglas de uso de color:**

- Fondos siempre oscuros (nunca fondo blanco excepto en modales de texto largo para legibilidad)
- Texto principal siempre en Crema Lunar `#F5E6D3` (nunca blanco puro `#FFFFFF` que deslumbra en fondos oscuros)
- Oro Antiguo solo para elementos importantes (no abusar, máximo 3-4 usos por pantalla)
- Contrastes validados con WCAG 2.1 AA como mínimo (ratio ≥ 4.5:1 para texto normal)

---

#### 6.2.B PROPUESTA B — "Cielo y Rosas" (preferencias de Chachita)

Paleta basada en los colores que Chachita expresó: azul celeste, blanco, beis, rosa y morado. Estética más luminosa, femenina y celestial. Transmite: serenidad, suavidad, intuición femenina, cielo diurno con toques etéreos.

**Colores primarios (Propuesta B):**

| Nombre | Hex | Uso principal |
|--------|-----|---------------|
| **Blanco Beis** | `#FAF6F0` | Fondo principal — cálido, no blanco puro, acogedor |
| **Azul Celeste** | `#A8C8E8` | Acento principal, headers, iconos activos, bordes |
| **Rosa Pétalo** | `#D4A0B0` | CTAs principales, badges, elementos destacados |
| **Morado Suave** | `#8B6BAE` | Acento secundario, hovers, selecciones, links |

**Colores secundarios (Propuesta B):**

| Nombre | Hex | Uso principal |
|--------|-----|---------------|
| **Lavanda Claro** | `#E8DCF0` | Fondos de cards, paneles secundarios, badges suaves |
| **Azul Profundo** | `#3A5A80` | Texto principal sobre fondos claros, máximo contraste |
| **Rosa Intenso** | `#C07088` | Hover de CTAs, estados activos, acentos fuertes |
| **Beis Cálido** | `#EDE4D8` | Fondos alternativos, separadores, secciones alternas |
| **Gris Lavanda** | `#9896A8` | Texto secundario, placeholders, estados deshabilitados |

**Colores funcionales (Propuesta B):**

| Nombre | Hex | Uso |
|--------|-----|-----|
| **Verde Salvia** | `#7BAE8E` | Éxito, confirmaciones, pagos completados |
| **Rojo Coral** | `#D46A6A` | Errores, cancelaciones, alertas |
| **Ámbar Dorado** | `#D4A84C` | Warnings, pendientes, elementos premium |
| **Gris Neutro** | `#B0AEC0` | Texto terciario, separadores, deshabilitados |

**Gradientes (Propuesta B):**

```css
/* Gradiente de fondo principal (cielo al amanecer) */
--gradient-sky-b: linear-gradient(180deg, #FAF6F0 0%, #E8DCF0 40%, #A8C8E8 100%);

/* Gradiente rosa a morado (CTAs, highlights) */
--gradient-rosa: linear-gradient(135deg, #D4A0B0 0%, #8B6BAE 100%);

/* Gradiente celeste suave (cards especiales) */
--gradient-celeste: linear-gradient(135deg, #E8F0FA 0%, #D8E8F8 50%, #E8DCF0 100%);

/* Shimmer rosado (animación de brillo sutil) */
--gradient-shimmer-b: linear-gradient(90deg, transparent 0%, rgba(212,160,176,0.15) 50%, transparent 100%);
```

**Reglas de uso de color (Propuesta B):**

- Fondos predominantemente claros (Blanco Beis como base, Lavanda para cards)
- Texto principal en Azul Profundo `#3A5A80` (nunca negro puro, que es duro contra fondos suaves)
- Rosa Pétalo para CTAs principales (botones "Agendar", "Pagar")
- Morado Suave para links y elementos interactivos
- Azul Celeste para bordes, separadores y detalles decorativos
- Contrastes validados WCAG 2.1 AA

**Comparativa de ambas propuestas:**

| Aspecto | Propuesta A "Noche Dorada" | Propuesta B "Cielo y Rosas" |
|---------|---------------------------|----------------------------|
| Emoción dominante | Misterio, profundidad, lujo | Serenidad, suavidad, feminidad |
| Fondo | Oscuro (noche estrellada) | Claro (cielo al amanecer) |
| Acento principal | Oro dorado | Rosa + morado |
| Texto sobre fondo | Crema claro sobre oscuro | Azul profundo sobre claro |
| Percepción | Exclusiva, esotérica, profunda | Cercana, celestial, acogedora |
| Referentes visuales | Joyería de lujo, cosmos, rituales | Spa, nubes, jardín etéreo |
| Animaciones 3D | Partículas doradas, estrellas brillando | Partículas rosadas, nubes suaves, estrellas pastel |
| Tipo de cliente atraído | Busca lo profundo, lo serio, lo misterioso | Busca lo cálido, lo femenino, lo esperanzador |
| Legibilidad en móvil al sol | Buena (fondos oscuros resisten el brillo) | Excelente (fondos claros son más legibles al aire libre) |
| Diferenciación competitiva | Alta (90% de sitios de tarot usan oscuros pero pocos con esta elegancia) | Media (hay sitios claros en el rubro, pero esta paleta es más refinada que la mayoría) |
| Fatiga visual nocturna | Baja (oscuro es cómodo de noche) | Media (claro puede cansar de noche, mitigable con modo oscuro futuro) |

**✅ DECISIÓN FINAL DE PALETA: Dual Mode (Modo Oscuro + Modo Claro)**

Ambas propuestas se implementan como **dos modos del mismo sistema**:

- **Modo Oscuro** (Propuesta A "Noche Dorada"): activado por defecto. Fondo nocturno con acentos dorados. Ideal para consultas nocturnas, navegación prolongada, y experiencia mística inmersiva.
- **Modo Claro** (Propuesta B "Cielo y Rosas"): activable con un botón toggle (icono sol/luna). Fondo beis con acentos rosa, celeste y morado. Ideal para navegación diurna, lectura al aire libre, y preferencia personal.

**Implementación técnica del dual mode:**

La paleta se gestiona con **CSS custom properties** en `:root`. Al cambiar de modo, se actualizan todas las variables de una sola vez:

```css
/* Modo Oscuro (default) */
:root[data-theme="dark"] {
  --bg-primary: #0D0B1E;
  --bg-secondary: #1A0F2E;
  --bg-card: linear-gradient(135deg, #1A0F2E, #2E1A4A, #1A0F2E);
  --text-primary: #F5E6D3;
  --text-secondary: rgba(245,230,211,0.7);
  --accent-primary: #C9A84C;
  --accent-secondary: #6B3FA0;
  --accent-cta: linear-gradient(135deg, #C9A84C, #E8D48B, #C9A84C);
  --accent-cta-text: #0D0B1E;
  --border-subtle: rgba(201,168,76,0.15);
  --border-active: rgba(201,168,76,0.4);
  --glow-color: rgba(201,168,76,0.2);
  --success: #4A8B6B;
  --error: #B84A4A;
  --warning: #C9944C;
}

/* Modo Claro */
:root[data-theme="light"] {
  --bg-primary: #FAF6F0;
  --bg-secondary: #E8DCF0;
  --bg-card: linear-gradient(135deg, #F8F4F0, #E8DCF0);
  --text-primary: #3A5A80;
  --text-secondary: rgba(58,90,128,0.7);
  --accent-primary: #D4A0B0;
  --accent-secondary: #8B6BAE;
  --accent-cta: linear-gradient(135deg, #D4A0B0, #C07088);
  --accent-cta-text: #FFFFFF;
  --border-subtle: rgba(168,200,232,0.3);
  --border-active: rgba(139,107,174,0.4);
  --glow-color: rgba(212,160,176,0.2);
  --success: #7BAE8E;
  --error: #D46A6A;
  --warning: #D4A84C;
}
```

**Comportamiento del toggle:**

- Ubicación: en el header principal, al lado del avatar del usuario (icono 🌙/☀️)
- Preferencia se guarda en localStorage del navegador (persiste entre sesiones)
- Si el usuario no elige, se respeta `prefers-color-scheme` del sistema operativo
- Transición suave entre modos (300ms ease-in-out en background y color)
- Las animaciones 3D adaptan su paleta de partículas al modo activo (doradas en oscuro, rosadas/celestes en claro)

**Reglas específicas por modo:**

En modo oscuro:
- Las animaciones 3D son más visibles y protagonistas (partículas brillan más)
- Los bordes usan glow dorado sutil
- Cards tienen borde casi invisible que se ilumina al hover

En modo claro:
- Las animaciones 3D son más sutiles (opacidad reducida al 60%)
- Los bordes usan color sólido suave en vez de glow
- Cards tienen sombra suave (box-shadow) en vez de borde brillante
- Los iconos esotéricos custom usan trazo en Azul Profundo en vez de dorado

### 6.3 Tipografía

**Tipografía principal (títulos y headings):**

**Cormorant Garamond** — serif elegante con raíces clásicas, evoca manuscritos antiguos y tradición. Excelente en tamaños grandes.

```css
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap');

--font-heading: 'Cormorant Garamond', Georgia, serif;
```

Uso: títulos de página, nombres de consultas, textos hero, frases destacadas.

**Tipografía secundaria (cuerpo de texto):**

**Inter** — sans-serif humanista, extremadamente legible en pantalla, con variantes ópticas que se ajustan al tamaño. Contrasta elegantemente con la serif de títulos.

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

--font-body: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
```

Uso: texto de cuerpo, botones, formularios, labels, navegación, tablas.

**Tipografía decorativa (opcional, acentos):**

**Italiana** — serif display con toque art nouveau, usada con moderación para detalles ornamentales específicos.

```css
@import url('https://fonts.googleapis.com/css2?family=Italiana&display=swap');

--font-display: 'Italiana', serif;
```

Uso: números grandes (precios), signo zodiacal del cliente, nombre de la marca en el header, frases inspiracionales.

**Escala tipográfica:**

```css
--text-xs: 0.75rem;    /* 12px — labels secundarios, badges */
--text-sm: 0.875rem;   /* 14px — texto secundario, helpers */
--text-base: 1rem;     /* 16px — cuerpo principal */
--text-lg: 1.125rem;   /* 18px — subtítulos, cards */
--text-xl: 1.25rem;    /* 20px — títulos de sección */
--text-2xl: 1.5rem;    /* 24px — títulos de página */
--text-3xl: 1.875rem;  /* 30px — hero subtítulos */
--text-4xl: 2.25rem;   /* 36px — hero títulos */
--text-5xl: 3rem;      /* 48px — landing hero principal */
```

### 6.4 Iconografía y elementos gráficos

**Set de iconos:**

**Lucide React** como base (open source, consistente, ~1,400 iconos). Estilo: stroke de 1.5px, esquinas redondeadas, tamaño estándar 24px.

Para iconos específicos del rubro esotérico que Lucide no tiene, se crean SVGs custom:
- Carta de tarot (vista frontal y posterior)
- Baraja española
- Constelación
- Signo zodiacal (los 12)
- Luna (fases)
- Sol con rayos
- Ojo místico (tercer ojo)
- Cristal
- Vela encendida
- Péndulo
- Runas (selección de 5-6 comunes)

Estilo de los iconos custom: línea delgada dorada (#C9A84C) sobre fondo oscuro, coherente con Lucide pero con más detalle ornamental.

**Separadores y ornamentos:**

En vez de líneas horizontales estándar, se usan elementos decorativos:
- Línea fina dorada con estrella central: `——✦——`
- Fases lunares como separador: `🌒 🌓 🌔 🌕 🌖 🌗 🌘`
- Patrón de constelación como divider entre secciones grandes

**Ilustraciones:**

Para secciones que necesiten ilustración (landing, onboarding, estados vacíos):
- Estilo: line art dorado sobre fondo oscuro, trazos finos y elegantes
- Temática: manos sosteniendo cartas, ojos con estrellas, lunas ornamentadas, constelaciones conectadas
- Se crean como SVGs para mantener nitidez en todas las resoluciones
- Animaciones sutiles: brillo pulsante en las estrellas de las ilustraciones

### 6.5 Componentes UI principales

#### 6.5.1 Botones

**Botón primario (CTA):**
```css
.btn-primary {
  background: var(--gradient-gold);
  color: #0D0B1E;
  font-family: var(--font-body);
  font-weight: 600;
  padding: 12px 32px;
  border-radius: 8px;
  border: none;
  box-shadow: 0 0 20px rgba(201, 168, 76, 0.2);
  transition: all 0.3s ease;
}
.btn-primary:hover {
  box-shadow: 0 0 30px rgba(201, 168, 76, 0.4);
  transform: translateY(-1px);
}
```

**Botón secundario:**
```css
.btn-secondary {
  background: transparent;
  color: var(--color-gold);
  border: 1px solid rgba(201, 168, 76, 0.4);
  border-radius: 8px;
  padding: 12px 32px;
}
.btn-secondary:hover {
  border-color: var(--color-gold);
  background: rgba(201, 168, 76, 0.08);
}
```

**Botón de texto (terciario):**
```css
.btn-text {
  background: none;
  border: none;
  color: var(--color-violet);
  text-decoration: underline;
  text-underline-offset: 3px;
}
```

#### 6.5.2 Cards

**Card de consulta (catálogo):**
```css
.card-consulta {
  background: var(--gradient-mystic);
  border: 1px solid rgba(201, 168, 76, 0.15);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.4s ease;
}
.card-consulta:hover {
  border-color: rgba(201, 168, 76, 0.4);
  box-shadow: 0 8px 32px rgba(201, 168, 76, 0.1);
  transform: translateY(-4px);
}
```

Estructura interna: imagen arriba (16:9 con overlay gradiente), contenido abajo (nombre en Cormorant, duración + precio en Inter, botón CTA).

**Card de cita (mis consultas):**

Más compacta, horizontal en desktop y vertical en móvil. Chip de estado con color según estado. Fecha prominente al lado izquierdo.

#### 6.5.3 Formularios

- Inputs con fondo semi-transparente (`rgba(255,255,255,0.05)`) y borde sutil
- Labels encima del input en Crema Lunar
- Focus state: borde dorado con glow sutil
- Error state: borde rojo + texto de error debajo en Rosa Crepúsculo
- Select y dropdowns con fondo oscuro consistente
- Checkboxes custom con borde dorado y check dorado
- Toggle switches con track púrpura y thumb dorado

#### 6.5.4 Modales y overlays

- Fondo overlay: negro al 70% con backdrop-blur de 8px
- Modal: fondo Púrpura Cósmico con borde dorado sutil, border-radius 20px
- Animación de entrada: scale de 0.95 a 1 + opacity 0 a 1 (300ms ease-out)
- Close button: X en esquina superior derecha, dorado, con hover glow

#### 6.5.5 Navegación

**Header (web):**
- Fondo: glassmorphism (Noche Profunda al 80% + backdrop-blur 12px)
- Logo TarotEstrellas a la izquierda (en Italiana dorada)
- Links de navegación centrados: Servicios / Sobre Chachita / Blog / Mis Consultas
- Avatar del usuario + dropdown a la derecha
- Sticky al hacer scroll, con sombra sutil que aparece al scrollear

**Bottom navigation (móvil):**
- Barra inferior fija con 5 iconos: Inicio / Servicios / Agendar (destacado) / Mis Consultas / Perfil
- Icono de "Agendar" más grande y con glow dorado (acción principal)
- Animación de bounce sutil al hacer tap

**Sidebar admin (Filament):**
- Se hereda el estilo de Filament pero con colores oscuros personalizados
- Iconos dorados para el estado activo
- Logo TarotEstrellas en la parte superior

### 6.6 Animaciones 3D

#### 6.6.1 Tecnología

**Stack de animaciones:**
- **Three.js + React Three Fiber**: escenas 3D embebidas en componentes React
- **@react-three/drei**: helpers (OrbitControls, Environment, Float, Stars, Sparkles)
- **@react-three/postprocessing**: efectos de post-procesado (bloom, glow)
- **Framer Motion**: animaciones 2D y transiciones de página
- **GSAP** (opcional): para animaciones complejas específicas que Framer no cubra

**Performance:**
- Target: 60fps en dispositivos de gama media (Galaxy A54, iPhone SE 3ra gen)
- Fallback: si el dispositivo no soporta WebGL o tiene GPU débil (detectado con `WEBGL_debug_renderer_info`), mostrar versión 2D estática con CSS
- Lazy loading: componentes 3D cargan solo cuando son visibles (Intersection Observer)
- Suspense boundary: mientras carga la escena 3D, muestra placeholder con animación CSS (loading con brillo dorado)

#### 6.6.2 Escenas 3D por sección

**Landing / Home — "Cielo Estrellado"**

Al abrir `tarotestrellas.com`, el fondo es un cielo nocturno 3D con:
- Estrellas titilando suavemente (partículas con `@react-three/drei Stars`)
- 1-2 constelaciones conectadas con líneas doradas tenues
- Lenta rotación casi imperceptible del campo estelar (0.0005 rad/frame)
- En móvil: versión reducida con menos partículas (200 vs 1000 en desktop)
- No interactivo (no consume gestos del usuario, solo decorativo)

**Catálogo — "Carta Flotante"**

Al entrar a la sección de servicios:
- Una carta de tarot 3D flota suavemente en el centro superior, girando lentamente sobre su eje Y
- La carta usa un modelo 3D simple (box geometry con textura del dorso dorado ornamentado)
- Al hacer hover sobre un tipo de consulta, la carta reacciona: gira ligeramente hacia esa dirección
- En móvil: la carta es más pequeña y está arriba del listado

**Detalle de consulta — "Escena temática"**

Cada tipo de consulta tiene su propia escena 3D sutil de fondo:

| Tipo | Escena 3D |
|------|-----------|
| Tarot | 3 cartas en abanico flotando, la central gira lentamente |
| Cartas españolas | Baraja española en arco, con una carta que se voltea periódicamente |
| Carta astral | Rueda zodiacal que rota lentamente, con puntos de luz en las posiciones planetarias |
| Astrología | Sistema solar simplificado con órbitas visibles y planetas como esferas brillantes |
| Numerología | Números dorados flotando y reorganizándose en patrones |
| Runas | Piedras rúnicas flotando con grabados luminosos |
| Péndulo | Péndulo oscilando suavemente en movimiento armónico |
| Limpieza energética | Partículas de luz ascendentes tipo "purificación" |
| Lectura de café | Taza con vapor animado y formas que se dibujan en el vapor |
| Quiromancia | Mano dorada wireframe con líneas iluminándose |
| Sinastría | Dos constelaciones conectándose con hilos de luz |
| Consulta rápida | Estrella fugaz que cruza la pantalla periódicamente |

Cada escena se implementa como componente React independiente: `<TarotScene />`, `<AstralScene />`, etc. Lazy loaded.

**Pago exitoso — "Constelación que se ilumina"**

Al confirmarse el pago:
- Pantalla muestra una constelación que empieza oscura
- Las estrellas se encienden una a una en secuencia (como dominó)
- Al encenderse la última, toda la constelación brilla con un pulse dorado
- Partículas de polvo cósmico caen suavemente
- Duración: 3 segundos total
- Después aparece el mensaje de confirmación con fade-in

**Entrada a sala de video — "Portal"**

Al entrar a la sala de videollamada:
- Breve animación (1.5s) de un "portal" circular que se abre (anillo dorado que se expande)
- Transición a la interfaz de video
- El portal se "cierra" cuando termina la sesión (anillo que se contrae)

**Estados vacíos — "Estrellas esperando"**

Cuando el cliente no tiene consultas aún:
- Una constelación incompleta con estrellas titilando suavemente
- Texto debajo: *"Las estrellas esperan por ti. Agenda tu primera consulta ✨"*
- CTA dorado: "Explorar servicios"

#### 6.6.3 Animaciones 2D (Framer Motion)

**Transiciones de página:**
```javascript
const pageTransition = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: -20 },
  transition: { duration: 0.4, ease: "easeInOut" }
};
```

**Aparición de cards en listados:**
```javascript
const cardVariants = {
  hidden: { opacity: 0, y: 30 },
  visible: (i) => ({
    opacity: 1, y: 0,
    transition: { delay: i * 0.1, duration: 0.5 }
  })
};
```
Efecto: las cards aparecen escalonadas de arriba a abajo al cargar la página.

**Hover en cards:**
- Elevación sutil (translateY -4px)
- Borde dorado más visible
- Sombra dorada expandida
- Duración: 300ms ease

**Micro-interacciones:**
- Checkboxes: el check aparece con un dibujo animado (como si se escribiera)
- Toggles: el thumb se mueve con spring physics (pequeño rebote)
- Botones CTA: glow dorado que pulsa suavemente cuando está en viewport
- Scroll progress: barra dorada fina en el top de la página que avanza con el scroll
- Badge de notificación: aparece con scale bounce (0 → 1.2 → 1)

### 6.7 Wireframes y flujos de pantalla

#### 6.7.1 Mapa de pantallas (sitemap visual)

```
tarotestrellas.com/
├── / (Landing)
│   ├── Hero con animación 3D de cielo estrellado
│   ├── Sección "Servicios destacados" (3 cards)
│   ├── Sección "Sobre Chachita" (foto + bio)
│   ├── Sección "Testimonios" (carousel)
│   ├── Sección "Cómo funciona" (3 pasos ilustrados)
│   ├── Sección "Preguntas frecuentes" (acordeón)
│   └── Footer (links legales + redes sociales + logo)
│
├── /servicios (Catálogo)
│   ├── Filtros + búsqueda
│   └── Grid de consultas disponibles
│
├── /servicios/{slug} (Detalle consulta)
│   ├── Hero con escena 3D temática
│   ├── Info completa
│   └── CTA "Agendar"
│
├── /auth/login
├── /auth/register
├── /auth/forgot-password
├── /auth/reset-password
│
├── /app (requiere login)
│   ├── /app/dashboard (home del cliente logueado)
│   ├── /app/agendar (flujo de agendamiento)
│   │   ├── Paso 1: Selección de consulta
│   │   ├── Paso 2: Calendario + hora
│   │   ├── Paso 3: Info adicional
│   │   ├── Paso 4: Resumen + pago
│   │   └── Paso 5: Confirmación
│   ├── /app/mis-consultas (historial)
│   ├── /app/mis-consultas/{uuid} (detalle consulta)
│   ├── /app/sala/{uuid} (videollamada)
│   ├── /app/agente (chat con agente IA)
│   ├── /app/mi-cuenta (perfil + datos natales + seguridad + privacidad)
│   └── /app/membresia (estado de membresía)
│
├── /admin (requiere rol admin)
│   ├── Dashboard
│   ├── Calendario
│   ├── Clientes
│   ├── Consultas (citas)
│   ├── Pagos
│   ├── Catálogo
│   ├── Promociones
│   ├── Notificaciones
│   ├── Agente IA
│   ├── Reportes
│   └── Configuración
│
├── /legal/terminos
├── /legal/privacidad
├── /legal/cookies
└── /legal/reembolsos
```

#### 6.7.2 Wireframe de la Landing (estructura)

```
┌─────────────────────────────────────────────────────────────────┐
│  HEADER: Logo TarotEstrellas | Servicios | Chachita | [Login]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│            ★  ·  ·  ★  ·  ★  ·  ·  ★  (fondo 3D)              │
│          ·     ★     ·     ·     ★     ·                        │
│                                                                 │
│           DESCUBRE LO QUE LAS                                   │
│           ESTRELLAS TIENEN                                      │
│           PARA TI                                               │
│                                                                 │
│           Consultas personalizadas de tarot,                    │
│           astrología y guía espiritual                          │
│           con Chachita desde cualquier                          │
│           lugar del mundo.                                      │
│                                                                 │
│           [ ✨ Agendar mi primera consulta ]                    │
│                                                                 │
│    ——————————————✦——————————————                                 │
│                                                                 │
│    SERVICIOS DESTACADOS                                         │
│                                                                 │
│    ┌──────────┐  ┌──────────┐  ┌──────────┐                    │
│    │  🔮      │  │  ⭐      │  │  🌙      │                    │
│    │  Tarot   │  │  Carta   │  │  Cartas  │                    │
│    │  2 horas │  │  Astral  │  │ Españolas│                    │
│    │  $65 USD │  │  2 horas │  │  1 hora  │                    │
│    │ [Agendar]│  │ [Agendar]│  │ [Agendar]│                    │
│    └──────────┘  └──────────┘  └──────────┘                    │
│                                                                 │
│    ——————————————✦——————————————                                 │
│                                                                 │
│    SOBRE CHACHITA                                               │
│    ┌─────────┐                                                  │
│    │  Foto   │  Con más de X años de experiencia...             │
│    │ Chachita│  Conectando almas con su propósito...            │
│    └─────────┘                                                  │
│                                                                 │
│    ——————————————✦——————————————                                 │
│                                                                 │
│    CÓMO FUNCIONA                                                │
│    ① Elige tu consulta   ② Agenda y paga   ③ Conéctate en vivo │
│                                                                 │
│    ——————————————✦——————————————                                 │
│                                                                 │
│    LO QUE DICEN NUESTROS CLIENTES                               │
│    ◄ "Increíble experiencia..." — María, Venezuela  ►           │
│                                                                 │
│    ——————————————✦——————————————                                 │
│                                                                 │
│    PREGUNTAS FRECUENTES                                         │
│    ▸ ¿Qué necesito para una consulta?                           │
│    ▸ ¿Cómo es el pago?                                          │
│    ▸ ¿Puedo cancelar?                                           │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│  FOOTER: Términos | Privacidad | Cookies | © TarotEstrellas     │
│  Redes: Instagram | TikTok | YouTube                            │
└─────────────────────────────────────────────────────────────────┘
```

#### 6.7.3 Wireframe del calendario de agendamiento (móvil)

```
┌──────────────────────────┐
│ ← Agendar: Tarot (2h)   │
├──────────────────────────┤
│                          │
│  ◄  Abril 2026  ►       │
│  Lu Ma Mi Ju Vi Sa Do   │
│  --  1  2  3  4  5  6   │
│   7  8  9 10 11 12 13   │
│  14 15 [16] 17 18 19 20 │
│  21 ●22 ●23 ●24 25 26 27│
│  ●28 ●29 ●30 -- -- -- --│
│                          │
│  ● = días con disponib.  │
│  [16] = hoy              │
│                          │
│  ─── Miércoles 22 ───    │
│                          │
│  ┌────────────────────┐  │
│  │ 10:00 - 12:00      │  │
│  │ (10:00 hora Chile) │  │
│  └────────────────────┘  │
│  ┌────────────────────┐  │
│  │ 14:00 - 16:00      │  │
│  │ (14:00 hora Chile) │  │
│  └────────────────────┘  │
│  ┌────────────────────┐  │
│  │ 16:30 - 18:30      │  │
│  │ Últimos disponibles│  │
│  └────────────────────┘  │
│                          │
│  🌐 Hora local: Caracas  │
│     [Ver en hora Chile]  │
│                          │
└──────────────────────────┘
```

#### 6.7.4 Wireframe de la sala de video

```
┌──────────────────────────────────────────────────────────────┐
│  🔴 Grabando  |  Tarot con Chachita  |  ⏱ 01:23:45 / 02:00 │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│                                                              │
│                    ┌────────────────────┐                    │
│                    │                    │                    │
│                    │                    │                    │
│                    │   VIDEO CHACHITA   │                    │
│                    │    (principal)     │                    │
│                    │                    │                    │
│                    │                    │                    │
│                    └────────────────────┘                    │
│                                          ┌─────────┐        │
│                                          │ Tu video│        │
│                                          │ (mini)  │        │
│                                          └─────────┘        │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│      🎤        📷        💬       🖥️        📞              │
│     Mute    Cámara     Chat   Compartir   Colgar            │
└──────────────────────────────────────────────────────────────┘
```

### 6.8 Diseño responsive

**Breakpoints (consistentes con Tailwind CSS):**

```css
--bp-sm: 640px;   /* Móvil grande */
--bp-md: 768px;   /* Tablet portrait */
--bp-lg: 1024px;  /* Tablet landscape / laptop pequeño */
--bp-xl: 1280px;  /* Desktop estándar */
--bp-2xl: 1536px; /* Desktop grande */
```

**Principio mobile-first:**

Todo se diseña primero para móvil (320px-640px), luego se amplía para pantallas más grandes. Razón: la mayoría de clientes de tarot y astrología en LATAM acceden desde móvil.

**Adaptaciones principales por breakpoint:**

| Componente | Móvil (<768px) | Tablet (768-1024px) | Desktop (>1024px) |
|------------|---------------|---------------------|-------------------|
| Navegación | Bottom tab bar | Sidebar colapsable | Header horizontal |
| Catálogo | 1 columna de cards | 2 columnas | 3 columnas |
| Calendario | Solo vista de mes | Vista mes + slots lateral | Vista semana completa |
| Sala de video | Video stack vertical | Video lado a lado | Video + panel lateral notas |
| Historial | Cards apiladas | Cards con preview | Tabla + panel detalle |
| Animaciones 3D | Reducidas (menos partículas) | Normales | Completas |

### 6.9 Accesibilidad

**Estándar objetivo:** WCAG 2.1 nivel AA

**Medidas implementadas:**
- Contraste de color verificado para toda la paleta contra fondos oscuros (ratio ≥ 4.5:1 para texto normal, ≥ 3:1 para texto grande)
- Textos alternativos en todas las imágenes y iconos decorativos marcados como `aria-hidden`
- Navegación completa con teclado (tab index, focus visible con borde dorado, skip-to-content)
- Labels explícitos en todos los inputs de formulario
- ARIA labels en componentes interactivos custom (modales, acordeones, tabs)
- Animaciones respetan `prefers-reduced-motion`: si el usuario tiene activada la opción de reducir movimiento, las animaciones 3D se desactivan y las transiciones 2D se minimizan
- Font sizes en `rem` (respetan zoom del navegador hasta 200%)
- Touch targets mínimo 44x44px en móvil (estándar Apple/Google)

### 6.10 Logo

**Concepto del logo:**

El logo de TarotEstrellas combina dos elementos:
1. **Una estrella de 8 puntas** (símbolo de la carta XVII "La Estrella" del tarot, representa esperanza, guía, iluminación)
2. **Tipografía en Italiana** para "TarotEstrellas" con la T y E iniciales ligeramente ornamentadas

**Variantes del logo:**
- **Logo completo**: icono de estrella + texto "TarotEstrellas" al lado
- **Logo icono**: solo la estrella (para favicon, app icon, redes sociales)
- **Logo compacto**: estrella + "TE" (para espacios muy reducidos)

**Colores del logo:**
- Versión principal: Oro Antiguo `#C9A84C` sobre fondo Noche Profunda `#0D0B1E`
- Versión monocroma: blanco para fondos oscuros, negro para fondos claros (documentos formales)
- Versión con glow: para uso digital (animación sutil de brillo en la estrella)

**Favicon y App Icon:**
- Favicon: estrella de 8 puntas dorada sobre fondo púrpura oscuro, 32x32px y 16x16px
- App icon (iOS/Android Fase 2): estrella con fondo gradiente Noche Profunda a Púrpura Cósmico, 1024x1024px con esquinas redondeadas iOS

**Exclusion zone:** mantener espacio libre alrededor del logo equivalente a la altura de la letra "T"

### 6.11 Tono de voz de la marca

**Personalidad de marca en textos:**

TarotEstrellas habla como una amiga sabia: cálida, directa, con un toque poético pero sin perder claridad.

**Principios:**
- **Cálido, nunca frío**: *"¡Gracias por confiar en nosotros!"* (no *"Su solicitud fue procesada"*)
- **Claro, nunca confuso**: *"Tu consulta es el miércoles a las 3pm"* (no *"Se ha agendado el servicio según disponibilidad"*)
- **Empoderador**: *"Las estrellas iluminan tu camino, pero tú decides por dónde caminar"* (no *"El destino está escrito"*)
- **Profesional con alma**: *"Tu sesión fue grabada y estará disponible en minutos"* (no *"Grabación procesada. ID: 7832"*)

**Ejemplos de tono por contexto:**

| Contexto | Tono | Ejemplo |
|----------|------|---------|
| Bienvenida | Cálido + emocionante | *"✨ Bienvenida a TarotEstrellas. Las estrellas llevan tiempo esperándote"* |
| Error de pago | Empático + solucionador | *"Hubo un problema con el pago. No te preocupes, tu cita sigue reservada por 30 minutos mientras lo resuelves"* |
| Confirmación | Celebratorio | *"¡Todo listo! Tu consulta de tarot está confirmada ✨"* |
| Cancelación | Comprensivo | *"Lamentamos que no puedas asistir. Tu reembolso está en proceso"* |
| Estado vacío | Invitador | *"Aún no tienes consultas. ¿Lista para tu primera aventura estelar?"* |
| Error técnico | Honesto + proactivo | *"Algo no salió como esperábamos. Estamos trabajando en solucionarlo"* |

---

## 7. API REST — Índice Consolidado

### 7.1 Convenciones generales

**Base URL:** `https://tarotestrellas.com/api`

**Formato:** JSON. Content-Type: `application/json`.

**Autenticación:** Bearer token vía header `Authorization: Bearer {token}` (Laravel Sanctum). Endpoints marcados como "público" no requieren token.

**Paginación:** cursor-based de Laravel con `data`, `meta` (current_page, per_page, total) y `links` (next, prev).

**Códigos de respuesta:** 200 (OK), 201 (creado), 204 (sin contenido), 400 (request inválido), 401 (no autenticado), 403 (sin permisos), 404 (no encontrado), 409 (conflicto), 422 (validación semántica), 429 (rate limit), 500 (error servidor).

**Formato de errores:**
```json
{
  "error": {
    "code": "SLOT_NO_DISPONIBLE",
    "message": "Este horario ya fue tomado. Por favor elige otro.",
    "details": {}
  }
}
```

**Rate limiting:** públicos 60 req/min por IP, autenticados 120 req/min por usuario, pagos 10 req/min, webhooks sin límite (validados por firma).

### 7.2 Endpoints públicos (4)

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/public/tipos-consulta` | Lista pública del catálogo |
| GET | `/api/public/tipos-consulta/{slug}` | Detalle de una consulta |
| GET | `/api/public/paquetes` | Paquetes y membresías disponibles |
| GET | `/api/health` | Health check del sistema |

### 7.3 Endpoints de autenticación (13)

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| POST | `/api/auth/register` | Registro nuevo |
| POST | `/api/auth/login` | Login email/password |
| POST | `/api/auth/logout` | Logout (revoca token) |
| GET | `/api/auth/social/{provider}/redirect` | Redirige a OAuth |
| GET | `/api/auth/social/{provider}/callback` | Callback OAuth |
| POST | `/api/auth/forgot-password` | Solicita reset |
| POST | `/api/auth/reset-password` | Ejecuta reset con token |
| POST | `/api/auth/verify-email/{token}` | Verifica email |
| POST | `/api/auth/resend-verification` | Reenvía verificación |
| POST | `/api/auth/2fa/enable` | Inicia activación 2FA |
| POST | `/api/auth/2fa/confirm` | Confirma código para activar |
| POST | `/api/auth/2fa/disable` | Desactiva 2FA |
| POST | `/api/auth/2fa/challenge` | Valida código en login |

### 7.4 Endpoints del cliente autenticado (34)

**Perfil y cuenta (10):**

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/me` | Datos del usuario actual |
| PATCH | `/api/me/profile` | Actualiza perfil |
| PATCH | `/api/me/datos-natales` | Actualiza datos natales |
| GET | `/api/me/export` | Inicia exportación GDPR |
| DELETE | `/api/me/account` | Elimina cuenta |
| PATCH | `/api/me/consentimientos` | Revoca consentimientos |
| GET | `/api/me/preferencias-notificacion` | Preferencias notificación |
| PATCH | `/api/me/preferencias-notificacion` | Actualizar preferencias |
| GET | `/api/me/creditos` | Créditos disponibles |
| GET | `/api/me/membresias` | Mis membresías |

**Catálogo, agendamiento y pagos (14):**

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/tipos-consulta` | Lista con precios personalizados |
| GET | `/api/tipos-consulta/{slug}/disponibilidad-rapida` | Próximos 3 slots |
| GET | `/api/disponibilidad` | Slots disponibles por tipo y rango |
| POST | `/api/citas` | Crear cita (pendiente_abono) |
| GET | `/api/citas/{uuid}` | Detalle de cita |
| POST | `/api/citas/{uuid}/reagendar` | Reagendar cita |
| POST | `/api/citas/{uuid}/cancelar` | Cancelar cita |
| GET | `/api/citas/{uuid}/calendario-ics` | Descargar archivo .ics |
| POST | `/api/citas/{uuid}/pagar/stripe` | Crear PaymentIntent |
| GET | `/api/citas/{uuid}/pagar/transferencia/datos` | Obtener datos bancarios |
| POST | `/api/citas/{uuid}/pagar/transferencia/comprobante` | Subir comprobante |
| GET | `/api/pagos/{uuid}` | Detalle de pago |
| POST | `/api/me/membresias/comprar` | Iniciar compra membresía |
| POST | `/api/cupones/validar` | Validar cupón |

**Consultas, video y agente IA (10):**

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/me/consultas` | Lista de mis consultas |
| GET | `/api/me/consultas/{uuid}` | Detalle consulta |
| GET | `/api/me/consultas/{uuid}/transcripcion` | Transcripción |
| GET | `/api/me/consultas/{uuid}/grabacion-url` | URL firmada grabación |
| GET | `/api/me/consultas/{uuid}/resumen` | Resumen IA |
| PATCH | `/api/me/consultas/{uuid}/notas-privadas` | Notas del cliente |
| GET | `/api/me/consultas/export/pdf` | PDF de historial |
| GET | `/api/citas/{uuid}/sala` | Info de la sala video |
| POST | `/api/citas/{uuid}/sala/consentimiento` | Consentimiento grabación |
| POST | `/api/citas/{uuid}/sala/entrada` | Marca entrada |

### 7.5 Endpoints de webhooks (4)

| Método | Endpoint | Proveedor | Validación |
|--------|----------|-----------|------------|
| POST | `/api/webhooks/stripe` | Stripe | HMAC-SHA256 |
| POST | `/api/webhooks/daily` | Daily.co | HMAC |
| POST | `/api/webhooks/whatsapp` | Meta / YCloud | App Secret o API Key |
| POST | `/api/webhooks/resend` | Resend | Webhook secret |

### 7.6 Endpoints de administración (47)

**Dashboard y reportes (9):**

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/admin/dashboard` | KPIs del dashboard |
| GET | `/api/admin/dashboard/grafico-ingresos` | Gráfico ingresos |
| GET | `/api/admin/dashboard/grafico-tipos` | Gráfico por tipo |
| GET | `/api/admin/reportes/ingresos` | Reporte ingresos |
| GET | `/api/admin/reportes/consultas` | Reporte consultas |
| GET | `/api/admin/reportes/clientes` | Reporte clientes |
| GET | `/api/admin/reportes/fiscal` | Reporte fiscal |
| GET | `/api/admin/reportes/costos-ia` | Reporte costos IA |
| POST | `/api/admin/reportes/{tipo}/exportar` | Exportar Excel/PDF |

**Clientes, citas, disponibilidad (13):**

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/admin/clientes` | Lista clientes |
| GET | `/api/admin/clientes/{uuid}` | Ficha completa |
| PATCH | `/api/admin/clientes/{uuid}/notas` | Notas Chachita |
| GET | `/api/admin/clientes/{uuid}/briefing` | Briefing IA |
| GET | `/api/admin/clientes/{uuid}/estadisticas` | Stats cliente |
| GET | `/api/admin/calendario` | Vista agenda |
| POST | `/api/admin/citas/{uuid}/mover` | Mover cita |
| POST | `/api/admin/citas/mover-dia-completo` | Mover día completo |
| GET | `/api/admin/disponibilidad-base` | Horario base |
| PUT | `/api/admin/disponibilidad-base` | Actualizar horario |
| POST | `/api/admin/bloqueos` | Crear bloqueo |
| PATCH | `/api/admin/bloqueos/{id}` | Editar bloqueo |
| DELETE | `/api/admin/bloqueos/{id}` | Eliminar bloqueo |

**Pagos, promociones, notificaciones, sistema (25):**

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| GET | `/api/admin/reembolsos` | Listar reembolsos |
| GET | `/api/admin/reembolsos/{uuid}` | Detalle de reembolso + timeline |
| GET | `/api/admin/reembolsos/metricas` | Métricas de reembolsos |
| GET | `/api/admin/reembolsos/export` | Exportar reembolsos (CSV) |
| POST | `/api/admin/reembolsos` | Crear reembolso |
| POST | `/api/admin/reembolsos/{uuid}/procesar` | Procesar/reintentar/marcar completado |
| POST | `/api/admin/comprobantes/{id}/aprobar` | Aprobar comprobante |
| POST | `/api/admin/comprobantes/{id}/rechazar` | Rechazar comprobante |
| GET | `/api/admin/cupones` | Listar cupones |
| POST | `/api/admin/cupones` | Crear cupón |
| PATCH | `/api/admin/cupones/{id}` | Editar cupón |
| POST | `/api/admin/cupones/{id}/desactivar` | Desactivar |
| POST | `/api/admin/cupones/{id}/duplicar` | Duplicar |
| GET | `/api/admin/cupones/{id}/estadisticas` | Stats cupón |
| GET | `/api/admin/paquetes` | Listar paquetes |
| POST | `/api/admin/paquetes` | Crear paquete |
| PATCH | `/api/admin/paquetes/{id}` | Editar paquete |
| GET | `/api/admin/paquetes/{id}/estadisticas` | Stats paquete |
| GET | `/api/admin/promociones/dashboard` | Dashboard promos |
| PATCH | `/api/admin/primera-consulta/config` | Config descuento |
| GET | `/api/admin/plantillas` | Listar plantillas |
| PATCH | `/api/admin/plantillas/{id}` | Editar plantilla |
| POST | `/api/admin/plantillas/{id}/preview` | Preview |
| GET | `/api/admin/notificaciones/monitor` | Monitor entregas |
| GET | `/api/admin/notificaciones/log` | Log completo |
| GET | `/api/admin/configuracion` | Parámetros sistema |
| PATCH | `/api/admin/configuracion/{clave}` | Actualizar parámetro |
| GET | `/api/admin/integraciones` | Estado servicios |
| POST | `/api/admin/backups/ejecutar` | Backup manual |
| GET | `/api/admin/audit-log` | Log auditoría |

### 7.7 Resumen

**Total: 102 endpoints** (4 públicos + 13 auth + 34 cliente + 4 webhooks + 47 admin)

---

## 8. Plan de Implementación por Fases

### 8.1 Contexto del plan

**Desarrollador:** Luis Miguel (Automatizatech), solo, con asistencia de Claude AI
**Dedicación:** Part-time, 3-4 horas/día (lunes a viernes, fines de semana opcionales)
**Stack nuevo para el developer:** Laravel (viene de WAMP/PHP tradicional), Git (viene de FTP), React con TypeScript
**Herramientas de desarrollo:** VS Code + Laragon + GitHub + SSH al VPS
**Objetivo:** MVP de TarotEstrellas en producción, funcional y listo para que Chachita reciba clientes reales

### 8.2 Visión general de las fases

```
FASE 0 ──────── FASE 1 (MVP) ─────────────────────────── FASE 2 ──── FASE 3
(2 sem)         (14-16 semanas)                           (+6 sem)    (futuro)
Setup           Core funcional + lanzamiento              App móvil   Escalar
```

**Fase 0 — Setup y fundaciones (Semanas 1-2)**
Preparar el entorno, aprender herramientas nuevas, configurar servicios base.

**Fase 1 — MVP (Semanas 3-18)**
Construir toda la funcionalidad aprobada para el lanzamiento. Dividida en 8 sprints de 2 semanas cada uno.

**Fase 2 — App Móvil + IA Avanzada (post-lanzamiento, +6 semanas)**
Capacitor para iOS/Android, agente IA conversacional, push notifications, login Apple/Facebook.

**Fase 3 — Escalamiento (cuando el negocio lo justifique)**
Multi-especialista, integración SII, internacionalización.

### 8.3 FASE 0 — Setup y Fundaciones (Semanas 1-2)

#### Semana 1: Entorno local y aprendizaje

**Objetivo:** tener el stack de desarrollo corriendo en tu computadora y familiarizarte con las herramientas nuevas.

**Día 1 (3-4h):**
- Instalar Laragon (versión Full) → verificar que PHP 8.3, MariaDB, Node.js y Redis funcionen
- Instalar Git para Windows (viene con Laragon, pero verificar)
- Configurar VS Code con extensiones: PHP Intelephense, Laravel Blade Snippets, Tailwind CSS IntelliSense, GitLens, ES7+ React Snippets, Prettier
- Crear cuenta GitHub (si no tienes) + crear repositorio privado `tarotestrellas`
- Clonar repo vacío en tu máquina local

**Día 2 (3-4h):**
- Tutorial rápido de Git (commit, push, pull, branches): 1 hora
- Crear proyecto Laravel nuevo: `composer create-project laravel/laravel backend`
- Crear proyecto React nuevo: `npm create vite@latest frontend -- --template react-ts`
- Primer commit y push a GitHub
- Verificar que ambos proyectos corren en Laragon

**Día 3 (3-4h):**
- Instalar dependencias base de Laravel: Sanctum, Horizon, Filament, Cashier, Socialite
- Configurar `.env` con MariaDB local y Redis local
- Correr primera migración (`php artisan migrate`) — verificar conexión a BD
- Instalar Filament y verificar que el panel admin abre en `/admin`

**Día 4 (3-4h):**
- Instalar dependencias base de React: Tailwind CSS, React Router, TanStack Query, Zustand, Framer Motion, React Hook Form, Zod, Lucide React
- Configurar Tailwind con los colores del dual mode (dark/light)
- Crear layout base con header, footer, y toggle de tema
- Verificar que el frontend corre y muestra la landing básica

**Día 5 (3-4h):**
- Conectar frontend con backend: configurar CORS en Laravel, hacer primera llamada API desde React
- Crear endpoint de prueba `GET /api/ping` → frontend lo consume y muestra respuesta
- Primer deploy mental: entender la estructura del monorepo
- Commit de todo lo avanzado, push a GitHub

**Entregable Semana 1:** Stack local corriendo (Laravel + React + MariaDB + Redis), repositorio en GitHub con primer commit, panel Filament accesible.

#### Semana 2: VPS, servicios externos y estructura base

**Día 6 (3-4h):**
- Configurar VPS KVM 2 de Hostinger: SSH access, actualizar Ubuntu, instalar Nginx + PHP 8.3 + MariaDB + Redis + Supervisor + Certbot
- Usar script de provisioning o hacerlo manualmente paso a paso con Claude guiándote
- Verificar que Nginx sirve página default en la IP del VPS

**Día 7 (3-4h):**
- Apuntar DNS de `tarotestrellas.com` a Cloudflare (cambiar nameservers en Hostinger)
- Configurar Cloudflare: proxy activado, SSL Full (Strict), page rules básicas
- Instalar certificado Let's Encrypt en el VPS con Certbot
- Verificar que `https://tarotestrellas.com` muestra la página default de Nginx

**Día 8 (3-4h):**
- Crear cuentas en servicios externos (todas gratis): Stripe, Daily.co, Cloudflare R2, Resend, Sentry
- Verificar API keys de OpenAI y Anthropic (ya tienes cuentas)
- Guardar todas las keys en un archivo seguro (NO en Git) — solo en `.env`
- Configurar Sentry: crear proyecto Laravel + proyecto React, anotar DSN

**Día 9 (3-4h):**
- Configurar GitHub Actions para deploy automático al VPS
- Crear workflow `.github/workflows/deploy.yml` que al hacer push a `main` ejecute: SSH al VPS → git pull → composer install → npm run build → php artisan migrate → reiniciar workers
- Hacer primer deploy automático: subir la app básica al VPS
- Verificar que `https://tarotestrellas.com` muestra la app Laravel

**Día 10 (3-4h):**
- Crear estructura base de la BD: ejecutar migraciones de las tablas core (users, roles, user_profiles, configuracion_sistema)
- Crear seeders iniciales: roles, usuario admin (Chachita), parámetros de configuración
- Correr seeds en local y verificar en phpMyAdmin/TablePlus
- Commit, push, deploy automático, verificar en producción

**Entregable Semana 2:** VPS configurado, dominio apuntando con SSL, deploy automático funcionando, servicios externos con cuentas creadas, BD con tablas base y seeds iniciales.

### 8.4 FASE 1 — MVP (Semanas 3-18)

#### Sprint 1 (Semanas 3-4): Autenticación + Perfiles

**Objetivo:** que un usuario pueda registrarse, verificar email, loguearse, completar perfil y ver su dashboard vacío.

**Backend (Semana 3):**
- Migraciones: `users` completa, `user_profiles`, `datos_natales`, `consentimientos`, `user_roles`, `preferencias_notificacion`
- API de registro: `POST /api/auth/register` con validaciones completas
- API de login: `POST /api/auth/login` con rate limiting y tokens Sanctum
- API de verificación de email con token
- API de recuperación de contraseña
- Login social con Google (Socialite)
- Middleware de roles
- Tests unitarios de autenticación

**Frontend (Semana 4):**
- Páginas: `/auth/login`, `/auth/register`, `/auth/forgot-password`
- Formularios con React Hook Form + Zod + validación en tiempo real
- Integración con API de auth (login, registro, logout)
- Botón "Continuar con Google"
- Layout de la app autenticada (`/app/*`) con header, sidebar móvil, protección de rutas
- Página `/app/mi-cuenta` con formulario de perfil editable
- Formulario de datos natales
- Banner de verificación de email pendiente
- Toggle de modo oscuro/claro en header

**Entregable Sprint 1:** Usuario puede registrarse → verificar email → loguearse → completar perfil → ver dashboard vacío. Login con Google funciona. Modo oscuro/claro funciona.

---

#### Sprint 2 (Semanas 5-6): Catálogo + Landing Pública

**Objetivo:** que cualquier visitante vea la landing, el catálogo de servicios, y la página de detalle de cada consulta. Chachita puede gestionar el catálogo desde admin.

**Backend (Semana 5):**
- Migraciones: `tipos_consulta`, `tipos_consulta_precios`
- Seeders: los 12 tipos de consulta con precios en CLP y USD
- API pública: `GET /api/public/tipos-consulta`, `GET /api/public/tipos-consulta/{slug}`
- API autenticada con precios personalizados por moneda del usuario
- Panel admin (Filament): CRUD de tipos de consulta con drag-and-drop, editor de precios multi-moneda, upload de imágenes
- Detección de país por IP (usando header Cloudflare `CF-IPCountry`)

**Frontend (Semana 6):**
- Landing page completa (`/`): hero con animación de partículas (3D simplificada), servicios destacados, sección sobre Chachita, cómo funciona, FAQs, footer
- Página de catálogo (`/servicios`): grid de cards con filtros y búsqueda
- Página de detalle (`/servicios/{slug}`): hero temático, info completa, CTA "Agendar"
- Conversión de precios a moneda del usuario
- Animaciones de entrada con Framer Motion (cards escalonadas)
- Responsive: mobile-first, testeado en 3 breakpoints
- SEO básico: meta tags, Open Graph, título dinámico por página

**Entregable Sprint 2:** Landing pública profesional visible en `tarotestrellas.com`. Catálogo con 12 servicios. Chachita puede editar desde admin. Precios se muestran en la moneda del visitante.

---

#### Sprint 3 (Semanas 7-8): Agendamiento + Calendario

**Objetivo:** que un cliente pueda ver disponibilidad y seleccionar fecha/hora para su consulta. Chachita puede gestionar su horario desde admin.

**Backend (Semana 7):**
- Migraciones: `disponibilidad_base`, `bloqueos_agenda`, `citas`, `citas_estados_historial`, `reagendamientos`
- Seeders: horario base de Chachita (Lun-Vie 10-18, Sáb 10-14), feriados chilenos 2026
- Algoritmo de cálculo de disponibilidad (los 12 pasos documentados en 5.4.3)
- Cache de disponibilidad en Redis (TTL 5 min, invalidación por eventos)
- API: `GET /api/disponibilidad`, `POST /api/citas` (crea cita en estado pendiente_abono)
- Conversión de zonas horarias (UTC en BD, Chile para cálculos, TZ del cliente para presentación)
- Job `ExpirarReservasJob` (cada minuto, expira citas pendientes pasados los 30 min)
- Panel admin: calendario visual (vista semana/mes), gestión de bloqueos, configuración de horario base

**Frontend (Semana 8):**
- Flujo de agendamiento: paso 1 (tipo ya seleccionado) → paso 2 (calendario + hora) → paso 3 (info adicional) → paso 4 (resumen pre-pago)
- Componente de calendario mensual con días disponibles marcados
- Panel de slots disponibles al seleccionar día
- Selector de zona horaria con detección automática
- Countdown de reserva temporal (30 min)
- Vista "Mis consultas" básica (lista de citas del cliente)
- Responsive: calendario adaptado a móvil (vista mes compacta)

**Entregable Sprint 3:** Cliente puede navegar el calendario, elegir fecha/hora, y crear una cita (estado pendiente). Chachita ve las citas en su calendario admin. Zonas horarias funcionan correctamente.

---

#### Sprint 4 (Semanas 9-10): Pagos Stripe + Transferencia

**Objetivo:** que el cliente pueda pagar el 20% de abono (con Stripe o transferencia chilena) y que la cita se confirme.

**Backend (Semana 9):**
- Migraciones: `pagos`, `comprobantes_transferencia`, `validaciones_agente`, `metodos_pago_chachita`, `reembolsos`, `creditos_cliente`
- Integración Stripe: crear PaymentIntent, webhooks (`payment_intent.succeeded`, `payment_intent.payment_failed`), validación de firma HMAC
- Flujo de transferencia: datos bancarios parametrizados, generación de código de referencia único `TE-XXXX-YYYY`
- Upload de comprobante a Cloudflare R2 (presigned URL)
- Job `ValidarComprobanteJob`: llamada a Claude Vision API para OCR + 5 reglas de validación
- Job `ProcesarReembolsoJob`: refund vía Stripe API o marca para transferencia manual
- Generación de recibo PDF con `barryvdh/laravel-dompdf`
- Eventos y listeners: `PagoCompletadoEvent` → actualizar cita + enviar confirmaciones
- Panel admin: lista de pagos, comprobantes pendientes de revisión, aprobación/rechazo manual

**Frontend (Semana 10):**
- Paso de pago en flujo de agendamiento: selector Stripe vs Transferencia
- Integración Stripe Elements: formulario de tarjeta embebido, manejo de 3D Secure, estados de error
- Pantalla de transferencia: datos bancarios + código de referencia + countdown 30 min + upload de comprobante + botón extender 10 min
- Pantalla de éxito con animación de constelación
- Descarga de archivo .ics (agregar al calendario)
- Vista de detalle de cita con estado de pagos
- Recibo descargable desde historial

**Entregable Sprint 4:** Cliente puede pagar con tarjeta (Stripe) o transferencia chilena. Comprobantes se validan automáticamente con IA. Cita pasa a "reservada" al confirmarse el abono. Chachita ve los pagos y puede aprobar/rechazar comprobantes manualmente.

---

#### Sprint 5 (Semanas 11-12): Videollamada + Grabación

**Objetivo:** que cliente y Chachita puedan conectarse en videollamada dentro de la app, con grabación automática.

**Backend (Semana 11):**
- Migraciones: `sesiones_video`, `grabaciones`
- Integración Daily.co: crear salas (`POST /rooms`), generar meeting tokens, configurar grabación cloud → R2
- Webhooks Daily.co: `meeting.started`, `meeting.ended`, `recording.ready`, `recording.error`
- Job `CrearSalaDailyJob`: se dispara cuando cita se confirma (100% pagado)
- Job `EliminarVideosVencidosJob`: diario, elimina grabaciones >90 días
- Job `AvisarEliminacionProximaJob`: diario, avisa 7 días antes
- API: `GET /api/citas/{uuid}/sala`, `POST /api/citas/{uuid}/sala/consentimiento`
- Generación de URLs firmadas temporales para reproducción de grabaciones
- Panel admin: vista de sesiones de video, estado de grabaciones

**Frontend (Semana 12):**
- Página de sala de video (`/app/sala/{uuid}`): verificaciones pre-sala (pago, horario, sala creada)
- Modal de consentimiento de grabación (checkbox obligatorio)
- Test pre-sala: preview de cámara + micrófono
- Sala embebida con SDK Daily.co: video principal + mini self-view + controles (mute, cámara, chat, colgar)
- Indicador "🔴 Grabando" + reloj de duración + tiempo restante
- Pantalla post-sesión: "Tu grabación y transcripción estarán disponibles pronto"
- Reproductor de grabaciones en historial del cliente (video con URL firmada)
- Manejo de errores: desconexión, reconexión, cliente no aparece

**Entregable Sprint 5:** Videollamada funciona end-to-end dentro de la app. Grabación se guarda en R2. Cliente puede ver la grabación desde su historial. Se elimina automáticamente a los 3 meses con aviso previo.

---

#### Sprint 6 (Semanas 13-14): Transcripción + Resumen IA + Notificaciones

**Objetivo:** pipeline post-sesión completo (transcripción + resumen + embeddings) y sistema de notificaciones funcionando.

**Backend (Semana 13):**
- Migraciones: `transcripciones`, `resumenes_consulta`, `embeddings_transcripciones`, `plantillas_notificacion`, `notificaciones_enviadas`, `preferencias_notificacion`
- Pipeline post-sesión (cadena de 5 jobs):
  1. `DescargarAudioJob`: extrae audio con ffmpeg, sube mp3 a R2
  2. `TranscribirAudioJob`: llama Whisper API, guarda transcripción con segmentos
  3. `GenerarResumenJob`: llama Claude Haiku, parsea JSON, guarda resumen
  4. `GenerarEmbeddingsJob`: fragmenta texto, llama OpenAI embeddings, guarda vectores
  5. `NotificarTranscripcionListaJob`: envía email + WhatsApp al cliente
- Integración Resend: driver de email, envío de confirmaciones, recibos adjuntos
- Integración Meta WhatsApp Cloud API: envío de templates aprobados por Meta
- Seeders de plantillas de notificación (las ~25 plantillas definidas)
- Programación de recordatorios (48h, 3h, 30min) con jobs en cola
- Resúmenes automáticos para Chachita: diario (8am), semanal (lun 9am), mensual (día 1)
- Panel admin: plantillas editables con preview, monitor de entregas, preferencias

**Frontend (Semana 14):**
- Tab de transcripción en detalle de consulta: texto completo con timestamps, búsqueda en texto
- Tab de resumen IA: resumen corto, temas detectados como chips, emociones
- Preferencias de notificación en perfil del cliente
- Indicador de "procesando" mientras pipeline corre (polling al estado)
- Vista de detalle de consulta completa con todas las tabs funcionales

**Entregable Sprint 6:** Al terminar una videollamada, el pipeline genera automáticamente: audio → transcripción → resumen IA → embeddings. Cliente recibe email/WhatsApp cuando está listo. Recordatorios de citas funcionan. Chachita recibe resúmenes diarios.

---

#### Sprint 7 (Semanas 15-16): Promociones + Membresías + Pagos 80%

**Objetivo:** sistema completo de promociones, membresía 12x2, cupones, descuento primera consulta, y flujo de cobro del 80%.

**Backend (Semana 15):**
- Migraciones: `paquetes`, `paquete_tipos_consulta`, `cupones`, `membresias`
- Lógica de cupones: validación completa (vigencia, usos, monto mínimo, tipo aplicable, primera consulta)
- Lógica de membresía 12x2: compra, consumo al agendar, expiración, contador
- Descuento primera consulta automático (detección + aplicación)
- Flujo del 80%: recordatorios automáticos (48h, 3h, 30min antes), PaymentIntent del saldo, bloqueo de sala si no pagó
- Job `ExpirarMembresiasJob`: diario
- Job `RecordatorioPago80Job`: programado por cita
- Panel admin: CRUD cupones con estadísticas, gestión de paquetes/membresías, dashboard de promociones, configuración de descuento primera consulta

**Frontend (Semana 16):**
- Campo de cupón en checkout (validación en tiempo real)
- Opción "Usar membresía" si aplica en checkout
- Badge "10% off primera consulta" en catálogo y checkout
- Página de membresía del cliente: estado, consultas restantes, fecha vencimiento
- Flujo de pago del 80%: link desde email/WhatsApp, pantalla de pago, confirmación
- Dashboard de promociones en admin (gráficos de uso, ROI)

**Entregable Sprint 7:** Chachita puede crear cupones y paquetes. Membresía 12x2 funciona. Descuento primera consulta se aplica automáticamente. Flujo completo de cobro 20% + 80% funciona end-to-end.

---

#### Sprint 8 (Semanas 17-18): Reportes + Seguridad + Pulido + Lanzamiento

**Objetivo:** completar reportes financieros, reforzar seguridad, pulir UX, redactar documentos legales, y lanzar a producción.

**Backend (Semana 17):**
- Migraciones: `audit_log` (si no se creó antes)
- Reportes en panel admin:
  - Reporte de ingresos (por período, tipo, país, moneda) con export Excel/PDF
  - Reporte de consultas (por período, tipo, estado)
  - Reporte de clientes (nuevos, activos, retención, geografía)
  - Reporte fiscal (ingresos brutos/netos mensuales en CLP)
  - Reporte de costos IA
- Dashboard admin completo: KPIs del día, gráficos, acciones rápidas
- Seguridad: activar 2FA en admin, configurar rate limiting final, revisar CORS, validar todos los endpoints, test de webhooks
- Instalar Umami (analytics) en el VPS
- Redactar documentos legales: Términos y Condiciones, Política de Privacidad, Cookies, Reembolsos, Disclaimer esotérico, Consentimiento de grabación
- Publicar documentos en `/legal/*`
- Configurar backups automáticos diarios (BD → R2)
- Revisar logs de Sentry, corregir errores pendientes

**Frontend (Semana 18):**
- Páginas legales (`/legal/terminos`, `/legal/privacidad`, `/legal/cookies`, `/legal/reembolsos`)
- Checkbox de aceptación de términos en registro (con links a los documentos)
- Animaciones 3D finales: cielo estrellado en landing, carta flotante en catálogo, constelación al pagar, portal al entrar al video
- Pulido de UX: revisar todos los flujos en móvil, corregir detalles visuales, mensajes de error amigables, estados vacíos con ilustraciones
- Optimización de performance: lazy loading de componentes pesados, compresión de imágenes, code splitting
- Testing manual completo: registrar usuario real, agendar cita, pagar (modo test de Stripe), hacer videollamada corta, verificar transcripción
- Deploy final a producción

**Entregable Sprint 8:** TarotEstrellas en producción, listo para recibir clientes reales. Reportes funcionando. Documentos legales publicados. Animaciones 3D en las páginas clave. Performance optimizada.

### 8.5 Checklist de lanzamiento (día del go-live)

**Antes de anunciar a clientes:**

- [ ] Stripe en modo live (no test) con clave de producción
- [ ] Daily.co con dominio verificado
- [ ] Resend con dominio verificado (SPF + DKIM configurados)
- [ ] WhatsApp Business API con templates aprobados por Meta
- [ ] Cloudflare R2 con bucket de producción
- [ ] OpenAI y Anthropic con créditos suficientes cargados
- [ ] Sentry capturando errores de producción
- [ ] Umami trackando visitas
- [ ] Backups automáticos verificados (restaurar un backup de prueba)
- [ ] SSL funcionando correctamente en todo el dominio
- [ ] Documentos legales publicados y accesibles
- [ ] Cuenta admin de Chachita creada con 2FA activado
- [ ] Horario base de Chachita configurado
- [ ] Al menos 3 tipos de consulta activos con precios reales
- [ ] Email de bienvenida y recordatorios funcionando (test con cuenta real)
- [ ] WhatsApp funcionando (test con número real)
- [ ] Hacer una cita de prueba completa end-to-end con pago real de $1 USD (luego reembolsar)
- [ ] Velocidad de carga <3 segundos en móvil 4G (medir con Lighthouse)
- [ ] Score Lighthouse >80 en Performance y Accessibility

**Al anunciar:**

- [ ] Chachita publica en su Instagram/TikTok con link a `tarotestrellas.com`
- [ ] Activar cupón de lanzamiento (ej: `ESTRELLAS2026` = 15% off)
- [ ] Monitorear Sentry las primeras 24h por errores
- [ ] Estar disponible para soporte durante las primeras 48h
- [ ] Verificar que la primera cita real funciona completamente

### 8.6 FASE 2 — App Móvil + IA Avanzada (post-lanzamiento)

**Duración estimada:** 6 semanas adicionales (part-time)

**Sprint 9 (Semanas 19-20): Agente IA conversacional**
- Tablas: `agente_conversaciones`, `agente_mensajes`
- Backend: endpoint de chat con streaming, búsqueda semántica en embeddings, system prompts diferenciados (Chachita vs cliente)
- Frontend: interfaz de chat en "Mis consultas" y en ficha admin del cliente
- Briefing IA pre-consulta para Chachita

**Sprint 10 (Semanas 21-22): App móvil con Capacitor**
- Configurar Capacitor en el proyecto React existente
- Adaptar UI para patrones nativos (bottom tabs, gestos, safe areas)
- Configurar Firebase Cloud Messaging para push notifications
- Login con Apple (obligatorio para iOS App Store)
- Login con Facebook
- Build de iOS: requiere cuenta Apple Developer ($99 USD/año) + Mac para build
- Build de Android: APK/AAB para Google Play

**Sprint 11 (Semanas 23-24): Publicación y refinamiento**
- Publicar en App Store y Google Play
- App Store Review (puede tomar 1-5 días, posibles rechazos por el rubro esotérico — tener plan B con disclaimer reforzado)
- Push notifications integradas en los flujos de notificación existentes
- Reportes fiscales avanzados
- Corrección de bugs post-lanzamiento Fase 1

### 8.7 FASE 3 — Escalamiento (cuando lo justifique)

**Sin timeline fijo.** Se activa cuando Chachita quiera sumar más especialistas o el volumen crezca significativamente.

**Sprint 12+: Multi-especialista**
- Flujo de registro de especialista (con aprobación admin)
- Perfil público de cada especialista
- Calendario independiente por especialista
- Routing de pagos (cada especialista recibe su parte)
- Dashboard consolidado para super admin
- Comisiones parametrizables

**Sprint 13+: Integración SII**
- Investigar API del SII o integrar con Bsale/Nubox
- Emisión automática de boletas electrónicas
- Mapping de servicios a categorías tributarias

**Sprint 14+: Internacionalización**
- Sistema de traducciones (Laravel Localization + i18n en React)
- Traducción de toda la UI a inglés
- Plantillas de notificación en inglés
- Landing en inglés con detección automática de idioma

### 8.8 Resumen visual del roadmap

```
Semana  1  2 │ 3  4 │ 5  6 │ 7  8 │ 9 10 │11 12 │13 14 │15 16 │17 18
        ─────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────
Fase 0  ████ │      │      │      │      │      │      │      │
Sprint 1     │ ████ │      │      │      │      │      │      │
Sprint 2     │      │ ████ │      │      │      │      │      │
Sprint 3     │      │      │ ████ │      │      │      │      │
Sprint 4     │      │      │      │ ████ │      │      │      │
Sprint 5     │      │      │      │      │ ████ │      │      │
Sprint 6     │      │      │      │      │      │ ████ │      │
Sprint 7     │      │      │      │      │      │      │ ████ │
Sprint 8     │      │      │      │      │      │      │      │ ████
             │      │      │      │      │      │      │      │   ↑
             │      │      │      │      │      │      │      │ 🚀 LANZAMIENTO
```

### 8.9 Gestión de riesgos

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Curva de aprendizaje Laravel más lenta de lo esperado | Alta | Medio | Claude AI asiste en cada paso. Los primeros sprints tienen buffer. Tutoriales Laracasts disponibles gratis |
| Stripe rechaza cuenta por rubro esotérico | Baja | Alto | Stripe acepta "servicios de entretenimiento/bienestar". Si rechaza: alternativa con dLocal o PayPal |
| App Store rechaza la app por contenido esotérico | Media | Medio | Reforzar disclaimer ("entretenimiento"), cumplir guidelines de Apple, apelar si rechazan |
| WhatsApp templates no aprobados por Meta | Media | Bajo | Iniciar proceso de aprobación en Semana 2. Si no aprueba: lanzar solo con email, WhatsApp en Fase 2 |
| Daily.co tier gratuito insuficiente | Muy baja | Bajo | 10k min/mes es más que suficiente. Si crece: upgrade cuesta ~$15/mes |
| VPS KVM 2 sin recursos suficientes | Muy baja | Medio | Monitoring con Sentry + health checks. Upgrade a KVM 4 ($12/mes más) si es necesario |
| Chachita no se adapta al panel admin | Media | Medio | Sesión de capacitación de 2h antes del lanzamiento. Manual visual con screenshots. Soporte las primeras 2 semanas |
| Bugs críticos post-lanzamiento | Alta | Alto | Testing manual completo en Sprint 8. Sentry para detección inmediata. Estar disponible 48h post-lanzamiento |

### 8.10 Cómo trabajar con Claude AI durante el desarrollo

**Estrategia recomendada para cada sprint:**

1. **Al inicio del sprint:** abre una nueva conversación con Claude y pega la sección relevante de esta especificación técnica. Ej: para Sprint 3, pega la sección 5.4 (Agendamiento) + las tablas relevantes de la sección 4.
2. **Pide a Claude que genere el código:** "Crea la migración de Laravel para la tabla `citas` con todos los campos documentados aquí" → Claude genera el archivo completo.
3. **Itera rápidamente:** si algo no funciona, pega el error y Claude te ayuda a resolverlo.
4. **Commit frecuente:** cada feature funcional → commit con mensaje descriptivo → push a GitHub.
5. **Al final del sprint:** revisa el checklist del entregable y verifica que todo funciona.

**Tips para maximizar la productividad con Claude:**

- Dale contexto suficiente: pega las secciones relevantes del documento, no asumas que Claude "recuerda" conversaciones anteriores
- Pide código completo, no fragmentos: "Genera el controlador completo de autenticación con todos los métodos documentados"
- Si algo no funciona: pega el error completo (stack trace) y el código relevante
- Usa Claude para escribir tests: "Escribe tests PHPUnit para el servicio de agendamiento cubriendo los edge cases documentados en 5.4.11"
- Para frontend: describe la pantalla que necesitas y pega el wireframe correspondiente de la sección 6

### 8.11 Tareas paralelas (no bloqueantes)

Estas tareas se pueden ir haciendo en paralelo al desarrollo, sin bloquear sprints:

| Tarea | Responsable | Cuándo empezar | Deadline |
|-------|-------------|----------------|----------|
| Verificar WhatsApp Business API con Meta (Automatizatech) | Luis Miguel | Semana 2 | Semana 12 (antes de Sprint 6) |
| Preparar contenido de Chachita: bio, foto profesional, descripción de cada servicio | Chachita | Semana 3 | Semana 6 (antes de Sprint 2) |
| Sesión de fotos/video de Chachita para landing y redes | Chachita | Semana 4 | Semana 6 |
| Crear cuentas de redes sociales de TarotEstrellas (Instagram, TikTok) | Chachita/Luis Miguel | Semana 6 | Semana 16 |
| Preparar contenido de marketing pre-lanzamiento | Chachita | Semana 12 | Semana 17 |
| Cuenta Apple Developer ($99 USD/año) para Fase 2 | Luis Miguel | Semana 16 | Semana 20 |
| Consulta con abogado para revisión de documentos legales | Luis Miguel | Semana 14 | Semana 17 |

---

## 9. Configuración de Servicios Externos

### 9.1 Stripe (pagos internacionales)

**Crear cuenta:** https://dashboard.stripe.com/register
- Registrar con email de Automatizatech
- País de la cuenta: Chile
- Tipo de negocio: Individual o Empresa
- Categoría: "Servicios profesionales" o "Entretenimiento y recreación"
- Completar verificación KYC (RUT de Chachita, cédula de identidad, cuenta bancaria chilena)
- Tiempo de verificación: 1-3 días hábiles

**Configuración inicial:**
- Dashboard → Developers → API keys: copiar `pk_live_...` y `sk_live_...`
- Guardar en `.env` del VPS como `STRIPE_KEY` y `STRIPE_SECRET`
- Dashboard → Developers → Webhooks → Add endpoint:
  - URL: `https://tarotestrellas.com/api/webhooks/stripe`
  - Eventos a escuchar: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `charge.dispute.created`
  - Copiar webhook signing secret → guardar como `STRIPE_WEBHOOK_SECRET` en `.env`

**Modo test:** usar claves `pk_test_...` y `sk_test_...` durante desarrollo. Tarjeta de prueba: `4242 4242 4242 4242`, cualquier fecha futura, cualquier CVC.

**Variables de entorno:**
```
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### 9.2 Daily.co (videollamadas + grabación)

**Crear cuenta:** https://dashboard.daily.co/signup
- Plan: Free (10,000 minutos-participante/mes)
- Dashboard → Developers → API key: copiar

**Configuración de grabación a R2:**
- Dashboard → Recordings → Configure → Custom S3-compatible storage
- Endpoint: `https://{account_id}.r2.cloudflarestorage.com`
- Bucket: `tarotestrellas-media`
- Access Key ID y Secret Key: generados en Cloudflare R2 (ver sección 9.4)
- Path prefix: `videos/`

**Configuración de webhooks:**
- Dashboard → Developers → Webhooks → Add webhook
- URL: `https://tarotestrellas.com/api/webhooks/daily`
- Eventos: `meeting.started`, `meeting.ended`, `recording.started`, `recording.ready`, `recording.error`
- Copiar HMAC secret → guardar como `DAILY_WEBHOOK_SECRET`

**Variables de entorno:**
```
DAILY_API_KEY=...
DAILY_WEBHOOK_SECRET=...
DAILY_DOMAIN=tarotestrellas.daily.co
```

### 9.3 Cloudflare (proxy + DNS)

**Crear cuenta:** https://dash.cloudflare.com/sign-up

**Agregar dominio:**
- Add site → `tarotestrellas.com`
- Plan: Free
- Cloudflare asigna nameservers (ej: `ada.ns.cloudflare.com`, `lee.ns.cloudflare.com`)
- Ir al registrador del dominio (Namecheap/Hostinger) → cambiar nameservers a los de Cloudflare
- Esperar propagación DNS (hasta 24h, típicamente 1-2h)

**Configurar DNS:**
- Registro A: `tarotestrellas.com` → IP del VPS KVM 2, proxy ON (naranja)
- Registro A: `www` → IP del VPS, proxy ON
- Page Rule: `www.tarotestrellas.com/*` → 301 redirect a `https://tarotestrellas.com/$1`

**Configuración SSL:**
- SSL/TLS → Full (Strict) — requiere certificado válido en el VPS (Let's Encrypt)
- Edge Certificates → Always Use HTTPS: ON
- Edge Certificates → Minimum TLS Version: 1.2
- Edge Certificates → Automatic HTTPS Rewrites: ON

**Configuración de seguridad:**
- Security → WAF: activar reglas gestionadas (gratis)
- Security → Bot Fight Mode: ON
- Security → Rate Limiting: crear regla para `/api/auth/*` (máx 10 req/min por IP)

**Configuración de performance:**
- Speed → Auto Minify: JS + CSS + HTML
- Caching → Caching Level: Standard
- Caching → Browser Cache TTL: 1 year (assets estáticos con hash)

### 9.4 Cloudflare R2 (object storage)

**Activar R2:**
- Dashboard → R2 → Get started (requiere método de pago aunque el tier gratuito es generoso: 10 GB + sin costo de egreso)

**Crear buckets:**
- Bucket 1: `tarotestrellas-media` (videos, audios, avatares, recibos)
- Bucket 2: `tarotestrellas-backups` (backups de BD)

**Crear API tokens para acceso programático:**
- R2 → Manage R2 API Tokens → Create API token
- Permisos: Object Read & Write
- Especificar buckets: ambos
- Copiar Access Key ID y Secret Access Key

**Variables de entorno:**
```
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_ENDPOINT=https://{account_id}.r2.cloudflarestorage.com
R2_BUCKET_MEDIA=tarotestrellas-media
R2_BUCKET_BACKUPS=tarotestrellas-backups
R2_PUBLIC_URL=https://media.tarotestrellas.com
```

**Dominio personalizado para R2 (opcional pero recomendado):**
- En Cloudflare DNS → CNAME: `media` → `{bucket}.r2.cloudflarestorage.com`
- Así las URLs de grabaciones son `https://media.tarotestrellas.com/videos/{uuid}/grabacion.mp4` en vez de URLs de R2 genéricas

### 9.5 OpenAI (Whisper + embeddings)

**Cuenta:** ya la tienes. Verificar créditos disponibles.

**API key:**
- https://platform.openai.com/api-keys → Create new secret key
- Nombre: `tarotestrellas-production`
- Guardar como `OPENAI_API_KEY` en `.env`

**Uso en el sistema:**
- **Whisper** (transcripción): modelo `whisper-1`, ~$0.006/min
- **Embeddings**: modelo `text-embedding-3-small`, ~$0.02/1M tokens

**Variables de entorno:**
```
OPENAI_API_KEY=sk-...
OPENAI_WHISPER_MODEL=whisper-1
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

### 9.6 Anthropic (Claude — agente IA + resúmenes + OCR)

**Cuenta:** ya la tienes. Verificar créditos disponibles.

**API key:**
- https://console.anthropic.com/settings/keys → Create Key
- Nombre: `tarotestrellas-production`

**Uso en el sistema:**
- **Claude Haiku** (resúmenes post-sesión): modelo `claude-3-5-haiku-20241022`
- **Claude Sonnet** (agente IA conversacional): modelo `claude-sonnet-4-20250514`
- **Claude Sonnet con visión** (OCR de comprobantes): mismo modelo, con input de imagen

**Variables de entorno:**
```
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_HAIKU_MODEL=claude-3-5-haiku-20241022
ANTHROPIC_SONNET_MODEL=claude-sonnet-4-20250514
```

### 9.7 Resend (email transaccional)

**Crear cuenta:** https://resend.com/signup

**Verificar dominio:**
- Domains → Add domain → `tarotestrellas.com`
- Agregar registros DNS en Cloudflare: SPF (TXT), DKIM (CNAME), DMARC (TXT)
- Resend verifica automáticamente (minutos a horas)

**API key:**
- API Keys → Create API key → nombre `tarotestrellas`

**Variables de entorno:**
```
RESEND_API_KEY=re_...
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=hola@tarotestrellas.com
MAIL_FROM_NAME="TarotEstrellas"
```

### 9.8 WhatsApp Business (Meta Cloud API o YCloud)

Tienes dos opciones para enviar WhatsApp. Ambas usan la misma infraestructura de Meta por debajo, la diferencia es la capa de acceso:

**Opción A — Meta WhatsApp Cloud API (directo, sin intermediario):**

Ventajas: sin intermediario, las primeras 1,000 conversaciones service/mes son gratis, control total.
Desventaja: configuración más técnica, dashboard de Meta Business Manager puede ser confuso.

Configuración:
- Ir a https://developers.facebook.com → crear app tipo "Business"
- Agregar producto "WhatsApp" a la app
- En WhatsApp → Getting Started: obtener phone number ID y token temporal
- Verificar negocio en Meta Business Manager (usar cuenta de Automatizatech ya verificada)
- Registrar número de teléfono permanente para TarotEstrellas
- Generar System User Token permanente (no expira como el temporal)
- Crear Message Templates en WhatsApp Manager → enviar para aprobación de Meta (24-48h)

Variables de entorno:
```
WHATSAPP_PROVIDER=meta
META_WHATSAPP_TOKEN=EAAxxxxxxx...
META_WHATSAPP_PHONE_ID=1234567890
META_WHATSAPP_BUSINESS_ID=9876543210
META_WHATSAPP_VERIFY_TOKEN=mi_token_secreto_webhook
META_WHATSAPP_APP_SECRET=abc123...
```

Webhook: Meta envía eventos a tu URL configurada. Verificar con `META_WHATSAPP_APP_SECRET`.
URL webhook: `https://tarotestrellas.com/api/webhooks/whatsapp`

**Opción B — YCloud (intermediario simplificado):**

Ventajas: dashboard más limpio, SDK más simple, soporte en español, pricing transparente.
Desventaja: costo ligeramente mayor (~$0.05/msg vs ~$0.03/msg de Meta directo).

Configuración:
- Crear cuenta en https://www.ycloud.com
- Conectar tu WhatsApp Business Account de Meta (YCloud guía el proceso)
- Dashboard → API Keys → crear key
- Crear templates desde el dashboard de YCloud (los envía a Meta para aprobación)

Variables de entorno:
```
WHATSAPP_PROVIDER=ycloud
YCLOUD_API_KEY=yclk_...
YCLOUD_WHATSAPP_FROM=+56912345678
YCLOUD_WEBHOOK_SECRET=...
```

**Recomendación:** si ya tienes la cuenta de Automatizatech verificada con Meta, ir con **Opción A (Meta directo)** para ahorrar costos. Si prefieres simplicidad y soporte, ir con **Opción B (YCloud)**. El código del backend usa una capa de abstracción `WhatsAppService` que permite cambiar entre ambos proveedores editando solo la variable `WHATSAPP_PROVIDER` en `.env`.

### 9.9 Sentry (monitoreo de errores)

**Crear cuenta:** https://sentry.io/signup/ (plan Developer, gratis)

**Crear proyectos:**
- Proyecto 1: `tarotestrellas-backend` (plataforma: Laravel)
- Proyecto 2: `tarotestrellas-frontend` (plataforma: React)

**Configurar alertas:**
- Alerts → Create Alert → "When a new issue is created" → Send email to you
- Threshold: alert if >5 events in 1 hour (para no saturar)

**Variables de entorno:**
```
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/yyy
SENTRY_REACT_DSN=https://xxx@sentry.io/zzz
```

### 9.10 Resumen de todas las variables de entorno

```env
# === APP ===
APP_NAME=TarotEstrellas
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://tarotestrellas.com

# === BASE DE DATOS ===
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tarotestrellas
DB_USERNAME=tarotestrellas_user
DB_PASSWORD=...

# === REDIS ===
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# === STRIPE ===
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# === DAILY.CO ===
DAILY_API_KEY=...
DAILY_WEBHOOK_SECRET=...

# === CLOUDFLARE R2 ===
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_ENDPOINT=https://xxx.r2.cloudflarestorage.com
R2_BUCKET_MEDIA=tarotestrellas-media
R2_BUCKET_BACKUPS=tarotestrellas-backups

# === OPENAI ===
OPENAI_API_KEY=sk-...

# === ANTHROPIC ===
ANTHROPIC_API_KEY=sk-ant-...

# === EMAIL (RESEND) ===
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=hola@tarotestrellas.com
MAIL_FROM_NAME="TarotEstrellas"

# === WHATSAPP (Meta Cloud API o YCloud) ===
WHATSAPP_PROVIDER=meta
# Si Meta directo:
META_WHATSAPP_TOKEN=EAAxxxxxxx...
META_WHATSAPP_PHONE_ID=1234567890
META_WHATSAPP_BUSINESS_ID=9876543210
META_WHATSAPP_APP_SECRET=abc123...
# Si YCloud:
# YCLOUD_API_KEY=yclk_...
# YCLOUD_WHATSAPP_FROM=+56912345678

# === SENTRY ===
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/yyy

# === AUTH SOCIAL ===
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://tarotestrellas.com/api/auth/social/google/callback
```

---

## 10. Documentos Legales

### 10.1 Nota importante

Los documentos a continuación son **borradores profesionales** diseñados para cumplir con GDPR, ley chilena de protección de datos (19.628 y 21.719), y mejores prácticas del rubro. Sin embargo, **se recomienda encarecidamente que un abogado especializado en tecnología y protección de datos los revise** antes de publicarlos, especialmente considerando:
- Clientes en múltiples jurisdicciones (Chile, USA, España, México, Venezuela)
- Rubro esotérico que requiere disclaimers específicos
- Manejo de datos biométricos (video) y datos sensibles (datos natales, grabaciones de sesiones íntimas)

### 10.2 Términos y Condiciones

**Documento completo a publicar en:** `tarotestrellas.com/legal/terminos`

---

**TÉRMINOS Y CONDICIONES DE USO — TAROTESTRELLAS**

Última actualización: [FECHA DE LANZAMIENTO]

Bienvenido/a a TarotEstrellas ("la Plataforma"), operada por Automatizatech ("nosotros", "nuestro"). Al registrarte y usar nuestros servicios, aceptas estos Términos y Condiciones ("Términos"). Si no estás de acuerdo, no utilices la Plataforma.

**1. Descripción del servicio.** TarotEstrellas es una plataforma digital que facilita la conexión entre clientes y especialistas en tarot, astrología, carta astral y prácticas afines, a través de consultas por videollamada en tiempo real. La Plataforma proporciona herramientas de agendamiento, pago, grabación, transcripción e historial de consultas.

**2. Requisitos de uso.** Debes ser mayor de 18 años para usar la Plataforma. Al registrarte, declaras que cumples este requisito. Debes proporcionar información veraz y mantener tus credenciales de acceso seguras. Eres responsable de toda actividad bajo tu cuenta.

**3. Naturaleza de las consultas.** Las consultas ofrecidas a través de TarotEstrellas son servicios de entretenimiento, guía personal y reflexión espiritual. NO constituyen asesoría médica, psicológica, legal, financiera ni profesional de ningún tipo. Las interpretaciones y orientaciones proporcionadas por los especialistas son de carácter subjetivo y personal. Cualquier decisión que tomes basándote en una consulta es tu responsabilidad exclusiva. Si experimentas problemas de salud física o mental, te instamos a consultar con un profesional de la salud certificado.

**4. Agendamiento y pagos.** Al agendar una consulta, aceptas pagar el precio indicado. El abono del 20% se cobra al reservar. El saldo del 80% debe pagarse antes de ingresar a la videollamada. Los precios se muestran en la moneda de tu perfil y pueden estar sujetos a conversión cambiaria. Los pagos con tarjeta se procesan a través de Stripe, un procesador de pagos certificado PCI-DSS. La Plataforma no almacena datos de tarjetas de crédito.

**5. Cancelaciones y reembolsos.** Puedes cancelar con más de 24 horas de anticipación y recibir reembolso completo del abono (máximo 1 vez). Cancelaciones con menos de 24 horas: el abono no es reembolsable. No-show (no presentarse): todo lo pagado se pierde. Puedes reagendar 1 vez sin costo adicional. Detalles completos en nuestra Política de Reembolsos.

**6. Grabación y transcripción.** Todas las sesiones se graban y transcriben automáticamente. Al ingresar a una sesión, confirmas tu consentimiento explícito para la grabación. Las grabaciones se conservan por 3 meses. Las transcripciones se conservan indefinidamente mientras mantengas tu cuenta activa. Puedes solicitar la eliminación de tus datos en cualquier momento.

**7. Propiedad intelectual.** La Plataforma, su diseño, código, marca y contenido son propiedad de Automatizatech. Las consultas grabadas son propiedad compartida entre el cliente y el especialista. El cliente puede usar sus grabaciones y transcripciones para uso personal, no comercial.

**8. Conducta del usuario.** Te comprometes a no usar la Plataforma para fines ilegales, acosar al especialista o a otros usuarios, grabar la sesión por medios externos sin consentimiento, compartir públicamente grabaciones o transcripciones sin autorización, ni intentar vulnerar la seguridad del sistema.

**9. Limitación de responsabilidad.** TarotEstrellas y sus especialistas no son responsables por decisiones que tomes basándote en las consultas, resultados inesperados o insatisfactorios de una lectura, interrupciones técnicas fuera de nuestro control, ni por pérdidas económicas derivadas del uso de la Plataforma. Nuestra responsabilidad máxima se limita al monto pagado por la consulta en cuestión.

**10. Privacidad.** El manejo de tus datos personales se rige por nuestra Política de Privacidad, que forma parte integral de estos Términos.

**11. Modificaciones.** Podemos modificar estos Términos en cualquier momento. Te notificaremos por email sobre cambios sustanciales con al menos 15 días de anticipación. El uso continuado de la Plataforma después de la notificación implica aceptación.

**12. Ley aplicable.** Estos Términos se rigen por las leyes de la República de Chile. Para disputas, se someten a los tribunales ordinarios de Santiago, Chile, sin perjuicio de los derechos que te otorgue la legislación de tu país de residencia.

**13. Contacto.** Para consultas sobre estos Términos: legal@tarotestrellas.com.

---

### 10.3 Política de Privacidad

**Documento completo a publicar en:** `tarotestrellas.com/legal/privacidad`

---

**POLÍTICA DE PRIVACIDAD — TAROTESTRELLAS**

Última actualización: [FECHA DE LANZAMIENTO]

Esta Política de Privacidad describe cómo Automatizatech ("nosotros") recopila, usa, almacena y protege tus datos personales cuando usas TarotEstrellas ("la Plataforma").

Cumplimos con el Reglamento General de Protección de Datos de la Unión Europea (GDPR), la Ley 19.628 de Protección de la Vida Privada de Chile, la Ley 21.719 sobre Datos Personales de Chile, y las normativas aplicables en los países de nuestros usuarios.

**1. Datos que recopilamos.**

Datos de registro: nombre, email, teléfono, país de residencia, zona horaria. Datos natales (para consultas astrológicas): fecha, hora y lugar de nacimiento (almacenados cifrados). Datos de pago: procesados por Stripe, nunca almacenamos datos de tarjeta. Datos de uso: páginas visitadas, acciones realizadas, dispositivo, navegador. Grabaciones de video: sesiones de consulta grabadas con tu consentimiento. Transcripciones: texto generado automáticamente de las sesiones. Datos del agente IA: conversaciones con el asistente inteligente.

**2. Base legal del tratamiento.** Consentimiento explícito (registro, grabación, transcripción). Ejecución del contrato (procesamiento de pagos, prestación del servicio). Interés legítimo (seguridad, prevención de fraude, mejora del servicio). Obligación legal (conservación de datos fiscales).

**3. Cómo usamos tus datos.** Para prestar el servicio contratado (consultas, agendamiento, pagos). Para generar transcripciones y resúmenes de tus consultas. Para alimentar el agente IA con tu historial (solo con tu consentimiento). Para enviarte notificaciones transaccionales y recordatorios. Para comunicaciones de marketing (solo con tu consentimiento, revocable en cualquier momento). Para mejorar la Plataforma y analizar uso agregado (anonimizado).

**4. Compartimos tus datos con:** Stripe (procesamiento de pagos). Daily.co (videollamadas). OpenAI (transcripción de audio). Anthropic (resúmenes IA y agente conversacional). Resend (envío de emails). Meta WhatsApp Cloud API o YCloud (envío de WhatsApp). Cloudflare (CDN, proxy, almacenamiento). Sentry (monitoreo de errores, datos anonimizados). Ninguno de estos proveedores usa tus datos para fines propios. Todos cumplen con GDPR o equivalentes.

**5. Retención de datos.** Datos de perfil: mientras mantengas la cuenta activa. Grabaciones de video: 3 meses desde la sesión. Transcripciones: indefinidamente mientras la cuenta esté activa. Datos de facturación: 7 años (obligación tributaria chilena). Logs de auditoría: 2 años. Tras eliminar tu cuenta, los datos se anonimizan o eliminan según lo anterior.

**6. Tus derechos.** Acceso: puedes solicitar una copia de todos tus datos. Rectificación: puedes corregir datos inexactos. Eliminación: puedes solicitar que eliminemos tus datos (derecho al olvido). Portabilidad: puedes exportar tus datos en formato JSON. Oposición: puedes oponerte al tratamiento para marketing. Revocación de consentimiento: puedes revocar consentimientos otorgados en cualquier momento. Todos estos derechos se ejercen desde tu perfil (autoservicio) o contactando a privacidad@tarotestrellas.com.

**7. Seguridad.** Cifrado en tránsito (TLS 1.3). Cifrado en reposo para datos sensibles (datos natales, tokens). Contraseñas hasheadas con bcrypt. Autenticación de dos factores disponible. Backups diarios cifrados. Acceso restringido al equipo técnico con auditoría.

**8. Transferencias internacionales.** Tus datos pueden ser procesados en servidores ubicados fuera de tu país (Chile, Estados Unidos, Unión Europea). Todos los proveedores cumplen con estándares de protección equivalentes al GDPR.

**9. Menores de edad.** La Plataforma no está dirigida a menores de 18 años. No recopilamos datos de menores conscientemente.

**10. Cookies.** Ver nuestra Política de Cookies para detalles sobre el uso de cookies y tecnologías similares.

**11. Modificaciones.** Te notificaremos sobre cambios materiales con 15 días de anticipación por email.

**12. Contacto.** Delegado de protección de datos: privacidad@tarotestrellas.com.

---

### 10.4 Política de Cookies

**Documento completo a publicar en:** `tarotestrellas.com/legal/cookies`

---

**POLÍTICA DE COOKIES — TAROTESTRELLAS**

**¿Qué son las cookies?** Las cookies son pequeños archivos de texto que los sitios web almacenan en tu navegador.

**Cookies que utilizamos:**

Cookies esenciales (siempre activas): sesión de usuario (autenticación), preferencia de tema oscuro/claro, token CSRF (seguridad), preferencia de zona horaria.

Cookies analíticas (con consentimiento): Umami analytics (sin cookies en realidad, Umami es cookieless; usamos tracking basado en eventos sin identificadores personales).

Cookies de terceros: Stripe (necesarias para procesar pagos, gestionadas por Stripe). Cloudflare (seguridad y rendimiento, gestionadas por Cloudflare).

**No utilizamos:** cookies de publicidad, cookies de tracking cross-site, cookies de redes sociales, ni compartimos datos de cookies con terceros para fines publicitarios.

**Gestión de cookies:** Puedes configurar tu navegador para rechazar cookies no esenciales. Las cookies esenciales son necesarias para el funcionamiento de la Plataforma y no se pueden desactivar.

---

### 10.5 Política de Reembolsos y Cancelaciones

**Documento completo a publicar en:** `tarotestrellas.com/legal/reembolsos`

---

**POLÍTICA DE REEMBOLSOS Y CANCELACIONES — TAROTESTRELLAS**

**Abono y reserva.** Al agendar una consulta, pagas el 20% como abono de reserva. Este abono garantiza tu horario. El 80% restante debe pagarse antes de la sesión.

**Cancelación con más de 24 horas de anticipación.** Recibes reembolso del 100% del abono. Disponible máximo 1 vez por cliente. El reembolso se procesa en 5-10 días hábiles al mismo medio de pago original.

**Cancelación con menos de 24 horas.** El abono del 20% no es reembolsable, ya que el horario quedó bloqueado y la especialista se preparó para la sesión.

**No-show (no presentarse).** Si no te presentas a la sesión sin aviso previo, pierdes el total pagado (abono y saldo si ya fue cobrado).

**Reagendamiento.** Puedes reagendar 1 vez sin costo adicional, manteniendo tu abono. A partir del segundo reagendamiento, el abono se pierde y debes pagar uno nuevo.

**Cancelación por parte de la especialista.** Si la especialista cancela, recibes reembolso del 100% de todo lo pagado, sin excepciones. También puedes elegir reagendar sin costo a otra fecha.

**Problemas técnicos.** Si la sesión se ve gravemente afectada por problemas técnicos de la Plataforma (no atribuibles a tu conexión), puedes solicitar reagendamiento gratuito o reembolso contactando a soporte@tarotestrellas.com.

**Membresías.** Las membresías no son reembolsables una vez activadas. Las consultas no utilizadas dentro del período de vigencia se pierden al expirar la membresía.

---

### 10.6 Disclaimer sobre consultas esotéricas

**Incluido en:** footer de todas las páginas, modal antes del registro, y dentro de los Términos y Condiciones.

---

**AVISO LEGAL SOBRE LAS CONSULTAS**

Las consultas de tarot, astrología, carta astral, cartas españolas, numerología, runas, péndulo, lectura de café, quiromancia, limpieza energética y demás servicios ofrecidos a través de TarotEstrellas son de carácter estrictamente personal, espiritual y de entretenimiento.

Estas consultas NO constituyen, ni pretenden sustituir, asesoría o tratamiento médico, psicológico, psiquiátrico, legal, financiero ni profesional de ninguna índole. Las interpretaciones ofrecidas por los especialistas son subjetivas y basadas en tradiciones esotéricas que no cuentan con respaldo científico probado.

Cualquier decisión que tomes basándote en una consulta es tu entera responsabilidad. Si estás experimentando una crisis de salud mental, pensamientos de autolesión, o cualquier emergencia, por favor contacta inmediatamente a los servicios de emergencia de tu país o a una línea de ayuda profesional.

Al usar TarotEstrellas, reconoces y aceptas que los resultados de las consultas no están garantizados, que las lecturas son interpretaciones personales del especialista y no predicciones factuales del futuro, y que asumes la responsabilidad de tus decisiones.

---

### 10.7 Consentimiento de grabación y transcripción

**Mostrado como:** modal obligatorio con checkbox antes de entrar a cada videollamada.

---

**CONSENTIMIENTO DE GRABACIÓN Y TRANSCRIPCIÓN**

Antes de iniciar tu sesión, necesitamos tu consentimiento explícito:

Esta videollamada será grabada en audio y video. La grabación se almacenará de forma segura y cifrada durante 3 meses, después de los cuales se eliminará automáticamente. Recibirás un aviso 7 días antes de la eliminación para que puedas descargarla si lo deseas.

El audio de la sesión será transcrito automáticamente a texto mediante inteligencia artificial. La transcripción se conservará indefinidamente mientras mantengas tu cuenta activa, para alimentar tu historial personal y permitirte consultar un asistente inteligente sobre tus sesiones pasadas.

Solo tú y tu especialista tienen acceso a la grabación y transcripción de esta sesión. Nadie más puede verla, y no se comparte con terceros excepto los proveedores tecnológicos necesarios para el procesamiento (ver Política de Privacidad).

Puedes solicitar la eliminación de cualquier grabación o transcripción en cualquier momento desde tu perfil, en la sección "Privacidad".

Al marcar la casilla y entrar a la sala, confirmas que has leído y aceptas este consentimiento.

[ ] Confirmo que acepto la grabación y transcripción de esta sesión.

---

## 11. Checklist de Lanzamiento

### 11.1 Pre-lanzamiento (1 semana antes)

**Infraestructura:**
- [ ] VPS KVM 2 estable, sin errores en logs del último día
- [ ] Nginx configurado correctamente con SSL (verificar con https://www.ssllabs.com/ssltest/)
- [ ] Cloudflare proxy activado, DNS propagado
- [ ] Redis corriendo como servicio
- [ ] Supervisor corriendo con workers de Horizon activos
- [ ] Cron de Laravel (`schedule:run`) activo cada minuto
- [ ] Backup automático diario ejecutándose (verificar que el último se subió a R2)
- [ ] Restaurar un backup de prueba para verificar integridad

**Servicios externos:**
- [ ] Stripe en modo LIVE (no test) con API keys de producción
- [ ] Stripe webhook configurado con URL de producción y eventos correctos
- [ ] Daily.co con grabación configurada apuntando a R2 de producción
- [ ] Daily.co webhook configurado con URL de producción
- [ ] Cloudflare R2 con buckets de producción creados y accesibles
- [ ] Resend con dominio `tarotestrellas.com` verificado (SPF + DKIM + DMARC)
- [ ] Meta WhatsApp Cloud API o YCloud con número activo y al menos 5 templates aprobados por Meta
- [ ] OpenAI con créditos suficientes (mínimo $20 USD)
- [ ] Anthropic con créditos suficientes (mínimo $20 USD)
- [ ] Sentry proyectos creados y capturando errores (enviar error de prueba)
- [ ] Umami instalado y trackeando visitas
- [ ] Google OAuth configurado con redirect URI de producción

**Datos y configuración:**
- [ ] Usuario admin de Chachita creado con 2FA activado
- [ ] Horario base configurado (Lun-Vie, Sáb)
- [ ] Feriados chilenos 2026 cargados
- [ ] 12 tipos de consulta activos con precios reales en CLP y USD
- [ ] Datos bancarios de Chachita cargados para transferencias
- [ ] Membresía 12x2 creada y activa
- [ ] Plantillas de notificación revisadas y personalizadas
- [ ] Parámetros de sistema verificados (30 min ventana, 20% abono, etc.)
- [ ] Descuento primera consulta 10% activado

**Contenido:**
- [ ] Bio de Chachita escrita y publicada en landing
- [ ] Foto profesional de Chachita subida
- [ ] Descripciones de los 12 servicios escritas por Chachita
- [ ] FAQs completadas
- [ ] Sección "Cómo funciona" con contenido final

**Legal:**
- [ ] Términos y Condiciones publicados en `/legal/terminos`
- [ ] Política de Privacidad publicada en `/legal/privacidad`
- [ ] Política de Cookies publicada en `/legal/cookies`
- [ ] Política de Reembolsos publicada en `/legal/reembolsos`
- [ ] Disclaimer esotérico visible en footer y en registro
- [ ] Consentimiento de grabación funcional en sala de video
- [ ] Revisión por abogado completada (o agendada)

### 11.2 Pruebas finales (3 días antes)

**Test end-to-end completo:**
- [ ] Registrar usuario nuevo con email
- [ ] Verificar email
- [ ] Completar perfil con datos natales
- [ ] Navegar catálogo, ver detalle de consulta
- [ ] Agendar cita con Stripe (tarjeta de prueba en modo test, luego 1 transacción real de $1 USD en modo live)
- [ ] Verificar que se recibe email + WhatsApp de confirmación
- [ ] Verificar que cita aparece en calendario de Chachita
- [ ] Pagar 80% restante
- [ ] Entrar a sala de video, aceptar consentimiento
- [ ] Hacer videollamada de 5 minutos (con grabación)
- [ ] Verificar que grabación aparece en R2
- [ ] Esperar a que pipeline de transcripción complete
- [ ] Verificar transcripción y resumen IA en historial
- [ ] Descargar recibo PDF
- [ ] Hacer reembolso del $1 USD de prueba

**Tests específicos:**
- [ ] Login con Google funciona
- [ ] Recuperación de contraseña funciona (email llega)
- [ ] Modo oscuro/claro funciona y persiste
- [ ] Responsive: probar en iPhone SE, Galaxy A54, iPad, desktop
- [ ] Velocidad: Lighthouse score >80 en Performance y Accessibility
- [ ] Cupón de prueba funciona
- [ ] Transferencia bancaria: subir comprobante de prueba, verificar OCR
- [ ] Cancelación de cita funciona con reembolso
- [ ] Reagendamiento funciona

### 11.3 Día del lanzamiento

**Mañana (antes de anunciar):**
- [ ] Verificar que todo sigue corriendo (health check, Sentry limpio, workers activos)
- [ ] Cambiar Stripe a modo live si no se hizo antes
- [ ] Eliminar datos de prueba de la BD de producción
- [ ] Tomar screenshot del estado limpio para referencia

**Anuncio:**
- [ ] Chachita publica en Instagram con link
- [ ] Chachita publica en TikTok con link
- [ ] Enviar WhatsApp a clientes existentes de Chachita (lista personal)
- [ ] Activar cupón de lanzamiento (ej: `ESTRELLAS2026`)

**Primeras 48 horas:**
- [ ] Monitorear Sentry cada 2-3 horas
- [ ] Revisar logs de Nginx y Laravel
- [ ] Estar disponible por WhatsApp para soporte a clientes
- [ ] Verificar que la primera cita real de un cliente externo funciona completamente
- [ ] Verificar que el primer pago real se deposita en la cuenta de Chachita
- [ ] Celebrar 🎉

### 11.4 Post-lanzamiento (primera semana)

- [ ] Revisar métricas de Umami: visitantes, páginas más vistas, dispositivos
- [ ] Revisar tasa de registro vs visitas
- [ ] Revisar tasa de agendamiento vs registros
- [ ] Corregir bugs reportados por usuarios reales
- [ ] Recoger feedback de Chachita sobre el panel admin
- [ ] Ajustar horarios/precios si Chachita lo solicita
- [ ] Enviar resumen de primera semana a Chachita (ingresos, clientes, consultas)

---

## Anexos

### Anexo A — Glosario técnico

| Término | Significado |
|---------|-------------|
| API | Application Programming Interface — interfaz para comunicar frontend con backend |
| Bearer token | Credencial de autenticación enviada en cada request HTTP |
| Bucket | Contenedor de archivos en almacenamiento cloud (R2) |
| Cache | Almacenamiento temporal para acelerar consultas frecuentes |
| CI/CD | Continuous Integration / Continuous Deployment — despliegue automático |
| CORS | Cross-Origin Resource Sharing — permisos de comunicación entre dominios |
| CSRF | Cross-Site Request Forgery — ataque que se previene con tokens |
| DST | Daylight Saving Time — cambio de hora estacional |
| Embedding | Vector numérico que representa el significado semántico de un texto |
| GDPR | General Data Protection Regulation — ley europea de privacidad |
| HMAC | Hash-based Message Authentication Code — verificación de firma de webhooks |
| IANA | Internet Assigned Numbers Authority — define zonas horarias estándar |
| Job | Tarea que se ejecuta en segundo plano (cola asíncrona) |
| JWT | JSON Web Token — formato de token de autenticación |
| KYC | Know Your Customer — verificación de identidad (Stripe) |
| OCR | Optical Character Recognition — lectura de texto en imágenes |
| PCI-DSS | Payment Card Industry Data Security Standard — estándar de seguridad de pagos |
| Presigned URL | URL temporal y firmada para acceder a archivos privados en R2 |
| Rate limiting | Límite de requests por tiempo para prevenir abuso |
| Seed | Datos iniciales que se cargan al instalar el sistema |
| Soft delete | Marcar como eliminado sin borrar físicamente de la base de datos |
| SPA | Single Page Application — app web que carga una sola vez |
| SSE | Server-Side Encryption — cifrado automático en almacenamiento |
| TOTP | Time-based One-Time Password — códigos de autenticación 2FA |
| UTC | Coordinated Universal Time — hora estándar mundial |
| UUID | Universally Unique Identifier — identificador no adivinable |
| VPS | Virtual Private Server — servidor virtual dedicado |
| Webhook | Notificación HTTP que un servicio envía a otro cuando ocurre un evento |
| Worker | Proceso que ejecuta tareas de la cola en segundo plano |

### Anexo B — Contactos y recursos

| Recurso | URL |
|---------|-----|
| Repositorio GitHub | https://github.com/automatizatech/tarotestrellas |
| Hostinger Panel | https://hpanel.hostinger.com |
| Stripe Dashboard | https://dashboard.stripe.com |
| Daily.co Dashboard | https://dashboard.daily.co |
| Cloudflare Dashboard | https://dash.cloudflare.com |
| Resend Dashboard | https://resend.com/emails |
| WhatsApp Cloud API / YCloud Dashboard | https://business.facebook.com/latest/whatsapp_manager / https://www.ycloud.com |
| Sentry Dashboard | https://sentry.io |
| OpenAI Platform | https://platform.openai.com |
| Anthropic Console | https://console.anthropic.com |
| Documentación Laravel | https://laravel.com/docs |
| Documentación Filament | https://filamentphp.com/docs |
| Documentación React | https://react.dev |
| Documentación Tailwind | https://tailwindcss.com/docs |
| Documentación Daily.co | https://docs.daily.co |
| Documentación Stripe | https://stripe.com/docs |

---

**FIN DEL DOCUMENTO**

Versión: 1.0
Fecha: 16 de abril de 2026
Autor: Claude AI + Luis Miguel (Automatizatech)
Proyecto: TarotEstrellas
Dominio: tarotestrellas.com
Estado: Especificación técnica completa, lista para implementación.
