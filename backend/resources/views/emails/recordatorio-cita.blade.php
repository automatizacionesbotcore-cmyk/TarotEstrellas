Hola{{ $cita->cliente?->profile?->nombre ? ', ' . $cita->cliente->profile->nombre : '' }},

Te recordamos que {{ $cuando }} tienes una cita agendada en TarotEstrellas.

Servicio:   {{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}
Fecha:      {{ $cita->inicio_utc?->setTimezone('America/Santiago')->format('d/m/Y H:i') }} (hora Chile)
Duración:   {{ $cita->tipoConsulta?->duracion_minutos }} minutos
Referencia: {{ $cita->codigo_referencia ?? $cita->uuid }}

@if(! $cita->cliente_confirmo_at)
👉 Confirma tu asistencia haciendo clic aquí:
{{ $confirmUrl }}

Tu confirmación ayuda a tu especialista a prepararse mejor para la sesión.
@else
✓ Asistencia confirmada el {{ $cita->cliente_confirmo_at->setTimezone('America/Santiago')->format('d/m/Y H:i') }}.
@endif

Ingresa a tu cuenta unos minutos antes para acceder al enlace de la sala de video.

¡Te esperamos!
El equipo de TarotEstrellas
