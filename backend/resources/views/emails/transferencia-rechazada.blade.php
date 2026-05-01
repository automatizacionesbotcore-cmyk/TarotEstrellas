<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante rechazado</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f0fa; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #c0392b, #e74c3c); padding: 32px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
        .header p { color: #ffddd9; margin: 8px 0 0; font-size: 14px; }
        .body { padding: 32px 24px; }
        .badge { display: inline-block; background: #f8d7da; color: #721c24; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: bold; margin-bottom: 20px; }
        .alert-box { background: #fff3f3; border-left: 4px solid #e74c3c; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .alert-box p { margin: 0 0 6px; font-size: 14px; color: #555; }
        .info-box { background: #f8f4ff; border-left: 4px solid #6b3fa0; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 0 0 6px; font-size: 14px; color: #555; }
        .cta { text-align: center; margin: 28px 0; }
        .btn { background: #6b3fa0; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: bold; display: inline-block; }
        .warning { background: #fff8e1; border: 1px solid #ffc107; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #856404; margin: 16px 0; }
        .footer { background: #f4f0fa; padding: 16px 24px; text-align: center; font-size: 12px; color: #888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚠️ Comprobante Rechazado</h1>
        <p>TarotEstrellas</p>
    </div>
    <div class="body">
        <span class="badge">Acción requerida</span>

        @php
            $cita = $comprobante->cita;
            $cliente = $cita?->cliente;
        @endphp

        <p>Hola{{ $cliente?->profile?->nombre ? ', ' . $cliente->profile->nombre : '' }},</p>

        <p>Lamentablemente el especialista <strong>no pudo validar</strong> tu comprobante de transferencia.</p>

        <div class="alert-box">
            <p><strong>Motivo del rechazo:</strong></p>
            <p>{{ $razon }}</p>
        </div>

        @if($cita)
        <div class="info-box">
            <p><strong>Referencia:</strong> {{ $cita->codigo_referencia }}</p>
            <p><strong>Servicio:</strong> {{ $cita->tipoConsulta?->nombre ?? 'Consulta de Tarot' }}</p>
        </div>
        @endif

        <div class="warning">
            ⚠️ <strong>Importante:</strong> Debes adjuntar un nuevo comprobante válido. Si no lo haces dentro del plazo establecido, tu cita quedará <strong>anulada automáticamente</strong>.
        </div>

        <p>Por favor ingresa a tu cuenta, selecciona la cita afectada y sube un nuevo comprobante de transferencia.</p>

        <div class="cta">
            <a href="{{ rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/') }}/mis-citas" class="btn">Subir nuevo comprobante</a>
        </div>

        <p style="font-size:13px; color:#888;">¿Tienes dudas? Escríbenos a contacto@tarotestrellas.com</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} TarotEstrellas
    </div>
</div>
</body>
</html>
