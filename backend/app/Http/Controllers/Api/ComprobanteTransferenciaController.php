<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComprobanteTransferencia;
use App\Models\User;
use App\Models\ValidacionAgente;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComprobanteTransferenciaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'estado_validacion' => ['sometimes', 'in:pendiente,aprobado_automatico,rechazado_automatico,revision_requerida,aprobado_manual,rechazado_manual'],
            'decision_manual' => ['sometimes', 'in:aprobado,rechazado,en_revision'],
            'transaccion_id' => ['sometimes', 'string', 'max:120'],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if (! empty($validated['from_date']) && ! empty($validated['to_date'])) {
            $fromDate = Carbon::createFromFormat('Y-m-d', $validated['from_date']);
            $toDate = Carbon::createFromFormat('Y-m-d', $validated['to_date']);

            if ($fromDate->greaterThan($toDate)) {
                return response()->json([
                    'message' => 'El rango de fechas es invalido: from_date no puede ser mayor que to_date.',
                    'errors' => [
                        'from_date' => ['El rango de fechas es invalido.'],
                    ],
                ], 422);
            }
        }

        $query = ComprobanteTransferencia::query()
            ->with([
                'cita:id,uuid,cliente_id,estado,codigo_referencia',
                'pago:id,uuid,cita_id,tipo,canal,monto_centavos,estado',
                'validador:id,email',
            ])
            ->latest('created_at');

        if (! empty($validated['estado_validacion'])) {
            $query->where('estado_validacion', $validated['estado_validacion']);
        }

        if (! empty($validated['decision_manual'])) {
            if ($validated['decision_manual'] === 'aprobado') {
                $query->where('estado_validacion', 'aprobado_manual');
            } elseif ($validated['decision_manual'] === 'rechazado') {
                $query->where('estado_validacion', 'rechazado_manual');
            } elseif ($validated['decision_manual'] === 'en_revision') {
                $query->where('estado_validacion', 'revision_requerida');
            }
        }

        if (! empty($validated['transaccion_id'])) {
            $query->where('id_transaccion_bancaria', 'like', '%'.$validated['transaccion_id'].'%');
        }

        if (! empty($validated['from_date'])) {
            $query->where('created_at', '>=', now()->parse($validated['from_date'])->startOfDay());
        }

        if (! empty($validated['to_date'])) {
            $query->where('created_at', '<=', now()->parse($validated['to_date'])->endOfDay());
        }

        if (! $user->isAdmin()) {
            $query->whereHas('cita', function ($builder) use ($user) {
                $builder->where('cliente_id', $user->id);
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        return response()->json($query->paginate($perPage));
    }

    public function metrics(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden consultar metricas de comprobantes.',
            ], 403);
        }

        $baseQuery = ComprobanteTransferencia::query();
        $total = (int) $baseQuery->count();

        $byStatusRaw = ComprobanteTransferencia::query()
            ->selectRaw('estado_validacion, COUNT(*) as total')
            ->groupBy('estado_validacion')
            ->pluck('total', 'estado_validacion');

        $estados = [
            'pendiente',
            'aprobado_automatico',
            'rechazado_automatico',
            'revision_requerida',
            'aprobado_manual',
            'rechazado_manual',
        ];

        $byStatus = [];
        foreach ($estados as $estado) {
            $byStatus[$estado] = (int) ($byStatusRaw[$estado] ?? 0);
        }

        $last24h = (int) ComprobanteTransferencia::query()
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $manualQueue = (int) ComprobanteTransferencia::query()
            ->where('estado_validacion', 'revision_requerida')
            ->count();

        $manualResolved24h = (int) ComprobanteTransferencia::query()
            ->whereIn('estado_validacion', ['aprobado_manual', 'rechazado_manual'])
            ->where('validado_en', '>=', now()->subDay())
            ->count();

        return response()->json([
            'data' => [
                'total' => $total,
                'by_status' => $byStatus,
                'last_24h' => $last24h,
                'manual_queue' => $manualQueue,
                'manual_resolved_24h' => $manualResolved24h,
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $comprobante = ComprobanteTransferencia::query()
            ->with([
                'cita:id,uuid,cliente_id,estado,codigo_referencia',
                'pago:id,uuid,cita_id,tipo,canal,monto_centavos,estado',
                'validador:id,email',
                'validacionesAgente:id,comprobante_id,decision,razon,created_at',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $clienteIdCita = $comprobante->cita ? (int) $comprobante->cita->cliente_id : null;

        if (! $user->isAdmin() && $clienteIdCita !== (int) $user->id) {
            return response()->json([
                'message' => 'No autorizado para ver este comprobante.',
            ], 403);
        }

        return response()->json([
            'data' => $comprobante,
        ]);
    }

    public function validarManual(Request $request, string $uuid): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden validar comprobantes manualmente.',
            ], 403);
        }

        $validated = $request->validate([
            'estado_validacion' => ['required', 'in:aprobado_manual,rechazado_manual,revision_requerida'],
            'razon_rechazo' => ['nullable', 'string', 'max:500', 'required_if:estado_validacion,rechazado_manual'],
            'razon' => ['nullable', 'string', 'max:500'],
        ]);

        $comprobante = ComprobanteTransferencia::query()
            ->with(['cita:id,cliente_id'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $clienteId = $comprobante->cita ? $comprobante->cita->cliente_id : null;
        if (! $clienteId) {
            $clienteId = ValidacionAgente::query()
                ->where('comprobante_id', $comprobante->id)
                ->latest('created_at')
                ->value('cliente_id');
        }

        if (! $clienteId) {
            return response()->json([
                'message' => 'No fue posible resolver el cliente asociado al comprobante.',
            ], 422);
        }

        $comprobante->forceFill([
            'estado_validacion' => $validated['estado_validacion'],
            'validado_por' => $admin->id,
            'validado_en' => now(),
            'razon_rechazo' => $validated['estado_validacion'] === 'rechazado_manual'
                ? $validated['razon_rechazo']
                : null,
        ])->save();

        $decision = 'revision_manual';
        if ($validated['estado_validacion'] === 'aprobado_manual') {
            $decision = 'aprobar';
        } elseif ($validated['estado_validacion'] === 'rechazado_manual') {
            $decision = 'rechazar';
        }

        $razon = $validated['razon']
            ?? ($validated['estado_validacion'] === 'rechazado_manual'
                ? $validated['razon_rechazo']
                : 'Validacion manual de comprobante por administrador.');

        ValidacionAgente::query()->create([
            'comprobante_id' => $comprobante->id,
            'cliente_id' => $clienteId,
            'cita_id' => $comprobante->cita_id,
            'pago_id' => $comprobante->pago_id,
            'decision' => $decision,
            'razon' => $razon,
            'modelo_ia' => 'manual-admin-v1',
            'duracion_ms' => 0,
            'payload' => [
                'manual' => true,
                'admin_id' => $admin->id,
                'estado_validacion' => $validated['estado_validacion'],
            ],
        ]);

        return response()->json([
            'message' => 'Comprobante actualizado manualmente.',
            'data' => $comprobante->fresh(['validador:id,email']),
        ]);
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden aprobar comprobantes.',
            ], 403);
        }

        $comprobante = ComprobanteTransferencia::query()
            ->with(['cita:id,cliente_id'])
            ->findOrFail($id);

        $comprobante->forceFill([
            'estado_validacion' => 'aprobado_manual',
            'validado_por' => $admin->id,
            'validado_en' => now(),
            'razon_rechazo' => null,
        ])->save();

        $clienteId = ($comprobante->cita ? $comprobante->cita->cliente_id : null)
            ?? ValidacionAgente::query()
                ->where('comprobante_id', $comprobante->id)
                ->latest('created_at')
                ->value('cliente_id');

        if ($clienteId) {
            ValidacionAgente::query()->create([
                'comprobante_id' => $comprobante->id,
                'cliente_id' => $clienteId,
                'cita_id' => $comprobante->cita_id,
                'pago_id' => $comprobante->pago_id,
                'decision' => 'aprobar',
                'razon' => 'Aprobado manualmente por administrador.',
                'modelo_ia' => 'manual-admin-v1',
                'duracion_ms' => 0,
                'payload' => [
                    'manual' => true,
                    'admin_id' => $admin->id,
                    'accion' => 'aprobar',
                ],
            ]);
        }

        return response()->json([
            'message' => 'Comprobante aprobado manualmente.',
            'data' => $comprobante->fresh(['validador:id,email']),
        ]);
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden rechazar comprobantes.',
            ], 403);
        }

        $validated = $request->validate([
            'razon_rechazo' => ['required', 'string', 'max:500'],
        ]);

        $comprobante = ComprobanteTransferencia::query()
            ->with(['cita:id,cliente_id'])
            ->findOrFail($id);

        $comprobante->forceFill([
            'estado_validacion' => 'rechazado_manual',
            'validado_por' => $admin->id,
            'validado_en' => now(),
            'razon_rechazo' => $validated['razon_rechazo'],
        ])->save();

        $clienteId = ($comprobante->cita ? $comprobante->cita->cliente_id : null)
            ?? ValidacionAgente::query()
                ->where('comprobante_id', $comprobante->id)
                ->latest('created_at')
                ->value('cliente_id');

        if ($clienteId) {
            ValidacionAgente::query()->create([
                'comprobante_id' => $comprobante->id,
                'cliente_id' => $clienteId,
                'cita_id' => $comprobante->cita_id,
                'pago_id' => $comprobante->pago_id,
                'decision' => 'rechazar',
                'razon' => $validated['razon_rechazo'],
                'modelo_ia' => 'manual-admin-v1',
                'duracion_ms' => 0,
                'payload' => [
                    'manual' => true,
                    'admin_id' => $admin->id,
                    'accion' => 'rechazar',
                ],
            ]);
        }

        return response()->json([
            'message' => 'Comprobante rechazado manualmente.',
            'data' => $comprobante->fresh(['validador:id,email']),
        ]);
    }
}
