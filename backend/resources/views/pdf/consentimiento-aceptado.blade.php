<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de aceptación — TarotEstrellas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; background: #fff; color: #2d1a4e; font-size: 11px; line-height: 1.5; }
        .page { padding: 36px 40px; }

        /* Header */
        .header { background: #2d1a4e; color: #f5d97e; padding: 22px 28px; border-radius: 6px; margin-bottom: 24px; }
        .header h1 { font-size: 18px; font-weight: bold; letter-spacing: 0.04em; }
        .header p  { font-size: 11px; color: #d4b8ff; margin-top: 4px; }

        /* Info box */
        .info-box { background: #f8f4ff; border-left: 4px solid #7c3aed; padding: 14px 18px; border-radius: 4px; margin-bottom: 20px; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td  { padding: 3px 6px 3px 0; font-size: 11px; }
        .info-box td.label { color: #7c3aed; font-weight: bold; width: 160px; white-space: nowrap; }
        .info-box td.value { color: #2d1a4e; }

        /* Section title */
        .section-title { font-size: 13px; font-weight: bold; color: #7c3aed; border-bottom: 1.5px solid #d4b8ff; padding-bottom: 5px; margin: 22px 0 10px; }

        /* Document block */
        .doc-title   { font-size: 12px; font-weight: bold; color: #2d1a4e; margin-bottom: 2px; }
        .doc-version { font-size: 10px; color: #9172c4; margin-bottom: 8px; }
        .doc-para    { font-size: 10px; color: #3d2a5e; margin-bottom: 5px; text-align: justify; }

        /* Footer */
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #d4b8ff; font-size: 9px; color: #9172c4; text-align: center; }

        /* Badge */
        .badge { display: inline-block; background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <h1>✦ TarotEstrellas — Constancia de aceptación</h1>
        <p>Este documento certifica la aceptación voluntaria de los documentos legales vigentes.</p>
    </div>

    <span class="badge">✓ Documentos aceptados</span>

    <div class="info-box">
        <table>
            <tr>
                <td class="label">Nombre</td>
                <td class="value">{{ $user->profile?->nombre ?? $user->name }}{{ $user->profile?->apellido ? ' ' . $user->profile->apellido : '' }}</td>
            </tr>
            <tr>
                <td class="label">Correo electrónico</td>
                <td class="value">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="label">Fecha y hora de aceptación</td>
                <td class="value">{{ \Carbon\Carbon::parse($aceptadoEn)->format('d/m/Y H:i:s') }} UTC</td>
            </tr>
            <tr>
                <td class="label">Dirección IP</td>
                <td class="value">{{ $ip }}</td>
            </tr>
            <tr>
                <td class="label">Versión de documentos</td>
                <td class="value">{{ $version }}</td>
            </tr>
        </table>
    </div>

    <!-- Términos y condiciones -->
    <div class="section-title">Términos y Condiciones</div>
    <p class="doc-title">{{ $terminos['title'] ?? 'Términos y condiciones de uso' }}</p>
    <p class="doc-version">Versión: {{ $terminos['version'] ?? $version }}</p>
    @foreach ($terminos['paragraphs'] ?? [] as $para)
        <p class="doc-para">{{ $para }}</p>
    @endforeach

    <!-- Política de privacidad -->
    <div class="section-title">Política de Privacidad</div>
    <p class="doc-title">{{ $privacidad['title'] ?? 'Política de privacidad' }}</p>
    <p class="doc-version">Versión: {{ $privacidad['version'] ?? $version }}</p>
    @foreach ($privacidad['paragraphs'] ?? [] as $para)
        <p class="doc-para">{{ $para }}</p>
    @endforeach

    <div class="footer">
        Este documento fue generado automáticamente como constancia de aceptación. &bull;
        &copy; {{ date('Y') }} TarotEstrellas &bull; contacto@tarotestrellas.com
    </div>

</div>
</body>
</html>
