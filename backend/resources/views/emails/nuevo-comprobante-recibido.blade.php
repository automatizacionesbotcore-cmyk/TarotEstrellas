@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $cita = $comprobante->cita;
    $adminUrl = $frontendUrl . '/app/admin/comprobantes';
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Nuevo comprobante recibido',
    'eyebrow' => 'Panel administrativo',
    'badge' => 'Revisión pendiente',
    'heading' => 'Hay un nuevo comprobante por revisar',
    'preheader' => 'Un cliente subió un comprobante de transferencia y requiere validación.',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">
        Se recibió un nuevo comprobante de transferencia. Revisa el respaldo contra la cuenta bancaria antes
        de aprobar o rechazar el pago en el panel.
    </p>

    @include('emails.partials.detail-table', [
        'accent' => '#B85C78',
        'rows' => [
            'Cliente' => e($cliente->name . ' (' . $cliente->email . ')'),
            'Referencia cita' => e($cita?->codigo_referencia ?? 'Sin referencia'),
            'Servicio' => e($cita?->tipoConsulta?->nombre ?? 'Consulta de Tarot'),
            'Estado comprobante' => 'Pendiente de revisión',
            'Recibido' => e(now()->setTimezone('America/Santiago')->format('d/m/Y H:i') . ' hrs'),
        ],
    ])

    @include('emails.partials.button', [
        'url' => $adminUrl,
        'label' => 'Revisar comprobante',
        'color' => '#B85C78',
    ])

    <p style="margin:0;color:#756A80;font-size:14px;">
        Una vez revisado en el banco, usa las acciones de aprobar o rechazar desde el panel.
    </p>
@endsection
