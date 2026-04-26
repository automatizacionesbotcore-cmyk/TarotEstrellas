Hola{{ $cita->cliente?->profile?->nombre ? ', ' . $cita->cliente->profile->nombre : '' }},

Tu abono fue procesado exitosamente y tu cita quedó reservada.

Servicio:  {{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}
Fecha:     {{ $cita->inicio_utc?->setTimezone('America/Santiago')->format('d/m/Y H:i') }} (hora Chile)
Duración:  {{ $cita->tipoConsulta?->duracion_minutos }} minutos
Referencia: {{ $cita->codigo_referencia ?? $cita->uuid }}

Para confirmar tu cita debes completar el pago del saldo restante (80%) antes de la fecha indicada.
Ingresa a tu cuenta en TarotEstrellas para hacerlo.

¡Nos vemos pronto!
El equipo de TarotEstrellas
