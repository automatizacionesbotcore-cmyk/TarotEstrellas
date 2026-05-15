@php
    $nombre = trim((string) ($user->profile?->nombre ?? $user->name ?? ''));
    $fechaAceptacion = \Carbon\Carbon::parse($aceptadoEn)->setTimezone('America/Santiago')->format('d/m/Y H:i');
@endphp
@extends('emails.layouts.branded', [
    'title' => 'Confirmación de consentimiento',
    'eyebrow' => 'Registro y documentos legales',
    'badge' => 'Constancia legal',
    'heading' => 'Recibimos tu aceptación',
    'preheader' => 'Adjuntamos la constancia de aceptación de términos y política de privacidad.',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $nombre !== '' ? ', ' . $nombre : '' }},</p>

    <p style="margin:0 0 16px;">
        Gracias por registrarte en TarotEstrellas y aceptar nuestros documentos legales. Adjuntamos un PDF
        con los Términos y Condiciones y la Política de Privacidad aceptados, como constancia para tus registros.
    </p>

    @include('emails.partials.detail-table', [
        'rows' => [
            'Fecha de aceptación' => e($fechaAceptacion . ' (hora Chile)'),
            'Versión de documentos' => e($version),
            'Correo registrado' => e($user->email),
        ],
    ])

    <p style="margin:0 0 16px;color:#756A80;font-size:14px;">
        Si no realizaste este registro, contáctanos de inmediato en contacto@tarotestrellas.com.
    </p>
@endsection
