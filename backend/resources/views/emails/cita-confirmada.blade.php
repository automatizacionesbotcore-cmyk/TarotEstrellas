@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $clienteNombre = trim((string) ($cita->cliente?->profile?->nombre ?? $cita->cliente?->name ?? ''));
    $fecha = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i');
    $detalleUrl = $frontendUrl . '/app/citas/' . $cita->uuid;
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Cita confirmada',
    'eyebrow' => 'Confirmación de cita',
    'badge' => 'Pago confirmado',
    'heading' => 'Tu cita está confirmada',
    'preheader' => 'El pago total fue procesado correctamente y tu cita quedó confirmada.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">
        El pago total fue procesado correctamente. Recibirás un recordatorio antes de la sesión y el enlace
        de la sala estará disponible en tu cuenta unos minutos antes del horario agendado.
    </p>

    @include('emails.partials.detail-table', [
        'rows' => [
            'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta'),
            'Fecha' => e(($fecha ?? 'Por confirmar') . ' (hora Chile)'),
            'Duración' => e(($cita->tipoConsulta?->duracion_minutos ?? '-') . ' minutos'),
            'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
            'Estado' => 'Confirmada',
        ],
    ])

    @include('emails.partials.button', [
        'url' => $detalleUrl,
        'label' => 'Ver mi cita',
    ])

    <p style="margin:0;">Gracias por confiar en TarotEstrellas.</p>
@endsection
