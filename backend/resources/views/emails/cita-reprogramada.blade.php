@php
    $clienteNombre = trim((string) ($cita->cliente?->profile?->nombre ?? $cita->cliente?->name ?? ''));
    $fechaNueva = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m/Y H:i');
    $fechaAnterior = $inicioAnterior ? \Carbon\CarbonImmutable::parse($inicioAnterior)->setTimezone('America/Santiago')->format('d/m/Y H:i') : null;
@endphp

@extends('emails.layouts.branded', [
    'title' => 'Cita reprogramada',
    'eyebrow' => 'Actualización de agenda',
    'badge' => 'Nueva fecha propuesta',
    'heading' => 'Tuvimos que reprogramar tu cita',
    'preheader' => 'Tu cita fue movida por una situación extraordinaria. Puedes aceptar la nueva fecha o elegir otra.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $clienteNombre !== '' ? ', ' . $clienteNombre : '' }},</p>

    <p style="margin:0 0 16px;color:#5C5168;line-height:1.6;">
        Lamentamos mucho este cambio. Por razones extraordinarias y ajenas al especialista,
        nos vimos en la necesidad de reprogramar tu cita. Queremos que sigas teniendo una
        experiencia tranquila, por eso puedes aceptar la nueva fecha o elegir otra disponible.
    </p>

    @if($motivo)
        <p style="margin:0 0 16px;color:#5C5168;line-height:1.6;">
            Motivo informado: {{ $motivo }}
        </p>
    @endif

    @include('emails.partials.detail-table', [
        'rows' => [
            'Servicio' => e($cita->tipoConsulta?->nombre ?? 'Consulta'),
            'Fecha anterior' => e(($fechaAnterior ?? 'No registrada') . ' (hora Chile)'),
            'Nueva fecha' => e(($fechaNueva ?? 'Por confirmar') . ' (hora Chile)'),
            'Duración' => e(($cita->tipoConsulta?->duracion_minutos ?? $cita->duracion_minutos ?? '-') . ' minutos'),
            'Referencia' => e($cita->codigo_referencia ?? $cita->uuid),
        ],
    ])

    <div style="text-align:center;margin:22px 0;">
        @include('emails.partials.button', [
            'url' => $aceptarUrl,
            'label' => 'Aceptar nueva fecha',
        ])
    </div>

    <div style="text-align:center;margin:22px 0;">
        @include('emails.partials.button', [
            'url' => $reagendarUrl,
            'label' => 'Elegir otra fecha',
        ])
    </div>
@endsection
