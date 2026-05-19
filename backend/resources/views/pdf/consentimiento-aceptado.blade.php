<!DOCTYPE html>
@php
    $logoPath = public_path('brand/logo-dark.png');
    $logoDataUri = is_file($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de aceptación — TarotEstrellas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            background: #ffffff;
            color: #1a0f2e;
            font-size: 10.5px;
            line-height: 1.55;
        }
        .page { padding: 0; }

        /* ── COVER HEADER ─────────────────────────────────── */
        .cover-header {
            background-color: #1e0a3c;
            padding: 0;
            margin-bottom: 0;
        }
        .cover-header-inner {
            padding: 28px 40px 20px 40px;
        }
        .cover-accent-bar {
            height: 5px;
            background-color: #c9a227;
        }

        /* Stars row */
        .stars-row { text-align: right; margin-bottom: 6px; }
        .star { color: #c9a227; font-size: 13px; margin: 0 3px; }
        .star-sm { color: #9b7ec8; font-size: 9px; margin: 0 2px; vertical-align: middle; }
        .stars-bottom { margin-top: 12px; }

        /* Logo row */
        .logo-row { margin-bottom: 6px; }
        .brand-logo-img {
            display: block;
            width: 260px;
            max-height: 78px;
            object-fit: contain;
            border: 0;
        }
        .brand-tagline {
            font-size: 9.5px;
            color: #b89fdf;
            margin-top: 1px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .cover-doc-title {
            font-size: 15px;
            color: #ffffff;
            font-weight: bold;
            margin-top: 12px;
            letter-spacing: 0.02em;
        }
        .cover-doc-subtitle {
            font-size: 10px;
            color: #9f7fd4;
            margin-top: 3px;
        }

        /* ── CONTENT AREA ─────────────────────────────────── */
        .content { padding: 28px 40px 20px 40px; }

        /* ── STATUS BANNER ────────────────────────────────── */
        .status-banner {
            background-color: #f0fdf4;
            border: 1.5px solid #22c55e;
            border-radius: 6px;
            padding: 10px 16px;
            margin-bottom: 22px;
        }
        .status-dot { color: #22c55e; font-size: 13px; vertical-align: middle; }
        .status-text { font-size: 11px; font-weight: bold; color: #15803d; vertical-align: middle; margin-left: 6px; }
        .status-sub  { font-size: 9.5px; color: #166534; margin-top: 2px; }

        /* ── SECTION HEADER ───────────────────────────────── */
        .section-header {
            background-color: #f5f0ff;
            border-left: 4px solid #7c3aed;
            padding: 7px 14px;
            margin-bottom: 14px;
            margin-top: 22px;
        }
        .section-header h2 {
            font-size: 11.5px;
            font-weight: bold;
            color: #4c1d95;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        /* ── INFO TABLE ───────────────────────────────────── */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .info-table tr:nth-child(even) td { background-color: #faf7ff; }
        .info-table td { padding: 6px 10px; font-size: 10.5px; border-bottom: 1px solid #ede9f7; }
        .info-table td.lbl {
            color: #6d28d9;
            font-weight: bold;
            width: 45%;
            white-space: nowrap;
        }
        .info-table td.val { color: #1a0f2e; }

        /* ── DOC CONTENT ──────────────────────────────────── */
        .doc-header-row {
            background-color: #2d1a4e;
            padding: 9px 14px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .doc-header-row .doc-main-title {
            font-size: 11px;
            font-weight: bold;
            color: #f0d060;
        }
        .doc-header-row .doc-main-version {
            font-size: 9px;
            color: #b89fdf;
            margin-top: 1px;
        }
        .doc-para {
            font-size: 9.5px;
            color: #2d1a4e;
            margin-bottom: 5px;
            text-align: justify;
            padding: 0 4px;
        }
        .doc-para-alt { background-color: #fdf8ff; }

        /* ── SIGNATURE AREA ───────────────────────────────── */
        .signature-area {
            margin-top: 28px;
            border: 1.5px dashed #7c3aed;
            border-radius: 6px;
            padding: 16px 20px;
            background-color: #faf7ff;
        }
        .sig-title { font-size: 10px; font-weight: bold; color: #4c1d95; margin-bottom: 10px; text-align: center; text-transform: uppercase; letter-spacing: 0.06em; }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-table td { padding: 4px 8px; font-size: 9.5px; }
        .sig-table td.sig-lbl { color: #7c3aed; font-weight: bold; width: 40%; }
        .sig-table td.sig-val { color: #1a0f2e; font-family: "DejaVu Sans Mono", monospace; font-size: 9px; }

        /* ── DOC ID / WATERMARK ───────────────────────────── */
        .doc-id-box {
            text-align: center;
            margin-top: 20px;
            padding: 8px;
            background-color: #f5f0ff;
            border-radius: 4px;
        }
        .doc-id-label { font-size: 8.5px; color: #9172c4; text-transform: uppercase; letter-spacing: 0.08em; }
        .doc-id-value { font-size: 9px; font-family: "DejaVu Sans Mono", monospace; color: #4c1d95; margin-top: 2px; }

        /* ── FOOTER ───────────────────────────────────────── */
        .footer-wrap {
            margin-top: 30px;
            padding-top: 0;
        }
        .footer-accent {
            height: 3px;
            background-color: #c9a227;
            margin-bottom: 0;
        }
        .footer-inner {
            background-color: #1e0a3c;
            padding: 12px 40px;
        }
        .footer-text { font-size: 8.5px; color: #9f7fd4; text-align: center; }
        .footer-text a { color: #c9a227; text-decoration: none; }
        .footer-legal { font-size: 8px; color: #6b4fa0; text-align: center; margin-top: 4px; }

        /* ── DIVIDER ──────────────────────────────────────── */
        .divider { border: none; border-top: 1px solid #e4d8ff; margin: 16px 0; }

        /* ── PAGE NUMBER ──────────────────────────────────── */
        @page { margin: 0; }
    </style>
</head>
<body>
<div class="page">

    {{-- ══ COVER HEADER ════════════════════════════════════ --}}
    <div class="cover-header">
        <div class="cover-accent-bar"></div>
        <div class="cover-header-inner">
            {{-- Stars top-right --}}
            <table width="100%" style="border-collapse:collapse; margin-bottom:10px;">
                <tr>
                    <td style="vertical-align:middle;">
                        {{-- Logo + brand --}}
                        <div class="logo-row">
                            @if($logoDataUri)
                                <img class="brand-logo-img" src="{{ $logoDataUri }}" alt="TarotEstrellas">
                            @else
                                <span style="font-size:22px;font-weight:bold;color:#f0d060;letter-spacing:0.06em;">TarotEstrellas</span>
                            @endif
                        </div>
                    </td>
                    <td style="text-align:right; vertical-align:top; padding-top:4px;">
                        <span class="star-sm">&#9733;</span>
                        <span class="star">&#9733;</span>
                        <span class="star-sm">&#9733;</span>
                        <span class="star">&#9733;</span>
                        <span class="star-sm">&#9733;</span>
                        <span class="star">&#9733;</span>
                        <span class="star-sm">&#9733;</span>
                    </td>
                </tr>
            </table>
            <div class="brand-tagline">Consultas de orientación espiritual</div>
            <div class="cover-doc-title">Constancia de Aceptación de Documentos Legales</div>
            <div class="cover-doc-subtitle">Documento generado automáticamente el {{ \Carbon\Carbon::parse($aceptadoEn)->format('d \d\e F \d\e Y, H:i') }} UTC</div>
            {{-- Stars bottom separator --}}
            <div class="stars-bottom" style="text-align:center; margin-top:14px;">
                <span class="star-sm">&#9733;</span>
                <span class="star-sm">&#9733;</span>
                <span class="star">&#10022;</span>
                <span class="star-sm">&#9733;</span>
                <span class="star">&#10022;</span>
                <span class="star-sm">&#9733;</span>
                <span class="star">&#10022;</span>
                <span class="star-sm">&#9733;</span>
                <span class="star-sm">&#9733;</span>
            </div>
        </div>
    </div>

    {{-- ══ CONTENT ══════════════════════════════════════════ --}}
    <div class="content">

        {{-- Status banner --}}
        <div class="status-banner">
            <span class="status-dot">✓</span>
            <span class="status-text">Documentos aceptados exitosamente</span>
            <div class="status-sub">El usuario identificado a continuación aceptó los documentos legales vigentes de forma libre y voluntaria.</div>
        </div>

        {{-- ── Datos del firmante ────────────────────────── --}}
        <div class="section-header"><h2>I. Datos del firmante</h2></div>
        <table class="info-table">
            <tr>
                <td class="lbl">Nombre completo</td>
                <td class="val">{{ ($user->profile?->nombre ?? $user->name) . ($user->profile?->apellido ? ' ' . $user->profile->apellido : '') }}</td>
            </tr>
            <tr>
                <td class="lbl">Correo electrónico</td>
                <td class="val">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="lbl">Fecha y hora de aceptación</td>
                <td class="val">{{ \Carbon\Carbon::parse($aceptadoEn)->format('d/m/Y H:i:s') }} UTC</td>
            </tr>
            <tr>
                <td class="lbl">Dirección IP registrada</td>
                <td class="val">{{ $ip }}</td>
            </tr>
            <tr>
                <td class="lbl">Versión de documentos</td>
                <td class="val">{{ $version }}</td>
            </tr>
            <tr>
                <td class="lbl">Estado</td>
                <td class="val" style="color:#15803d; font-weight:bold;">Aceptado ✓</td>
            </tr>
        </table>

        {{-- ── Documentos aceptados ─────────────────────── --}}
        <div class="section-header"><h2>II. Documentos legales aceptados</h2></div>

        {{-- Términos y condiciones --}}
        <div class="doc-header-row">
            <div class="doc-main-title">{{ $terminos['title'] ?? 'Términos y Condiciones de Uso' }}</div>
            <div class="doc-main-version">Versión: {{ $terminos['version'] ?? $version }}</div>
        </div>
        @foreach ($terminos['paragraphs'] ?? [] as $i => $para)
            <p class="doc-para {{ $i % 2 === 1 ? 'doc-para-alt' : '' }}">{{ $para }}</p>
        @endforeach

        <hr class="divider">

        {{-- Política de privacidad --}}
        <div class="doc-header-row">
            <div class="doc-main-title">{{ $privacidad['title'] ?? 'Política de Privacidad' }}</div>
            <div class="doc-main-version">Versión: {{ $privacidad['version'] ?? $version }}</div>
        </div>
        @foreach ($privacidad['paragraphs'] ?? [] as $i => $para)
            <p class="doc-para {{ $i % 2 === 1 ? 'doc-para-alt' : '' }}">{{ $para }}</p>
        @endforeach

        {{-- ── Área de firma digital ────────────────────── --}}
        <div class="section-header" style="margin-top:28px;"><h2>III. Constancia de aceptación</h2></div>
        <div class="signature-area">
            <div class="sig-title">✦ Registro de consentimiento digital ✦</div>
            <table class="sig-table">
                <tr>
                    <td class="sig-lbl">Usuario</td>
                    <td class="sig-val">{{ $user->email }}</td>
                </tr>
                <tr>
                    <td class="sig-lbl">Acción registrada</td>
                    <td class="sig-val">Aceptación voluntaria de Términos y Política de Privacidad</td>
                </tr>
                <tr>
                    <td class="sig-lbl">Timestamp UTC</td>
                    <td class="sig-val">{{ $aceptadoEn }}</td>
                </tr>
                <tr>
                    <td class="sig-lbl">IP de origen</td>
                    <td class="sig-val">{{ $ip }}</td>
                </tr>
                <tr>
                    <td class="sig-lbl">Versión</td>
                    <td class="sig-val">{{ $version }}</td>
                </tr>
            </table>
        </div>

        {{-- ── ID del documento ─────────────────────────── --}}
        <div class="doc-id-box">
            <div class="doc-id-label">Identificador único de documento</div>
            <div class="doc-id-value">TE-CONSENT-{{ strtoupper(substr(sha1($user->email . $aceptadoEn . $ip), 0, 32)) }}</div>
        </div>

    </div>{{-- /content --}}

    {{-- ══ FOOTER ═══════════════════════════════════════════ --}}
    <div class="footer-wrap">
        <div class="footer-accent"></div>
        <div class="footer-inner">
            <div class="footer-text">
                Este documento fue generado automáticamente por la plataforma TarotEstrellas.
                &bull; <span style="color:#c9a227;">tarotestrellas.com</span>
                &bull; contacto@tarotestrellas.com
            </div>
            <div class="footer-legal">
                Este documento tiene validez como constancia de aceptación de los documentos legales vigentes en la fecha indicada.
                Conserva este archivo para tus registros. &copy; {{ date('Y') }} TarotEstrellas. Todos los derechos reservados.
            </div>
        </div>
    </div>

</div>
</body>
</html>

