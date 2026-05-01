<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo comprobante recibido</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f0fa; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #1a6b3f, #27ae60); padding: 32px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
        .header p { color: #d4f5e2; margin: 8px 0 0; font-size: 14px; }
        .body { padding: 32px 24px; }
        .badge { display: inline-block; background: #d4edda; color: #155724; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: bold; margin-bottom: 20px; }
        .info-box { background: #f8f4ff; border-left: 4px solid #6b3fa0; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 0 0 6px; font-size: 14px; color: #555; }
        .cta { text-align: center; margin: 28px 0; }
        .btn { background: #6b3fa0; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: bold; display: inline-block; }
        .footer { background: #f4f0fa; padding: 16px 24px; text-align: center; font-size: 12px; color: #888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔔 Nuevo comprobante de transferencia</h1>
        <p>TarotEstrellas — Acción requerida</p>
    </div>
    <div class="body">
        <span class="badge">Revisión pendiente</span>

        @php
            $cita = $comprobante->cita;
        @endphp

        <p>Hola, se ha recibido un nuevo comprobante de transferencia que requiere tu revisión y aprobación.</p>

        <div class="info-box">
            <p><strong>Cliente:</strong> {{ $cliente->name }} ({{ $cliente->email }})</p>
            @if($cita)
            <p><strong>Referencia cita:</strong> {{ $cita->codigo_referencia }}</p>
            <p><strong>Servicio:</strong> {{ $cita->tipoConsulta?->nombre ?? 'Consulta de Tarot' }}</p>
            @endif
            <p><strong>Estado comprobante:</strong> Pendiente de revisión</p>
            <p><strong>Recibido:</strong> {{ now()->setTimezone('America/Santiago')->format('d/m/Y H:i') }} hrs</p>
        </div>

        <p>Ingresa al panel de administración para revisar el comprobante y confirmar si el pago fue recibido en tu cuenta bancaria.</p>

        <div class="cta">
            <a href="{{ rtrim(config('app.frontend_url', 'https://tarotestrellas.com'), '/') }}/app/admin/comprobantes" class="btn">
                Revisar comprobante
            </a>
        </div>

        <p style="font-size:13px; color:#888;">Una vez revisado en tu banco, usa los botones de <strong>Aprobar</strong> o <strong>Rechazar</strong> en el panel.</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} TarotEstrellas
    </div>
</div>
</body>
</html>
