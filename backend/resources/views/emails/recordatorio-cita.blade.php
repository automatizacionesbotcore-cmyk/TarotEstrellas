Hola{{ $cita->cliente?->profile?->nombre ? ', ' . $cita->cliente->profile->nombre : '' }},

Te recordamos que mañana tienes una cita agendada en TarotEstrellas.

Servicio:  {{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}
Fecha:     {{ $cita->inicio_utc?->setTimezone('America/Santiago')->format('d/m/Y H:i') }} (hora Chile)
Duración:  {{ $cita->tipoConsulta?->duracion_minutos }} minutos
Referencia: {{ $cita->codigo_referencia ?? $cita->uuid }}

Ingresa a tu cuenta para acceder al enlace de la sala de video unos minutos antes de la sesión.

¡Te esperamos!
El equipo de TarotEstrellas
