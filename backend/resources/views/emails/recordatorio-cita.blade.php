<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recordatorio TarotEstrellas</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ea;font-family:'Helvetica Neue',Arial,sans-serif;color:#2b2238;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ea;padding:24px 0;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(90,61,139,0.08);">
      <tr>
        <td style="background:linear-gradient(135deg,#5a3d8b 0%,#8b5fbf 100%);padding:28px 32px;text-align:center;color:#ffffff;">
          <div style="font-size:24px;font-weight:700;letter-spacing:0.5px;">✨ TarotEstrellas</div>
          <div style="font-size:14px;opacity:0.9;margin-top:4px;">Recordatorio de tu cita</div>
        </td>
      </tr>
      <tr>
        <td style="padding:32px;">
          <p style="font-size:16px;margin:0 0 16px;">Hola @if($cita->cliente?->profile?->nombre){{ $cita->cliente->profile->nombre }}@endif,</p>
          <p style="font-size:16px;line-height:1.5;margin:0 0 24px;">
            Te recordamos que <strong style="color:#5a3d8b;">{{ $cuando }}</strong> tienes una cita agendada con TarotEstrellas.
          </p>

          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f2;border-radius:8px;border-left:4px solid #8b5fbf;margin-bottom:24px;">
            <tr><td style="padding:16px 20px;">
              <table width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;">
                <tr><td style="color:#7a6b8a;width:90px;">Servicio:</td><td><strong>{{ $cita->tipoConsulta?->nombre ?? 'Consulta' }}</strong></td></tr>
                <tr><td style="color:#7a6b8a;">Fecha:</td><td><strong>{{ $cita->inicio_utc?->setTimezone('America/Santiago')->format('d/m/Y H:i') }}</strong> <span style="color:#7a6b8a;">(hora Chile)</span></td></tr>
                <tr><td style="color:#7a6b8a;">Duración:</td><td>{{ $cita->tipoConsulta?->duracion_minutos }} minutos</td></tr>
                <tr><td style="color:#7a6b8a;">Referencia:</td><td style="font-family:monospace;font-size:12px;">{{ $cita->codigo_referencia ?? $cita->uuid }}</td></tr>
              </table>
            </td></tr>
          </table>

          @if(! $cita->cliente_confirmo_at)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
              <tr><td align="center" style="padding:16px;background:#fef9e7;border-radius:8px;">
                <p style="margin:0 0 12px;font-size:15px;color:#5a3d8b;"><strong>👉 Confirma tu asistencia</strong></p>
                <a href="{{ $confirmUrl }}" style="display:inline-block;background:#5a3d8b;color:#ffffff;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:600;font-size:15px;">Confirmar mi cita</a>
                <p style="margin:12px 0 0;font-size:12px;color:#7a6b8a;">Tu confirmación ayuda a tu especialista a prepararse mejor.</p>
              </td></tr>
            </table>
          @else
            <p style="background:#e8f5e9;color:#2e7d32;padding:12px 16px;border-radius:6px;font-size:14px;margin:0 0 24px;">
              ✓ Asistencia confirmada el {{ $cita->cliente_confirmo_at->setTimezone('America/Santiago')->format('d/m/Y H:i') }}.
            </p>
          @endif

          <p style="font-size:14px;line-height:1.5;color:#5c5168;margin:0 0 8px;">
            Ingresa a tu cuenta unos minutos antes para acceder al enlace de la sala de video.
          </p>
          <p style="font-size:14px;margin:24px 0 0;">¡Te esperamos!<br><strong>El equipo de TarotEstrellas</strong></p>
        </td>
      </tr>
      <tr>
        <td style="background:#faf7f2;padding:16px 32px;text-align:center;font-size:11px;color:#7a6b8a;">
          Si no reconoces esta cita, puedes ignorar este mensaje.<br>
          © {{ date('Y') }} TarotEstrellas
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
