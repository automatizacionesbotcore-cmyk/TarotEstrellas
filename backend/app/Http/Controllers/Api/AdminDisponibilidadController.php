<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloqueoAgenda;
use App\Models\DisponibilidadBase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDisponibilidadController extends Controller
{
    // ─── Horario base ────────────────────────────────────────────────────────

    public function indexHorario(): JsonResponse
    {
        $especialista = $this->especialista();

        $horarios = DisponibilidadBase::query()
            ->where('especialista_id', $especialista->id)
            ->orderBy('dia_semana')
            ->get(['id', 'dia_semana', 'hora_inicio', 'hora_fin', 'activo']);

        return response()->json(['data' => $horarios]);
    }

    public function upsertHorario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'horarios' => ['required', 'array'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:0,6'],
            'horarios.*.hora_inicio' => ['required', 'date_format:H:i'],
            'horarios.*.hora_fin' => ['required', 'date_format:H:i', 'after:horarios.*.hora_inicio'],
            'horarios.*.activo' => ['required', 'boolean'],
        ]);

        $especialista = $this->especialista();

        foreach ($validated['horarios'] as $h) {
            DisponibilidadBase::query()->updateOrCreate(
                ['especialista_id' => $especialista->id, 'dia_semana' => $h['dia_semana']],
                ['hora_inicio' => $h['hora_inicio'].':00', 'hora_fin' => $h['hora_fin'].':00', 'activo' => $h['activo']]
            );
        }

        return response()->json(['message' => 'Horario actualizado']);
    }

    // ─── Bloqueos ─────────────────────────────────────────────────────────────

    public function indexBloqueos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $especialista = $this->especialista();

        $query = BloqueoAgenda::query()
            ->where('especialista_id', $especialista->id)
            ->orderBy('fecha_inicio_utc');

        if (! empty($validated['desde'])) {
            $query->where('fecha_fin_utc', '>=', $validated['desde'].' 00:00:00');
        }
        if (! empty($validated['hasta'])) {
            $query->where('fecha_inicio_utc', '<=', $validated['hasta'].' 23:59:59');
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeBloqueo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', 'in:bloqueo,apertura_extra'],
            'motivo' => ['required', 'in:feriado,vacaciones,descanso,emergencia,evento,otro'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d H:i'],
            'fecha_fin' => ['required', 'date_format:Y-m-d H:i', 'after:fecha_inicio'],
            'all_day' => ['boolean'],
        ]);

        $especialista = $this->especialista();

        $bloqueo = BloqueoAgenda::query()->create([
            'especialista_id' => $especialista->id,
            'tipo' => $validated['tipo'],
            'motivo' => $validated['motivo'],
            'descripcion' => $validated['descripcion'] ?? null,
            'fecha_inicio_utc' => $validated['fecha_inicio'].':00',
            'fecha_fin_utc' => $validated['fecha_fin'].':00',
            'all_day' => $validated['all_day'] ?? false,
        ]);

        return response()->json(['data' => $bloqueo], 201);
    }

    public function updateBloqueo(Request $request, int $id): JsonResponse
    {
        $especialista = $this->especialista();

        $bloqueo = BloqueoAgenda::query()
            ->where('especialista_id', $especialista->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'tipo' => ['sometimes', 'in:bloqueo,apertura_extra'],
            'motivo' => ['sometimes', 'in:feriado,vacaciones,descanso,emergencia,evento,otro'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['sometimes', 'date_format:Y-m-d H:i'],
            'fecha_fin' => ['sometimes', 'date_format:Y-m-d H:i'],
            'all_day' => ['sometimes', 'boolean'],
        ]);

        $data = array_filter([
            'tipo' => $validated['tipo'] ?? null,
            'motivo' => $validated['motivo'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
            'all_day' => $validated['all_day'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($validated['fecha_inicio'])) {
            $data['fecha_inicio_utc'] = $validated['fecha_inicio'].':00';
        }
        if (isset($validated['fecha_fin'])) {
            $data['fecha_fin_utc'] = $validated['fecha_fin'].':00';
        }

        $bloqueo->update($data);

        return response()->json(['data' => $bloqueo->fresh()]);
    }

    public function destroyBloqueo(int $id): JsonResponse
    {
        $especialista = $this->especialista();

        BloqueoAgenda::query()
            ->where('especialista_id', $especialista->id)
            ->findOrFail($id)
            ->delete();

        return response()->json(null, 204);
    }

    private function especialista(): User
    {
        $request  = request();
        $authUser = $request->user();

        // super_admin puede gestionar cualquier especialista pasando especialista_id
        if ($authUser->hasRole('super_admin') && $request->has('especialista_id')) {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
                ->findOrFail((int) $request->input('especialista_id'));
        }

        // admin_especialista gestiona solo su propia agenda
        if ($authUser->hasRole('admin_especialista')) {
            return $authUser;
        }

        // super_admin sin especialista_id → primer especialista
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->firstOrFail();
    }
}
