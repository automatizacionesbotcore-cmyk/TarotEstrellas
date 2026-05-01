Hola {{ $user->profile?->nombre ?? $user->name }},

Gracias por registrarte en TarotEstrellas y por aceptar nuestros documentos legales.

Adjunto a este correo encontrarás el PDF con los Términos y Condiciones y la Política de Privacidad que aceptaste, a modo de constancia.

Detalles del registro:
  Fecha de aceptación: {{ \Carbon\Carbon::parse($aceptadoEn)->format('d/m/Y H:i') }} UTC
  Versión de documentos: {{ $version }}

Si no realizaste este registro, contáctanos de inmediato en contacto@tarotestrellas.com

¡Bienvenid@! Las estrellas ya conocen tu camino ✨

El equipo de TarotEstrellas
