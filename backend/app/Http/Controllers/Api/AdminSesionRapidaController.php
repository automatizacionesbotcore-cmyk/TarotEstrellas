<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SalaRapidaAbiertaMail;
use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use App\Services\DailyRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AdminSesionRapidaController extends Controller
{
    public function __construct(private readonly DailyRoomService $daily)
    {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user || ! ($user->hasRole('super_admin') || $user->hasRole('admin_especialista'))) {
            return response()->json(['message' => 'Solo superadmin o especialista puede abrir una sala rapida.'], 403);
        }

        $validated = $request->validate([
            'cliente_uuid' => ['required', 'string', 'uuid'],
            'tipo_consulta_id' => ['required', 'integer', 'exists:tipos_consulta,id'],
            'duracion_minutos' => ['nullable', 'integer', 'min:15', 'max:240'],
            'tema_principal' => ['nullable', 'string', 'max:80'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
            'grabacion_solicitada' => ['nullable', 'boolean'],
        ]);

        $cliente = User::query()
            ->where('uuid', $validated['cliente_uuid'])
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'cliente'))
            ->with('profile')
            ->firstOrFail();

        $tipo = TipoConsulta::query()
            ->whereKey($validated['tipo_consulta_id'])
            ->where('activo', true)
            ->firstOrFail();

        $inicio = now();
        $duracion = (int) ($validated['duracion_minutos'] ?? $tipo->duracion_minutos ?? 60);
        $fin = $inicio->copy()->addMinutes($duracion);
        $precio = (int) ($tipo->precio_referencial_centavos ?? 0);

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => $this->generateReferenceCode(),
            'cliente_id' => $cliente->id,
            'especialista_id' => $user->hasRole('admin_especialista') ? $user->id : null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $inicio,
            'fin_utc' => $fin,
            'duracion_minutos' => $duracion,
            'zona_horaria_cliente' => $cliente->profile?->zona_horaria ?? 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'sesion_rapida',
            'precio_total_centavos' => $precio,
            'precio_final_centavos' => $precio,
            'moneda' => $tipo->moneda ?? 'CLP',
            'tema_principal' => $validated['tema_principal'] ?? 'Consulta rapida',
            'notas_chachita' => $validated['mensaje'] ?? null,
            'confirmada_en' => now(),
            'grabacion_solicitada' => (bool) ($validated['grabacion_solicitada'] ?? true),
            'grabacion_extra_centavos' => 0,
            'es_primera_consulta' => ! Cita::query()
                ->where('cliente_id', $cliente->id)
                ->whereIn('estado', ['confirmada', 'finalizada', 'completada'])
                ->exists(),
        ]);

        try {
            $sala = $this->daily->crearSala($cita);
            $cita->refresh()->load(['cliente.profile', 'especialista.profile', 'tipoConsulta']);
            Mail::to($cliente->email)->send(new SalaRapidaAbiertaMail($cita, $validated['mensaje'] ?? null));
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'La cita fue creada, pero no fue posible preparar o notificar la sala: ' . $e->getMessage(),
                'data' => ['cita_uuid' => $cita->uuid],
            ], 502);
        }

        return response()->json([
            'message' => 'Sala rapida abierta y notificada correctamente.',
            'data' => [
                'cita_uuid' => $cita->uuid,
                'codigo_referencia' => $cita->codigo_referencia,
                'room_url' => $sala['room_url'],
                'room_name' => $sala['room_name'],
                'cliente_email' => $cliente->email,
                'sala_app_url' => rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/') . '/app/sala/' . $cita->uuid,
            ],
        ], 201);
    }

    private function generateReferenceCode(): string
    {
        do {
            $code = 'TE-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (Cita::query()->where('codigo_referencia', $code)->exists());

        return $code;
    }
}
