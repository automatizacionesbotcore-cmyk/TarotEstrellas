@php
    $clienteNombre = trim((string) ($cita->cliente?->profile?->nombre ?? $cita->cliente?->name ?? ''));
    $especialistaNombre = trim((string) ($cita->especialista?->profile?->nombre ?? $cita->especialista?->name ?? ''));
    $fecha = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i');
@endphp

@extends('emails.layouts.branded', [
    'title' => 'Sala abierta',
    'eyebrow' => 'Consulta en vivo',
    'badge' => 'Sala disponible',
    'heading' => 'Tu sala de consulta esta abierta',
    'preheader' => 'Ya puedes entrar a tu sala privada de TarotEstrellas.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;color:#5C5168;line-height:1.6;">
        Tu especialista abrió una sala privada para una consulta rápida. Puedes entrar desde el botón de este correo.
    </p>

    @if($mensaje)
        <p style="margin:0 0 16px;color:#5C5168;line-height:1.6;">
            Mensaje del especialista: {{ $mensaje }}
        </p>
    @endif

    @include('emails.partials.detail-table', [
        'rows' => [
            'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta'),
            'Especialista' => e($especialistaNombre !== '' ? $especialistaNombre : 'TarotEstrellas'),
            'Fecha de apertura' => e(($fecha ?? 'Ahora') . ' (hora Chile)'),
            'Duración estimada' => e(($cita->duracion_minutos ?? $cita->tipoConsulta?->duracion_minutos ?? '-') . ' minutos'),
            'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
        ],
    ])

    <div style="text-align:center;margin:22px 0;">
        @include('emails.partials.button', [
            'url' => $salaUrl,
            'label' => 'Entrar a la sala',
        ])
    </div>
@endsection
