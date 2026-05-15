@php
    $frontendUrl = rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $exceeded = ($row['estado'] ?? '') === 'exceeded';
    $adminUrl = $frontendUrl . '/app/admin/api-usage';
    $accent = $exceeded ? '#B2483D' : '#A66A2C';
    $accentDark = $exceeded ? '#7A2F28' : '#6C441F';
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Alerta consumo API',
    'eyebrow' => 'Monitoreo técnico',
    'badge' => $exceeded ? 'Límite superado' : 'Cerca del límite',
    'heading' => $exceeded ? 'Un proveedor superó su límite de API' : 'Un proveedor está cerca del límite de API',
    'preheader' => 'Alerta automática de consumo de APIs para el periodo ' . $periodo . '.',
    'accent' => $accent,
    'accentDark' => $accentDark,
])

@section('content')
    <p style="margin:0 0 16px;">
        Esta alerta automática corresponde al periodo <strong>{{ $periodo }}</strong>.
    </p>

    @include('emails.partials.detail-table', [
        'accent' => $accent,
        'rows' => [
            'Proveedor' => e(strtoupper($row['provider'] ?? '')),
            'Consumo actual' => e(number_format((float) ($row['usado'] ?? 0), 2) . ' ' . ($row['unidad'] ?? '')),
            'Límite mensual' => e(number_format((float) ($row['limite'] ?? 0), 2) . ' ' . ($row['unidad'] ?? '')),
            'Porcentaje' => e(($row['porcentaje'] ?? 0) . '%'),
            'Umbral de aviso' => e(($row['warn_pct'] ?? 80) . '%'),
        ],
    ])

    <p style="margin:0 0 16px;">
        @if($exceeded)
            El consumo del mes superó el límite configurado. Revisa el dashboard para ajustar cuotas,
            proveedores o alertas antes de que afecte la operación.
        @else
            El consumo se acerca al límite configurado. Aún hay margen, pero conviene revisarlo.
        @endif
    </p>

    @include('emails.partials.button', [
        'url' => $adminUrl,
        'label' => 'Abrir dashboard API',
        'color' => $accent,
    ])
@endsection
