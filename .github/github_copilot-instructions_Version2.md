# Instrucciones para GitHub Copilot — TarotEstrellas

## Stack
- Backend: Laravel 11, PHP 8.3, MariaDB 10.11, Redis 7
- Frontend: React 18 + TypeScript + Vite + Tailwind CSS
- Pagos: Stripe (Laravel Cashier)
- Video: Daily.co
- IA: OpenAI Whisper + Anthropic Claude
- Storage: Cloudflare R2
- Email: Resend
- WhatsApp: Meta Cloud API o YCloud

## Convenciones
- Tablas en plural snake_case
- UUIDs en entidades públicas (citas, pagos, grabaciones)
- Enteros en campos monetarios (centavos)
- Fechas siempre en UTC en BD
- Soft deletes donde la especificación lo indica
- Cifrar campos sensibles con Laravel Encrypted Casts

## Referencia principal
Toda la arquitectura, modelo de datos y lógica de negocio está en:
docs/ESPECIFICACION_TECNICATarotEstrellas V2.md