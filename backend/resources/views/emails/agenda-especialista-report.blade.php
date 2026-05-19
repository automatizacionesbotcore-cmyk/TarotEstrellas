@php
    $nombreEspecialista = trim((string) ($especialista->profile?->nombre ?? $especialista->name ?? ''));
    $tituloPeriodo = $periodo === 'semanal' ? 'Reporte semanal de agenda' : 'Agenda de hoy';
@endphp
@extends('emails.layouts.branded', [
    'title' => $tituloPeriodo,
    'eyebrow' => 'Agenda especialista',
    'badge' => $periodo === 'semanal' ? 'Semana en curso' : 'Hoy',
    'heading' => $tituloPeriodo,
    'preheader' => 'Detalle de clientes agendados en TarotEstrellas.',
    'accent' => '#B85C78',
    'accentDark' => '#963058',
])

@section('content')
    <p style="margin:0 0 16px;">Hola{{ $nombreEspecialista !== '' ? ', ' . $nombreEspecialista : '' }},</p>

    <p style="margin:0 0 18px;">
        Este es el detalle de clientes agendados entre
        <strong>{{ $desdeChile }}</strong> y <strong>{{ $hastaChile }}</strong> hora Chile.
    </p>

    @if($citas->isEmpty())
        <p style="background:#FBEFF3;color:#963058;border:1px solid #E6C2CD;border-radius:8px;padding:14px 16px;margin:0;">
            No tienes clientes agendados para este periodo.
        </p>
    @else
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:20px 0;border:1px solid #E8DFD6;border-radius:8px;overflow:hidden;">
            <thead>
                <tr style="background:#FBEFF3;">
                    <th align="left" style="padding:10px 12px;font-size:12px;color:#963058;text-transform:uppercase;">Hora</th>
                    <th align="left" style="padding:10px 12px;font-size:12px;color:#963058;text-transform:uppercase;">Cliente</th>
                    <th align="left" style="padding:10px 12px;font-size:12px;color:#963058;text-transform:uppercase;">Servicio</th>
                    <th align="left" style="padding:10px 12px;font-size:12px;color:#963058;text-transform:uppercase;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($citas as $cita)
                    @php
                        $clienteNombre = trim((string) (($cita->cliente?->profile?->nombre ?? '') . ' ' . ($cita->cliente?->profile?->apellido ?? '')));
                        $clienteNombre = $clienteNombre !== '' ? $clienteNombre : ($cita->cliente?->name ?? 'Cliente');
                        $hora = $cita->inicio_utc?->copy()?->setTimezone('America/Santiago')?->format('d/m H:i');
                    @endphp
                    <tr>
                        <td style="padding:12px;border-top:1px solid #E8DFD6;font-size:14px;color:#2B2238;white-space:nowrap;">{{ $hora }}</td>
                        <td style="padding:12px;border-top:1px solid #E8DFD6;font-size:14px;color:#2B2238;">
                            <strong>{{ $clienteNombre }}</strong><br>
                            <span style="color:#756A80;">{{ $cita->cliente?->email ?? 'Sin correo' }}</span>
                        </td>
                        <td style="padding:12px;border-top:1px solid #E8DFD6;font-size:14px;color:#2B2238;">
                            {{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}<br>
                            <span style="color:#756A80;">{{ $cita->duracion_minutos }} min · Ref. {{ $cita->codigo_referencia }}</span>
                        </td>
                        <td style="padding:12px;border-top:1px solid #E8DFD6;font-size:14px;color:#2B2238;">{{ str_replace('_', ' ', $cita->estado) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
