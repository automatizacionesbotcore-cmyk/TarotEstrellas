@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $clienteNombre = trim((string) ($cita->cliente?->profile?->nombre ?? $cita->cliente?->name ?? ''));
    $fecha = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i');
    $detalleUrl = $frontendUrl . '/app/citas/' . $cita->uuid;
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Cita cancelada',
    'eyebrow' => 'Actualización de cita',
    'badge' => 'Cita cancelada',
    'heading' => 'Tu cita fue cancelada',
    'preheader' => 'Te informamos que tu cita en TarotEstrellas fue cancelada.',
    'accent' => '#8A4A3A',
    'accentDark' => '#5A2D24',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">Te informamos que la cita indicada a continuación fue cancelada.</p>

    @include('emails.partials.detail-table', [
        'accent' => '#8A4A3A',
        'rows' => [
            'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta'),
            'Fecha' => e(($fecha ?? 'Por confirmar') . ' (hora Chile)'),
            'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
        ],
    ])

    @if($conReembolso)
        <p style="margin:0 0 16px;">
            Se generó un reembolso por el monto abonado. El procesamiento puede tardar entre 3 y 10 días
            hábiles, según tu banco o medio de pago.
        </p>
    @else
        <p style="margin:0 0 16px;">
            De acuerdo con nuestra política, esta cancelación no genera reembolso.
        </p>
    @endif

    @include('emails.partials.button', [
        'url' => $detalleUrl,
        'label' => 'Revisar detalle',
        'color' => '#8A4A3A',
    ])
@endsection
