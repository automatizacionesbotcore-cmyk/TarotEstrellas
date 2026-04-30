<?php

namespace App\Services;

class AgentePlataformaContext
{
    public static function knowledge(): string
    {
        return <<<TXT
CONOCIMIENTO DE LA PLATAFORMA TarotEstrellas
============================================
TarotEstrellas es una plataforma de consultas esotericas (tarot, astrologia y rituales) atendidas por especialistas via videollamada o chat.

Como funciona:
- El visitante elige un tipo de consulta en /servicios, selecciona un horario disponible y se registra o inicia sesion.
- El pago se realiza por Stripe (tarjeta) o por transferencia bancaria con comprobante adjunto.
- A la hora de la sesion el cliente ingresa a la "Sala" virtual desde su panel /app y la consulta queda grabada con su autorizacion.
- Tras la sesion el sistema genera transcripcion y resumen automatico que el cliente puede consultar y tambien preguntarle al asistente IA.

Cuenta de cliente:
- Se puede registrar con email/contrasena o con Google.
- En /app/mi-cuenta puede actualizar perfil, contrasena, preferencias de notificacion y eliminar la cuenta.
- En /app/mis-consultas ve todo su historial, transcripciones, grabaciones y resumenes.

Membresia:
- Los clientes con membresia activa obtienen descuentos automaticos al pagar.
- Detalles en /app/membresia.

Pagos y reembolsos:
- Se aceptan tarjetas via Stripe y transferencias con validacion manual o automatica.
- Las cancelaciones con derecho a reembolso se gestionan desde el detalle de la cita.

Asistente IA:
- Esta disponible como widget flotante en la esquina inferior derecha en cualquier pagina.
- Si el visitante no esta logueado, responde dudas generales de la plataforma.
- Si el cliente esta logueado, ademas conoce su perfil e historial de sesiones.
- Si quien consulta es la administradora, puede preguntar por el historial de cualquier cliente indicandolo en la pregunta.

Soporte humano:
- Para escalamientos sensibles se redirige al correo de contacto de Chachita o al canal oficial de WhatsApp publicado en la landing.
TXT;
    }
}
