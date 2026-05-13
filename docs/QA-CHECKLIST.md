# 🧪 Checklist de QA — TarotEstrellas

Documento exhaustivo para validar **todas las funcionalidades** del sistema en cada release.
Ejecutar en orden. Marcar `[x]` lo OK y anotar issue al lado de lo que falle.

**Entornos:**
- 🟢 Local: `http://localhost:5173` (frontend Vite) + `http://tarotestrellas.test` (backend WAMP)
- 🔵 PROD: `https://tarotestrellas.com`

**Browsers a cubrir:** Chrome desktop, Firefox desktop, Safari iOS, Chrome Android.
**Resoluciones:** 1920×1080, 1366×768, 768×1024 (tablet), 375×667 (mobile).

---

## 1. Acceso público (sin login)

### 1.1 Landing
- [ ] Carga sin errores en consola
- [ ] Hero, secciones, CTA visibles
- [ ] Animaciones (StarField, webgl) funcionan en desktop
- [ ] Responsive: móvil 375px sin scroll horizontal
- [ ] Click en "Reservar consulta" → `/servicios`
- [ ] Click en "Iniciar sesión" → `/login`

### 1.2 Servicios públicos `/servicios`
- [ ] Lista todos los tipos de consulta
- [ ] Filtro por categoría (Tarot/Astrología/Rituales)
- [ ] Card con precio en moneda correcta según país detectado
- [ ] Click → `/servicios/{slug}`

### 1.3 Detalle servicio
- [ ] Calendario de disponibilidad carga horarios
- [ ] Selección de fecha → muestra slots disponibles
- [ ] Click "Reservar" sin login → redirige a `/login` con `redirect=`
- [ ] Click "Reservar" logueado → flujo de pago

### 1.4 Chatbot Astrea no logueado
- [ ] Widget flotante visible
- [ ] Mensaje inicial sin `**` literales (negritas reales)
- [ ] Footer NO muestra modelo/tokens (solo en admin)
- [ ] Pregunta libre → respuesta IA correcta
- [ ] Sin error 401/500

### 1.5 Páginas legales
- [ ] `/legal/terminos`, `/legal/privacidad`, `/legal/cookies` cargan
- [ ] Links footer funcionan

---

## 2. Registro y login

### 2.1 Registro `/registro`
- [ ] Formulario muestra todos los campos requeridos
- [ ] Validación inline: email, password ≥ 8, password match
- [ ] Checkboxes T&C, Privacidad, Mayor de edad obligatorios
- [ ] Submit → user creado + email de verificación enviado
- [ ] Sin error "consent_privacidad field is required"
- [ ] Sin error "Server Error 500"

### 2.2 Verificación email
- [ ] Email llega a la bandeja del cliente
- [ ] Link en email → marca `email_verified_at`
- [ ] Acceso a app después de verificar

### 2.3 Login `/login`
- [ ] Login con email + password OK
- [ ] Login con cuenta NO verificada → mensaje claro
- [ ] Login con credenciales inválidas → error
- [ ] Toggle "mostrar password"
- [ ] Link "Olvidé contraseña" → `/forgot-password`

### 2.4 Login Google
- [ ] Botón "Continuar con Google" visible
- [ ] OAuth callback funciona
- [ ] Si es primera vez → redirige a completar perfil

### 2.5 Reset password
- [ ] `/forgot-password` envía email con link
- [ ] Link en email → `/reset-password?token=...`
- [ ] Reset cambia password correctamente
- [ ] Login con nueva password OK

### 2.6 Completar perfil
- [ ] Tras registro/Google: `/completar-perfil` aparece
- [ ] Validación: todos los campos obligatorios
- [ ] Datos natales (fecha, hora, lugar) opcional pero recomendado
- [ ] Submit → redirige a dashboard

---

## 3. Cliente · zona privada `/app`

### 3.1 Dashboard `/app`
- [ ] Saludo personalizado con nombre
- [ ] Próxima cita (si existe) con CTA "Entrar a sala"
- [ ] Resumen IA última consulta (markdown renderizado, sin `**`)
- [ ] Cards: total consultas, créditos, membresía
- [ ] Astrea widget abre/cierra

### 3.2 Mis consultas `/app/mis-consultas`
- [ ] Tabs: Próximas / Pasadas / Canceladas
- [ ] Click en cita → `/app/citas/{uuid}`
- [ ] Filtro por especialista y tipo
- [ ] Botón "Reagendar" en cita confirmada futura

### 3.3 Detalle cita `/app/citas/{uuid}`
- [ ] Datos correctos (especialista, hora, precio)
- [ ] Si pagada → botón "Entrar a sala" 15min antes
- [ ] Si pendiente pago → botón "Pagar"
- [ ] Resumen IA post-cita (markdown OK)
- [ ] Grabación reproducible (si retención activa)
- [ ] Reseña (si cita realizada)

### 3.4 Sala video `/app/citas/{uuid}/sala`
- [ ] Daily.co inicia sin errores
- [ ] Cámara y micro funcionan
- [ ] Grabación se inicia automáticamente
- [ ] Salir → vuelve a detalle cita

### 3.5 Pago cita `/app/citas/{uuid}/pagar`
- [ ] Resumen orden correcto
- [ ] Selector método: Stripe / Transferencia
- [ ] Stripe → checkout funciona, callback OK
- [ ] Transferencia → muestra cuentas bancarias activas
- [ ] Subida comprobante → archivo aceptado
- [ ] Cupón aplica descuento
- [ ] Pago crédito (si tiene saldo)

### 3.6 Membresía `/app/membresia`
- [ ] Planes visibles con precio
- [ ] Suscripción Stripe funciona
- [ ] Cancelar suscripción
- [ ] Estado actual visible

### 3.7 Mi cuenta `/app/mi-cuenta`
- [ ] Tabs: Perfil, Datos natales, Notificaciones, Seguridad, Consentimientos
- [ ] Editar perfil guarda
- [ ] Editar datos natales guarda
- [ ] Cambiar password (con password actual)
- [ ] Activar 2FA
- [ ] Toggle preferencias notificación
- [ ] Revocar consentimientos

### 3.8 Asistente IA `/app/asistente`
- [ ] Chat carga historial previo
- [ ] Nueva pregunta → respuesta correcta
- [ ] Markdown renderizado (sin `**`)
- [ ] Botón "Nueva conversación"

---

## 4. Admin · Súper Admin

### 4.1 Layout admin
- [ ] Sidebar visible en desktop
- [ ] Sidebar drawer en móvil (botón ☰)
- [ ] Botón (i) muestra tooltip por módulo
- [ ] Toggle modo claro/oscuro
- [ ] Solo súper admin ve: Paquetes, Plantillas, Audit, IA Métricas, APIs Consumo, Especialistas, Reportes

### 4.2 Resumen `/app/admin`
- [ ] KPIs cargan (citas hoy, ingresos mes, etc.)
- [ ] Citas próximas (24h)
- [ ] Reseñas pendientes moderación
- [ ] Alertas operativas (pagos vencidos, no-shows)

### 4.3 Citas `/app/admin/citas`
- [ ] Tabla con todas las citas
- [ ] Filtros: estado, fecha, especialista, cliente
- [ ] Búsqueda por código o nombre
- [ ] Click → detalle cita
- [ ] Cambiar estado (pendiente → confirmada, etc.)

### 4.4 Clientes `/app/admin/clientes`
- [ ] Tabla con paginación
- [ ] Filtros: país, frecuencia, activos
- [ ] Búsqueda por nombre/email
- [ ] Click "Ver ficha" → detalle
- [ ] **Eliminar cliente** (solo súper admin):
  - [ ] Botón rojo visible en ficha
  - [ ] Confirmación 1 (eliminar)
  - [ ] Si tiene citas pagadas → confirmación 2 (cascada)
  - [ ] Tras OK → vuelve a lista, cliente desaparece
  - [ ] Email queda libre (puede registrar de nuevo)

### 4.5 Ficha cliente `/app/admin/clientes/{uuid}`
- [ ] Tabs: Resumen, Cronología, Natal, Chat, Pagos, Notas, Briefing
- [ ] Resumen: stats correctos
- [ ] Cronología: línea temporal de citas
- [ ] Natal: datos natales si los completó
- [ ] Chat: historial agente IA
- [ ] Pagos: histórico transacciones
- [ ] Notas: editar y guardar (solo admin)
- [ ] Briefing IA: genera con un click, markdown OK

### 4.6 Especialistas `/app/admin/especialistas` (súper admin)
- [ ] Lista de especialistas
- [ ] Botón "Nuevo especialista" → modal
- [ ] Crear envía email con link reset password
- [ ] Editar perfil público (bio, foto, categorías)
- [ ] Toggle activo/inactivo
- [ ] Ver agenda y citas asignadas

### 4.7 Servicios `/app/admin/servicios`
- [ ] CRUD completo
- [ ] Precio multi-moneda (CLP, USD, EUR)
- [ ] Asignar especialistas que pueden dar el servicio

### 4.8 Disponibilidad `/app/admin/disponibilidad`
- [ ] Vista semanal por especialista
- [ ] Crear horario laboral (día, hora inicio/fin)
- [ ] Bloquear día puntual
- [ ] Feriados aplicados a todos

### 4.9 Cupones `/app/admin/cupones`
- [ ] CRUD cupones
- [ ] Tipo % o monto fijo
- [ ] Límite usos, expiración
- [ ] Aplicable a servicios específicos

### 4.10 Paquetes `/app/admin/paquetes` (súper admin)
- [ ] CRUD paquetes
- [ ] Cantidad consultas, precio promo
- [ ] Vigencia días
- [ ] Asignar a cliente

### 4.11 Comprobantes `/app/admin/comprobantes`
- [ ] Lista de comprobantes pendientes
- [ ] Ver imagen/PDF
- [ ] Aprobar → cita pagada + email cliente
- [ ] Rechazar con motivo
- [ ] **Re-enviar comprobante** funciona

### 4.12 Reembolsos `/app/admin/reembolsos`
- [ ] Lista solicitudes
- [ ] Detalle con motivo cliente
- [ ] Aprobar (parcial/total) → Stripe refund + email
- [ ] Rechazar con motivo

### 4.13 Cuentas bancarias `/app/admin/cuentas-bancarias`
- [ ] CRUD cuentas
- [ ] Toggle activa/inactiva
- [ ] Solo activas se muestran al cliente

### 4.14 Reseñas `/app/admin/resenas`
- [ ] Lista por estado (pendiente/aprobada/rechazada)
- [ ] Aprobar publica
- [ ] **Responder reseña** desde admin
- [ ] Toggle visible/oculta

### 4.15 Plantillas `/app/admin/plantillas` (súper admin)
- [ ] Listado plantillas email/WhatsApp
- [ ] Editor con variables `{nombre}`, `{fecha}`
- [ ] Vista previa con valores ejemplo
- [ ] Activar/desactivar

### 4.16 Notificaciones `/app/admin/notificaciones` (súper admin)
- [ ] Bitácora envíos
- [ ] Filtro por tipo, estado, destinatario
- [ ] Detalle con payload y respuesta del servicio
- [ ] Reintento manual

### 4.17 Audit log `/app/admin/audit-log` (súper admin)
- [ ] Lista acciones sensibles
- [ ] Filtros: usuario, módulo, fecha
- [ ] Detalle con datos antes/después

### 4.18 IA Métricas `/app/admin/agente/metrics` (súper admin)
- [ ] Conversaciones totales
- [ ] Tokens consumidos / costo
- [ ] Conversiones (chat → registro)

### 4.19 APIs Consumo `/app/admin/api-usage` (súper admin)
- [ ] Consumo Anthropic, OpenAI, Daily
- [ ] Configurar tope mensual
- [ ] Email alerta al X% del tope

### 4.20 Reportes `/app/admin/reportes` (súper admin)
- [ ] Filtro fecha
- [ ] Ingresos por período
- [ ] Citas por especialista
- [ ] Exportar CSV

### 4.21 Ajustes `/app/admin/settings`
- [ ] Datos empresa (RUT, dirección, contacto)
- [ ] Branding (logo, colores)
- [ ] T&C y privacidad editable
- [ ] Integraciones (Stripe keys, Daily, Anthropic)

---

## 5. Especialista · zona admin

### 5.1 Mi agenda `/app/admin/mis-citas`
- [ ] Calendario con citas del día/semana
- [ ] Click cita → entrar a sala
- [ ] Notas privadas por cita

---

## 6. Mobile · responsive (375×667)

### 6.1 General
- [ ] Sin scroll horizontal en ninguna página
- [ ] Header se reordena correctamente
- [ ] Inputs ≥ 16px (no zoom iOS)
- [ ] Botones tap target ≥ 44px

### 6.2 Admin móvil
- [ ] Botón ☰ visible en header admin
- [ ] Drawer lateral abre con tap
- [ ] Backdrop cierra drawer al tocar
- [ ] Tablas con scroll horizontal sin romper layout
- [ ] Filtros admin se apilan verticalmente

### 6.3 Cliente móvil
- [ ] Cards 1 columna
- [ ] Sala video funciona en Safari iOS
- [ ] Astrea widget no tapa contenido
- [ ] Selector calendario usable

---

## 7. Performance y errores

- [ ] Sin errores rojos en consola en navegación normal
- [ ] Imágenes optimizadas (lazy load)
- [ ] Build sin warnings críticos (chunks > 500kB son OK por ahora)
- [ ] Tiempo carga LCP < 3s en 4G simulada
- [ ] PWA manifest válido (si aplica)

---

## 8. Smoke test E2E (release final)

Flujo completo a ejecutar de inicio a fin:

1. Cliente nuevo se registra + verifica email
2. Completa perfil con datos natales
3. Reserva cita en `/servicios`
4. Paga con Stripe (sandbox)
5. Recibe email confirmación
6. Entra a sala 5min antes
7. Especialista entra y atiende
8. Cita marcada realizada → resumen IA generado
9. Cliente ve resumen en dashboard
10. Cliente deja reseña
11. Admin aprueba reseña
12. Cliente solicita reembolso
13. Admin procesa reembolso
14. Cliente recibe email de reembolso

---

## 9. Notas de la sesión QA

```
Fecha:     ____ / ____ / ______
Tester:    ___________________
Versión:   _______ (commit _______)
Entorno:   [ ] Local   [ ] PROD
Browser:   _______ versión _______
Resol:     _________________

Issues encontrados:
1. 
2. 
3. 

Bloqueantes para release: [ ] SÍ  [ ] NO
```
