Hola{{ $cita->cliente?->profile?->nombre ? ', ' . $cita->cliente->profile->nombre : '' }},

¡Tu cita está confirmada! El pago total fue procesado correctamente.

Servicio:  {{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}
Fecha:     {{ $cita->inicio_utc?->setTimezone('America/Santiago')->format('d/m/Y H:i') }} (hora Chile)
Duración:  {{ $cita->tipoConsulta?->duracion_minutos }} minutos
Referencia: {{ $cita->codigo_referencia ?? $cita->uuid }}

Recibirás un recordatorio el día anterior a tu cita. El enlace para ingresar a la sala de video
estará disponible en tu cuenta unos minutos antes de la hora agendada.

Gracias por confiar en TarotEstrellas.
El equipo de TarotEstrellas
