@extends('emails.layouts.branded', [
    'title' => 'Actualización de soporte',
    'preheader' => 'Tu solicitud de soporte tiene una actualización.',
    'eyebrow' => 'Soporte de plataforma',
    'badge' => 'Ticket ' . $ticket->codigo,
    'heading' => 'Tu solicitud fue actualizada',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $ticket->nombre ? ' ' . $ticket->nombre : '' }},</p>
    <p style="margin:0 0 18px;">
        Tenemos una actualización sobre tu solicitud de soporte.
    </p>

    @include('emails.partials.detail-table', ['rows' => [
        ['label' => 'Código', 'value' => $ticket->codigo],
        ['label' => 'Estado actual', 'value' => ucfirst(str_replace('_', ' ', $ticket->estado))],
        ['label' => 'Asunto', 'value' => $ticket->asunto],
    ]])

    @if($message && $message->visible_para_cliente)
        <div style="margin-top:18px;padding:16px;border:1px solid #E7D5DD;border-radius:8px;background:#FFF8FA;">
            <strong style="display:block;margin-bottom:8px;color:#963058;">Mensaje del equipo</strong>
            <p style="margin:0;white-space:pre-line;">{{ $message->mensaje }}</p>
        </div>
    @endif

    <p style="margin:18px 0 0;color:#756A80;font-size:14px;">
        Te avisaremos por correo si hay nuevas respuestas o cambios en el estado.
    </p>
@endsection
