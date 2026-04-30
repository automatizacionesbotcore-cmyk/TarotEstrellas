<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTranscripcionController extends Controller
{
    /**
     * GET /api/admin/citas/{uuid}/transcripcion
     * Devuelve JSON con texto crudo, resumen, metadatos. Solo admin.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $cita = Cita::query()
            ->with(['cliente:id,email', 'transcripciones', 'resumenes', 'grabaciones'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $transcripcion = $cita->transcripciones()->latest('id')->first();
        $resumen = $cita->resumenes()->latest('id')->first();

        return response()->json([
            'data' => [
                'cita_uuid' => $cita->uuid,
                'cliente_email' => $cita->cliente?->email,
                'transcripcion' => $transcripcion ? [
                    'id' => $transcripcion->id,
                    'contenido' => $transcripcion->contenido,
                    'idioma' => $transcripcion->idioma,
                    'proveedor' => $transcripcion->proveedor,
                    'modelo' => $transcripcion->modelo,
                    'creada_en' => $transcripcion->created_at?->toIso8601String(),
                ] : null,
                'resumen' => $resumen ? [
                    'id' => $resumen->id,
                    'contenido' => $resumen->contenido ?? null,
                    'creado_en' => $resumen->created_at?->toIso8601String(),
                ] : null,
                'grabaciones' => $cita->grabaciones->map(fn ($g) => [
                    'id' => $g->id,
                    'estado' => $g->estado,
                    'url_grabacion' => $g->url_grabacion,
                    'expira_en' => $g->expira_en?->toIso8601String(),
                    'borrada_en' => $g->borrada_en?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * GET /api/admin/citas/{uuid}/transcripcion/descargar?formato=txt
     * Descarga TXT de la transcripcion. Solo admin.
     */
    public function descargar(Request $request, string $uuid): StreamedResponse
    {
        $formato = strtolower((string) $request->query('formato', 'txt'));
        $cita = Cita::query()->with('cliente:id,email')->where('uuid', $uuid)->firstOrFail();
        $transcripcion = $cita->transcripciones()->latest('id')->first();

        abort_unless($transcripcion, 404, 'No hay transcripcion para esta cita.');

        $contenido = (string) $transcripcion->contenido;
        $cliente = $cita->cliente?->email ?? 'cliente';
        $fecha = $cita->inicio_utc?->format('Y-m-d') ?? 'sin-fecha';

        $headerTxt = "Transcripcion de la cita {$cita->codigo_referencia}\n"
            . "Cliente: {$cliente}\n"
            . "Fecha: {$fecha}\n"
            . "Idioma: " . ($transcripcion->idioma ?? 'es') . "\n"
            . "Proveedor: " . ($transcripcion->proveedor ?? 'n/a') . "\n"
            . str_repeat('=', 60) . "\n\n";

        $body = $headerTxt . $contenido;
        $filename = "transcripcion-{$cita->codigo_referencia}-{$fecha}.txt";

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
