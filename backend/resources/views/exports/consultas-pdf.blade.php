<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de consultas</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 4px;
        }
        h2 {
            font-size: 14px;
            margin: 16px 0 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
        }
        .empty {
            margin-top: 12px;
            padding: 10px;
            border: 1px dashed #9ca3af;
            color: #4b5563;
        }
        .block {
            margin-top: 10px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <h1>Historial de consultas</h1>
    <div class="muted">
        Cliente: {{ $user->name }} ({{ $user->email }})<br>
        Exportado en: {{ $exportadoEn->toDateTimeString() }}<br>
        Total de consultas: {{ $citas->count() }}
    </div>

    @if($citas->isEmpty())
        <div class="empty">No existen consultas para exportar.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Inicio UTC</th>
                    <th>Moneda</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($citas as $cita)
                    <tr>
                        <td>{{ $cita->codigo_referencia }}</td>
                        <td>{{ optional($cita->tipoConsulta)->nombre }}</td>
                        <td>{{ $cita->estado }}</td>
                        <td>{{ optional($cita->inicio_utc)->toDateTimeString() }}</td>
                        <td>{{ $cita->moneda }}</td>
                        <td>{{ $cita->precio_final_centavos }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @foreach($citas as $cita)
            <h2>{{ $cita->codigo_referencia }} - {{ optional($cita->tipoConsulta)->nombre }}</h2>
            <div class="block">
                <strong>Pagos:</strong> {{ $cita->pagos->count() }}<br>
                <strong>Grabaciones:</strong> {{ $cita->grabaciones->count() }}<br>
                <strong>Transcripciones:</strong> {{ $cita->grabaciones->filter(fn($g) => $g->transcripcion !== null)->count() }}<br>
                <strong>Resumenes:</strong> {{ $cita->grabaciones->filter(fn($g) => $g->transcripcion && $g->transcripcion->resumen !== null)->count() }}
            </div>
        @endforeach
    @endif
</body>
</html>
