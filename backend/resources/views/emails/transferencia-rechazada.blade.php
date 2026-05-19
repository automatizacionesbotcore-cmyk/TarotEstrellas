@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $cita = $comprobante->cita;
    $cliente = $cita?->cliente;
    $clienteNombre = trim((string) ($cliente?->profile?->nombre ?? $cliente?->name ?? ''));
    $pagarUrl = $cita?->uuid ? $frontendUrl . '/app/citas/' . $cita->uuid . '/pagar' : $frontendUrl . '/app/mis-consultas';
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Comprobante rechazado',
    'eyebrow' => 'Validación de pago',
    'badge' => 'Acción requerida',
    'heading' => 'No pudimos validar tu comprobante',
    'preheader' => 'Necesitamos que adjuntes un nuevo comprobante válido para mantener tu cita.',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;">
        Revisamos el comprobante enviado, pero no fue posible validarlo. Para mantener tu cita debes subir
        un nuevo comprobante válido desde tu cuenta.
    </p>

    <div style="background:#FBEFF3;border:1px solid #E6C2CD;border-left:4px solid #B85C78;border-radius:8px;margin:22px 0;padding:16px 18px;">
        <p style="margin:0 0 6px;color:#963058;font-size:14px;font-weight:700;">Motivo del rechazo</p>
        <p style="margin:0;color:#3C3348;font-size:15px;line-height:1.55;">{{ $razon }}</p>
    </div>

    @if($cita)
        @include('emails.partials.detail-table', [
            'accent' => '#B85C78',
            'rows' => [
                'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
                'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta de Tarot'),
            ],
        ])
    @endif

    <p style="margin:0 0 16px;">
        Si no adjuntas un comprobante válido dentro del plazo establecido, la cita puede quedar anulada automáticamente.
    </p>

    @include('emails.partials.button', [
        'url' => $pagarUrl,
        'label' => 'Subir nuevo comprobante',
        'color' => '#B85C78',
    ])
@endsection
