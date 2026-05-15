@extends('emails.layouts.branded', [
    'title' => 'Confirmación de cita',
    'eyebrow' => 'Confirmación de asistencia',
    'badge' => 'Resultado',
    'heading' => $titulo ?? 'Resultado',
    'preheader' => $mensaje ?? 'Resultado de confirmación de cita.',
])

@section('content')
    <p style="margin:0 0 16px;">{{ $mensaje ?? '' }}</p>

    @include('emails.partials.button', [
        'url' => rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/') . '/app/mis-consultas',
        'label' => 'Ir a mi cuenta',
    ])
@endsection
