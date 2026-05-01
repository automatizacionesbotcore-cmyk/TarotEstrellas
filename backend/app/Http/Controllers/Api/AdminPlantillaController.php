<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaNotificacion;
use App\Models\PlantillaNotificacionVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPlantillaController extends Controller
{
    private const VARIABLES_BASE = [
        'cita_uuid', 'cita_codigo', 'cita_inicio',
        'cliente_nombre', 'cliente_email', 'cliente_telefono',
        'tipo_consulta', 'duracion_minutos', 'precio',
        'sala_url', 'reembolso_monto', 'enlace_descarga',
    ];

    public function index(Request $request): JsonResponse
    {
        $q = PlantillaNotificacion::query();
        if ($canal = $request->string('canal')->toString()) $q->where('canal', $canal);
        if ($request->has('activa')) $q->where('activa', $request->boolean('activa'));
        if ($s = $request->string('q')->toString()) {
            $q->where(fn ($w) => $w->where('codigo', 'like', "%$s%")->orWhere('nombre', 'like', "%$s%"));
        }
        return response()->json($q->orderBy('codigo')->paginate((int) $request->integer('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $p = PlantillaNotificacion::with('versiones:id,plantilla_id,version,asunto,creado_en,autor_id')->findOrFail($id);
        return response()->json([
            'data' => $p,
            'variables_base' => self::VARIABLES_BASE,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, null);
        $data['version'] = 1;
        $data['actualizado_por'] = optional($request->user())->id;

        $p = DB::transaction(function () use ($data, $request) {
            $p = PlantillaNotificacion::create($data);
            PlantillaNotificacionVersion::create([
                'plantilla_id' => $p->id, 'version' => 1,
                'asunto' => $p->asunto, 'cuerpo' => $p->cuerpo,
                'autor_id' => optional($request->user())->id,
                'creado_en' => now(),
            ]);
            return $p;
        });
        return response()->json(['data' => $p], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = PlantillaNotificacion::findOrFail($id);
        $data = $this->validatePayload($request, $id);

        DB::transaction(function () use ($p, $data, $request) {
            $cambioContenido = $data['cuerpo'] !== $p->cuerpo || ($data['asunto'] ?? null) !== $p->asunto;
            if ($cambioContenido) {
                $data['version'] = $p->version + 1;
                PlantillaNotificacionVersion::create([
                    'plantilla_id' => $p->id, 'version' => $data['version'],
                    'asunto' => $data['asunto'] ?? null, 'cuerpo' => $data['cuerpo'],
                    'autor_id' => optional($request->user())->id,
                    'creado_en' => now(),
                ]);
            }
            $data['actualizado_por'] = optional($request->user())->id;
            $p->update($data);
        });

        return response()->json(['data' => $p->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        PlantillaNotificacion::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $p = PlantillaNotificacion::findOrFail($id);
        $vars = (array) $request->input('variables', []);
        $body = $p->cuerpo;
        $subj = $p->asunto;
        foreach ($vars as $k => $v) {
            if (! is_string($k)) continue;
            $needle = '{{' . $k . '}}';
            $val = is_scalar($v) ? (string) $v : json_encode($v);
            $body = str_replace($needle, $val, $body);
            if ($subj) $subj = str_replace($needle, $val, $subj);
        }
        return response()->json(['data' => ['asunto' => $subj, 'cuerpo' => $body]]);
    }

    private function validatePayload(Request $request, ?int $id): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:100', Rule::unique('plantillas_notificacion', 'codigo')->ignore($id)],
            'canal' => ['required', 'in:email,whatsapp,sms,push'],
            'nombre' => ['required', 'string', 'max:191'],
            'asunto' => ['nullable', 'string', 'max:191'],
            'cuerpo' => ['required', 'string'],
            'variables_disponibles' => ['nullable', 'array'],
            'activa' => ['sometimes', 'boolean'],
        ]);
    }
}
