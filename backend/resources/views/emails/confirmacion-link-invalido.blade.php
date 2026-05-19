@extends('emails.layouts.branded', [
    'title' => 'Enlace inválido',
    'eyebrow' => 'Confirmación de asistencia',
    'badge' => 'Enlace no disponible',
    'heading' => 'El enlace ya no es válido',
    'preheader' => 'El enlace de confirmación es inválido o expiró.',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">
        El enlace de confirmación ya no es válido o expiró. Ingresa a tu cuenta para gestionar tu cita y
        revisar su estado actual.
    </p>

    @include('emails.partials.button', [
        'url' => rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/') . '/app/mis-consultas',
        'label' => 'Ir a mi cuenta',
        'color' => '#B85C78',
    ])
@endsection
