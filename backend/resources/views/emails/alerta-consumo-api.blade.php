<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Alerta consumo API</title>
</head>
<body style="font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:#f5f5f5; margin:0; padding:24px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
    <tr>
        <td style="padding:20px 24px; background: {{ $row['estado'] === 'exceeded' ? '#dc2626' : '#f59e0b' }}; color:#fff;">
            <h1 style="margin:0; font-size:18px;">
                {{ $row['estado'] === 'exceeded' ? '🚨 Límite de API superado' : '⚠️ Consumo de API cerca del límite' }}
            </h1>
            <p style="margin:6px 0 0; font-size:13px; opacity:.9;">Periodo {{ $periodo }}</p>
        </td>
    </tr>
    <tr>
        <td style="padding:24px;">
            <p style="margin:0 0 12px; font-size:15px;">
                Proveedor: <strong>{{ strtoupper($row['provider']) }}</strong>
            </p>
            <table cellspacing="0" cellpadding="8" style="width:100%; border-collapse:collapse; font-size:14px;">
                <tr>
                    <td style="border:1px solid #e5e7eb;">Consumo actual</td>
                    <td style="border:1px solid #e5e7eb;"><strong>{{ number_format($row['usado'], 2) }} {{ $row['unidad'] }}</strong></td>
                </tr>
                <tr>
                    <td style="border:1px solid #e5e7eb;">Límite mensual</td>
                    <td style="border:1px solid #e5e7eb;">{{ number_format($row['limite'], 2) }} {{ $row['unidad'] }}</td>
                </tr>
                <tr>
                    <td style="border:1px solid #e5e7eb;">Porcentaje</td>
                    <td style="border:1px solid #e5e7eb;"><strong>{{ $row['porcentaje'] }}%</strong></td>
                </tr>
            </table>
            <p style="margin:18px 0 0; font-size:13px; color:#6b7280;">
                @if ($row['estado'] === 'exceeded')
                    El consumo del mes <strong>superó el límite configurado</strong>.
                    Revisa el dashboard de APIs en el panel de Super Admin.
                @else
                    Has alcanzado el {{ $row['warn_pct'] ?? 80 }}% del límite. Aún hay margen, pero conviene revisar.
                @endif
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 24px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; color:#6b7280;">
            Esta es una alerta automática del sistema TarotEstrellas.
        </td>
    </tr>
</table>
</body>
</html>
