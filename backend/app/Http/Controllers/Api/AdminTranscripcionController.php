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
     * Ver transcripción cruda + resumen.
     * Permisos:
     *   super_admin      → cualquier cita.
     *   admin_especialista → solo sus propias citas (cita.especialista_id == user.id).
     *   cliente / otros  → 403.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user        = $request->user();
        $isSuperAdmin = (bool) $user?->hasRole('super_admin');
        $isEspecialista = (bool) $user?->hasRole('admin_especialista');

        if (! $isSuperAdmin && ! $isEspecialista) {
            return response()->json(['message' => 'Solo administradores pueden ver la transcripcion cruda.'], 403);
        }

        $query = Cita::query()
            ->with(['cliente:id,email', 'transcripciones', 'resumenes', 'grabaciones'])
            ->where('uuid', $uuid);

        // Especialista: solo sus citas asignadas.
        if ($isEspecialista && ! $isSuperAdmin) {
            $query->where('especialista_id', $user->id);
        }

        $cita = $query->first();
        if (! $cita) {
            // 403 (no 404) para no revelar si la cita existe.
            return response()->json(['message' => 'No tienes acceso a esta cita.'], 403);
        }

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
     * Descarga TXT de la transcripción.
     * Permisos iguales a show:
     *   super_admin       → cualquier cita.
     *   admin_especialista → solo sus propias citas.
     */
    public function descargar(Request $request, string $uuid): StreamedResponse
    {
        $user = $request->user();
        $isSuperAdmin = (bool) $user?->hasRole('super_admin');
        $isEspecialista = (bool) $user?->hasRole('admin_especialista');

        abort_unless($isSuperAdmin || $isEspecialista, 403, 'Solo administradores pueden descargar transcripciones.');

        $formato = strtolower((string) $request->query('formato', 'txt'));
        $query = Cita::query()->with('cliente:id,email')->where('uuid', $uuid);

        if ($isEspecialista && ! $isSuperAdmin) {
            $query->where('especialista_id', $user->id);
        }

        $cita = $query->first();
        abort_unless($cita, 403, 'No tienes acceso a esta cita.');
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
