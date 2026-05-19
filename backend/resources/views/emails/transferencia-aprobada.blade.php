@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $cita = $comprobante->cita;
    $cliente = $cita?->cliente;
    $clienteNombre = trim((string) ($cliente?->profile?->nombre ?? $cliente?->name ?? ''));
    $fecha = $cita?->inicio_utc?->copy()?->setTimezone($cita->zona_horaria_cliente ?? 'America/Santiago')?->format('d/m/Y H:i');
    $citaUrl = $cita?->uuid ? $frontendUrl . '/app/citas/' . $cita->uuid : $frontendUrl . '/app/mis-consultas';
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Transferencia validada',
    'eyebrow' => 'Validación de pago',
    'badge' => 'Pago confirmado',
    'heading' => 'Tu transferencia fue validada',
    'preheader' => 'Confirmamos la recepción de tu transferencia bancaria.',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">
        Confirmamos que tu transferencia bancaria fue recibida correctamente. La cita asociada ya quedó
        actualizada en tu cuenta.
    </p>

    @if($cita)
        @include('emails.partials.detail-table', [
            'accent' => '#B85C78',
            'rows' => [
                'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
                'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta de Tarot'),
                'Fecha' => e($fecha ? $fecha . ' hrs' : 'Por confirmar'),
                'Estado' => 'Reservada / en espera de confirmación final',
            ],
        ])
    @endif

    <p style="margin:0 0 16px;">
        Si tu consulta requiere un pago adicional, podrás completarlo desde el detalle de la cita.
    </p>

    @include('emails.partials.button', [
        'url' => $citaUrl,
        'label' => 'Ver mi cita',
        'color' => '#B85C78',
    ])
@endsection
