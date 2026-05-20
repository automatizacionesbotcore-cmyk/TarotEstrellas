@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $clienteNombre = trim((string) ($cita->cliente?->profile?->nombre ?? $cita->cliente?->name ?? ''));
    $fecha = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i');
    $detalleUrl = $frontendUrl . '/app/citas/' . $cita->uuid;
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Recordatorio de cita',
    'eyebrow' => 'Recordatorio de agenda',
    'badge' => 'Tu cita es ' . $cuando,
    'heading' => 'Recuerda tu cita en TarotEstrellas',
    'preheader' => 'Te recordamos que tienes una cita agendada ' . $cuando . '.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">
        Te recordamos que <strong style="color:#5A3D8B;">{{ $cuando }}</strong> tienes una cita agendada.
        Ingresa a tu cuenta unos minutos antes para acceder al enlace de la sala de video.
    </p>

    @include('emails.partials.detail-table', [
        'rows' => [
            'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta'),
            'Fecha' => e(($fecha ?? 'Por confirmar') . ' (hora Chile)'),
            'Duración' => e(($cita->tipoConsulta?->duracion_minutos ?? '-') . ' minutos'),
            'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
        ],
    ])

    @if(! $cita->cliente_confirmo_at)
        <div style="background:#FBEFF3;border:1px solid #E6C2CD;border-radius:8px;padding:18px;margin:22px 0;text-align:center;">
            <p style="margin:0 0 12px;color:#3E2A61;font-size:15px;font-weight:700;">Confirma tu asistencia</p>
            <p style="margin:0 0 16px;color:#5C5168;font-size:14px;line-height:1.5;">
                Tu confirmación ayuda a preparar mejor la sesión.
            </p>
            @include('emails.partials.button', [
                'url' => $confirmUrl,
                'label' => 'Confirmar mi cita',
            ])
        </div>
    @else
        <p style="background:#FBEFF3;color:#963058;border:1px solid #E6C2CD;border-radius:8px;padding:12px 16px;font-size:14px;margin:0 0 22px;">
            Asistencia confirmada el {{ $cita->cliente_confirmo_at->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i') }}.
        </p>
    @endif

    @if($cita->estado === 'reservada')
        <div style="background:#FBEFF3;border:1px solid #E6C2CD;border-radius:8px;padding:18px;margin:22px 0;text-align:center;">
            <p style="margin:0 0 12px;color:#963058;font-size:15px;font-weight:700;">Pago de saldo pendiente</p>
            <p style="margin:0 0 16px;color:#5C5168;font-size:14px;line-height:1.5;">
                Para mantener tu reserva activa debes pagar la diferencia al menos 24 horas antes de la consulta.
                Si el saldo no queda pagado dentro de ese plazo, la cita se anulará automáticamente.
            </p>
            @include('emails.partials.button', [
                'url' => $saldoUrl,
                'label' => 'Pagar diferencia',
            ])
        </div>
    @endif

    @include('emails.partials.button', [
        'url' => $detalleUrl,
        'label' => 'Ver detalle de cita',
    ])
@endsection
