<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferencia validada</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f0fa; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #6b3fa0, #9b59b6); padding: 32px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
        .header p { color: #e8d5ff; margin: 8px 0 0; font-size: 14px; }
        .body { padding: 32px 24px; }
        .badge { display: inline-block; background: #d4edda; color: #155724; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: bold; margin-bottom: 20px; }
        .info-box { background: #f8f4ff; border-left: 4px solid #6b3fa0; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 0 0 6px; font-size: 14px; color: #555; }
        .info-box strong { color: #333; }
        .cta { text-align: center; margin: 28px 0; }
        .btn { background: #6b3fa0; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: bold; display: inline-block; }
        .footer { background: #f4f0fa; padding: 16px 24px; text-align: center; font-size: 12px; color: #888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✅ Transferencia Validada</h1>
        <p>TarotEstrellas</p>
    </div>
    <div class="body">
        <span class="badge">Pago confirmado</span>

        @php
            $cita = $comprobante->cita;
            $cliente = $cita?->cliente;
        @endphp

        <p>Hola{{ $cliente?->profile?->nombre ? ', ' . $cliente->profile->nombre : '' }},</p>

        <p>El especialista ha <strong>confirmado</strong> que tu transferencia bancaria fue recibida correctamente. Tu cita ha quedado <strong>reservada</strong>.</p>

        @if($cita)
        <div class="info-box">
            <p><strong>Referencia:</strong> {{ $cita->codigo_referencia }}</p>
            <p><strong>Servicio:</strong> {{ $cita->tipoConsulta?->nombre ?? 'Consulta de Tarot' }}</p>
            <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($cita->inicio_utc)->timezone($cita->zona_horaria_cliente ?? 'America/Santiago')->format('d/m/Y H:i') }}</p>
            <p><strong>Estado:</strong> Reservada / En espera de confirmación final</p>
        </div>
        @endif

        <p>Recibirás una confirmación final cuando tu cita sea completamente confirmada por el especialista.</p>

        <div class="cta">
            <a href="{{ rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/') }}/mis-citas" class="btn">Ver mis citas</a>
        </div>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} TarotEstrellas &bull; Si tienes dudas escríbenos a contacto@tarotestrellas.com
    </div>
</div>
</body>
</html>
