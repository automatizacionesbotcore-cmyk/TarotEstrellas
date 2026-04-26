Hola{{ $cita->cliente?->profile?->nombre ? ', ' . $cita->cliente->profile->nombre : '' }},

Tu cita ha sido cancelada.

Servicio:  {{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}
Fecha:     {{ $cita->inicio_utc?->setTimezone('America/Santiago')->format('d/m/Y H:i') }} (hora Chile)
Referencia: {{ $cita->codigo_referencia ?? $cita->uuid }}

@if($conReembolso)
Se generó un reembolso por el monto abonado. El procesamiento puede tardar entre 3 y 10 días
hábiles dependiendo de tu banco o medio de pago.
@else
De acuerdo con nuestra política, esta cancelación no genera reembolso.
@endif

Si tienes dudas, responde este correo o contáctanos directamente.

El equipo de TarotEstrellas
