@extends('emails.layouts.branded', [
    'title' => 'Solicitud de soporte recibida',
    'preheader' => 'Recibimos tu solicitud de soporte en TarotEstrellas.',
    'eyebrow' => 'Soporte de plataforma',
    'badge' => 'Ticket ' . $ticket->codigo,
    'heading' => 'Recibimos tu solicitud',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $ticket->nombre ? ' ' . $ticket->nombre : '' }},</p>
    <p style="margin:0 0 18px;">
        Ya tenemos registrada tu solicitud. Nuestro equipo revisará el caso y te notificará por correo cada cambio de estado.
    </p>

    @include('emails.partials.detail-table', ['rows' => [
        ['label' => 'Código', 'value' => $ticket->codigo],
        ['label' => 'Estado', 'value' => 'Nuevo'],
        ['label' => 'Tipo de problema', 'value' => $ticket->tipo_error],
        ['label' => 'Asunto', 'value' => $ticket->asunto],
    ]])

    <p style="margin:18px 0 0;color:#756A80;font-size:14px;">
        Si ya puedes ingresar a tu cuenta, también podrás revisar el estado desde el módulo de soporte en tu panel.
    </p>
@endsection
