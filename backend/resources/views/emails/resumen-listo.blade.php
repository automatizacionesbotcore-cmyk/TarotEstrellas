@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $citaUrl = $cita?->uuid ? $frontendUrl . '/app/citas/' . $cita->uuid : $frontendUrl . '/app/mis-consultas';
    $clienteNombre = trim((string) ($cita?->cliente?->profile?->nombre ?? $cita?->cliente?->name ?? ''));
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Resumen listo',
    'eyebrow' => 'Resumen de sesión',
    'badge' => 'Disponible en tu cuenta',
    'heading' => 'Tu resumen ya está listo',
    'preheader' => 'El resumen de tu sesión en TarotEstrellas ya está disponible.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">
        Ya puedes revisar el resumen de tu sesión desde tu cuenta. Lo dejamos asociado a tu consulta para
        que puedas volver a leerlo cuando lo necesites.
    </p>

    @include('emails.partials.detail-table', [
        'rows' => [
            'Referencia de cita' => e($cita?->codigo_referencia ?? 'Sin referencia'),
            'Servicio' => e($cita?->tipoConsulta?->nombre ?? 'Consulta'),
        ],
    ])

    @include('emails.partials.button', [
        'url' => $citaUrl,
        'label' => 'Ver resumen',
    ])
@endsection
