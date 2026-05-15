@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $clienteNombre = trim((string) ($cita->cliente?->profile?->nombre ?? $cita->cliente?->name ?? ''));
    $fecha = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i');
    $detalleUrl = $frontendUrl . '/app/citas/' . $cita->uuid . '/pagar';
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Reserva confirmada',
    'eyebrow' => 'Reserva de cita',
    'badge' => 'Abono recibido',
    'heading' => 'Tu cita quedó reservada',
    'preheader' => 'Procesamos tu abono y reservamos tu horario en TarotEstrellas.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">
        Procesamos correctamente tu abono y tu horario quedó reservado. Para dejar la cita confirmada,
        debes completar el saldo restante antes de la fecha indicada.
    </p>

    @include('emails.partials.detail-table', [
        'rows' => [
            'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta'),
            'Fecha' => e(($fecha ?? 'Por confirmar') . ' (hora Chile)'),
            'Duración' => e(($cita->tipoConsulta?->duracion_minutos ?? '-') . ' minutos'),
            'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
            'Estado' => 'Reservada, pendiente de saldo',
        ],
    ])

    <p style="margin:0 0 16px;">
        En tu cuenta encontrarás el detalle de pago y el estado actualizado de la reserva.
    </p>

    @include('emails.partials.button', [
        'url' => $detalleUrl,
        'label' => 'Completar pago',
    ])
@endsection
