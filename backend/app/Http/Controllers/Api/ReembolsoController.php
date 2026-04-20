<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarReembolsoJob;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\Reembolso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReembolsoController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden consultar metricas de reembolsos.',
            ], 403);
        }

        $validated = $request->validate([
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d'],
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

        $baseQuery = Reembolso::query();

        if (! empty($validated['from_date'])) {
            $baseQuery->where('created_at', '>=', now()->parse($validated['from_date'])->startOfDay());
        }

        if (! empty($validated['to_date'])) {
            $baseQuery->where('created_at', '<=', now()->parse($validated['to_date'])->endOfDay());
        }

        $total = (int) (clone $baseQuery)->count();
        $montoTotal = (int) ((clone $baseQuery)->sum('monto_centavos'));

        $statusRows = (clone $baseQuery)
            ->selectRaw('estado, COUNT(*) as total, COALESCE(SUM(monto_centavos), 0) as monto_total')
            ->groupBy('estado')
            ->get();

        $methodRows = (clone $baseQuery)
            ->selectRaw('metodo, COUNT(*) as total, COALESCE(SUM(monto_centavos), 0) as monto_total')
            ->groupBy('metodo')
            ->get();

        $estados = ['pendiente', 'completado', 'fallido'];
        $metodos = ['mismo_medio_pago', 'transferencia_manual', 'credito_cliente'];

        $byStatus = [];
        foreach ($estados as $estado) {
            $row = $statusRows->firstWhere('estado', $estado);
            $byStatus[$estado] = [
                'total' => (int) ($row->total ?? 0),
                'monto_centavos' => (int) ($row->monto_total ?? 0),
            ];
        }

        $byMethod = [];
        foreach ($metodos as $metodo) {
            $row = $methodRows->firstWhere('metodo', $metodo);
            $byMethod[$metodo] = [
                'total' => (int) ($row->total ?? 0),
                'monto_centavos' => (int) ($row->monto_total ?? 0),
            ];
        }

        return response()->json([
            'data' => [
                'total' => $total,
                'monto_total_centavos' => $montoTotal,
                'by_status' => $byStatus,
                'by_method' => $byMethod,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden consultar reembolsos.',
            ], 403);
        }

        $validated = $request->validate([
            'estado' => ['sometimes', 'in:pendiente,completado,fallido'],
            'razon' => ['sometimes', 'string', 'max:50'],
            'metodo' => ['sometimes', 'in:mismo_medio_pago,transferencia_manual,credito_cliente'],
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

        $query = Reembolso::query()
            ->with([
                'cita:id,uuid,codigo_referencia,cliente_id,estado',
                'cliente:id,uuid,email,name',
                'pago:id,uuid,stripe_payment_intent_id,stripe_charge_id,canal,estado,monto_centavos',
            ])
            ->latest('created_at');

        if (! empty($validated['estado'])) {
            $query->where('estado', $validated['estado']);
        }

        if (! empty($validated['razon'])) {
            $query->where('razon', $validated['razon']);
        }

        if (! empty($validated['metodo'])) {
            $query->where('metodo', $validated['metodo']);
        }

        if (! empty($validated['from_date'])) {
            $query->where('created_at', '>=', now()->parse($validated['from_date'])->startOfDay());
        }

        if (! empty($validated['to_date'])) {
            $query->where('created_at', '<=', now()->parse($validated['to_date'])->endOfDay());
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        return response()->json($query->paginate($perPage));
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden exportar reembolsos.',
            ], 403);
        }

        $validated = $request->validate([
            'estado' => ['sometimes', 'in:pendiente,completado,fallido'],
            'razon' => ['sometimes', 'string', 'max:50'],
            'metodo' => ['sometimes', 'in:mismo_medio_pago,transferencia_manual,credito_cliente'],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d'],
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

        $query = Reembolso::query()
            ->with([
                'cita:id,uuid,codigo_referencia',
                'cliente:id,uuid,email',
                'pago:id,uuid',
            ])
            ->orderBy('id');

        if (! empty($validated['estado'])) {
            $query->where('estado', $validated['estado']);
        }

        if (! empty($validated['razon'])) {
            $query->where('razon', $validated['razon']);
        }

        if (! empty($validated['metodo'])) {
            $query->where('metodo', $validated['metodo']);
        }

        if (! empty($validated['from_date'])) {
            $query->where('created_at', '>=', now()->parse($validated['from_date'])->startOfDay());
        }

        if (! empty($validated['to_date'])) {
            $query->where('created_at', '<=', now()->parse($validated['to_date'])->endOfDay());
        }

        $filename = 'reembolsos-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'uuid',
                'cita_uuid',
                'codigo_referencia',
                'cliente_uuid',
                'cliente_email',
                'pago_uuid',
                'estado',
                'razon',
                'metodo',
                'monto_centavos',
                'moneda',
                'solicitado_en',
                'procesado_en',
                'created_at',
            ]);

            foreach ($query->cursor() as $reembolso) {
                fputcsv($handle, [
                    (string) $reembolso->uuid,
                    (string) data_get($reembolso, 'cita.uuid', ''),
                    (string) data_get($reembolso, 'cita.codigo_referencia', ''),
                    (string) data_get($reembolso, 'cliente.uuid', ''),
                    (string) data_get($reembolso, 'cliente.email', ''),
                    (string) data_get($reembolso, 'pago.uuid', ''),
                    (string) $reembolso->estado,
                    (string) $reembolso->razon,
                    (string) $reembolso->metodo,
                    (int) $reembolso->monto_centavos,
                    (string) $reembolso->moneda,
                    optional($reembolso->solicitado_en)?->toIso8601String(),
                    optional($reembolso->procesado_en)?->toIso8601String(),
                    optional($reembolso->created_at)?->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden ver detalle de reembolsos.',
            ], 403);
        }

        $reembolso = Reembolso::query()
            ->with([
                'cita:id,uuid,codigo_referencia,estado,cliente_id',
                'cliente:id,uuid,email,name',
                'pago:id,uuid,estado,canal,monto_centavos,stripe_payment_intent_id,stripe_charge_id',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $metadata = (array) ($reembolso->metadata ?? []);
        $timeline = [];

        $errorMessage = data_get($metadata, 'stripe.error.message')
            ?? data_get($metadata, 'error.message')
            ?? data_get($metadata, 'error_details.message')
            ?? data_get($metadata, 'error')
            ?? data_get($metadata, 'stripe_body.error.message');

        $errorCode = data_get($metadata, 'stripe.error.code')
            ?? data_get($metadata, 'error.code')
            ?? data_get($metadata, 'stripe_body.error.code');

        $errorType = data_get($metadata, 'stripe.error.type')
            ?? data_get($metadata, 'error.type')
            ?? data_get($metadata, 'stripe_body.error.type');

        $stripeRefundId = data_get($metadata, 'stripe.refund_id')
            ?? data_get($metadata, 'stripe_refund_id')
            ?? data_get($metadata, 'stripe_response.id');

        $stripeStatus = data_get($metadata, 'stripe.status')
            ?? data_get($metadata, 'stripe_status')
            ?? data_get($metadata, 'stripe_response.status');

        $stripeBalanceTx = data_get($metadata, 'stripe.balance_transaction')
            ?? data_get($metadata, 'stripe_response.balance_transaction');

        $timeline[] = [
            'tipo' => 'creado',
            'timestamp' => optional($reembolso->created_at)->toIso8601String(),
            'actor' => data_get($metadata, 'creado_por_admin_id') ? 'admin:'.data_get($metadata, 'creado_por_admin_id') : 'system',
            'detalle' => [
                'estado' => $reembolso->estado,
                'razon' => $reembolso->razon,
                'metodo' => $reembolso->metodo,
                'nota_admin' => data_get($metadata, 'nota_admin'),
            ],
        ];

        if (data_get($metadata, 'reintento_por_admin_id')) {
            $timeline[] = [
                'tipo' => 'reintento',
                'timestamp' => data_get($metadata, 'reintento_en')
                    ?? optional($reembolso->updated_at)->toIso8601String(),
                'actor' => 'admin:'.data_get($metadata, 'reintento_por_admin_id'),
                'detalle' => [
                    'nota_admin' => data_get($metadata, 'nota_admin'),
                ],
            ];
        }

        if (data_get($metadata, 'procesado_manual_por_admin_id')) {
            $timeline[] = [
                'tipo' => 'procesado_manual',
                'timestamp' => data_get($metadata, 'procesado_manual_en')
                    ?? optional($reembolso->procesado_en)->toIso8601String(),
                'actor' => 'admin:'.data_get($metadata, 'procesado_manual_por_admin_id'),
                'detalle' => [
                    'referencia_manual' => data_get($metadata, 'referencia_manual'),
                    'nota_admin' => data_get($metadata, 'nota_admin'),
                ],
            ];
        }

        if ($stripeRefundId) {
            $timeline[] = [
                'tipo' => 'procesado_stripe',
                'timestamp' => data_get($metadata, 'stripe.processed_at')
                    ?? optional($reembolso->procesado_en)->toIso8601String(),
                'actor' => 'system',
                'detalle' => [
                    'refund_id' => $stripeRefundId,
                    'status' => $stripeStatus,
                    'balance_transaction' => $stripeBalanceTx,
                ],
            ];
        }

        if ($errorMessage) {
            $timeline[] = [
                'tipo' => 'fallo_procesamiento',
                'timestamp' => data_get($metadata, 'error.at')
                    ?? data_get($metadata, 'error_details.at')
                    ?? optional($reembolso->updated_at)->toIso8601String(),
                'actor' => 'system',
                'detalle' => [
                    'message' => $errorMessage,
                    'code' => $errorCode,
                    'type' => $errorType,
                ],
            ];
        }

        usort($timeline, function (array $a, array $b) {
            return strcmp((string) ($a['timestamp'] ?? ''), (string) ($b['timestamp'] ?? ''));
        });

        return response()->json([
            'data' => [
                'reembolso' => $reembolso,
                'timeline' => $timeline,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden crear reembolsos manuales.',
            ], 403);
        }

        $validated = $request->validate([
            'cita_uuid' => ['required', 'string', 'exists:citas,uuid'],
            'pago_uuid' => ['nullable', 'string', 'exists:pagos,uuid'],
            'monto_centavos' => ['required', 'integer', 'min:1'],
            'moneda' => ['required', 'string', 'size:3'],
            'razon' => ['required', 'string', 'max:50'],
            'metodo' => ['required', 'in:mismo_medio_pago,transferencia_manual,credito_cliente'],
            'nota_admin' => ['nullable', 'string', 'max:500'],
        ]);

        $cita = Cita::query()->where('uuid', $validated['cita_uuid'])->firstOrFail();

        $pago = null;
        if (! empty($validated['pago_uuid'])) {
            $pago = Pago::query()->where('uuid', $validated['pago_uuid'])->firstOrFail();

            if ((int) $pago->cita_id !== (int) $cita->id) {
                return response()->json([
                    'message' => 'El pago indicado no pertenece a la cita seleccionada.',
                ], 422);
            }
        }

        $reembolso = Reembolso::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'cliente_id' => $cita->cliente_id,
            'pago_id' => $pago ? $pago->id : null,
            'monto_centavos' => (int) $validated['monto_centavos'],
            'moneda' => strtoupper((string) $validated['moneda']),
            'estado' => 'pendiente',
            'razon' => $validated['razon'],
            'metodo' => $validated['metodo'],
            'solicitado_en' => now(),
            'metadata' => [
                'creado_por_admin_id' => $admin->id,
                'nota_admin' => $validated['nota_admin'] ?? null,
            ],
        ]);

        return response()->json([
            'message' => 'Reembolso manual creado correctamente.',
            'data' => $reembolso->fresh(['cita:id,uuid,codigo_referencia', 'cliente:id,uuid,email,name', 'pago:id,uuid,canal,estado']),
        ], 201);
    }

    public function procesar(Request $request, string $uuid): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden procesar reembolsos.',
            ], 403);
        }

        $validated = $request->validate([
            'accion' => ['sometimes', 'in:procesar,reintentar,marcar_completado'],
            'referencia_manual' => ['nullable', 'string', 'max:120', 'required_if:accion,marcar_completado'],
            'nota_admin' => ['nullable', 'string', 'max:500'],
        ]);

        $reembolso = Reembolso::query()->with('pago')->where('uuid', $uuid)->firstOrFail();
        $accion = (string) ($validated['accion'] ?? 'procesar');

        if ($accion === 'marcar_completado') {
            if ($reembolso->estado === 'completado') {
                return response()->json([
                    'message' => 'El reembolso ya estaba completado.',
                    'data' => $reembolso,
                ]);
            }

            $reembolso->forceFill([
                'estado' => 'completado',
                'procesado_en' => now(),
                'metadata' => array_merge((array) ($reembolso->metadata ?? []), [
                    'referencia_manual' => $validated['referencia_manual'] ?? null,
                    'procesado_manual_por_admin_id' => $admin->id,
                    'procesado_manual_en' => now()->toIso8601String(),
                    'nota_admin' => $validated['nota_admin'] ?? null,
                ]),
            ])->save();

            return response()->json([
                'message' => 'Reembolso marcado como completado manualmente.',
                'data' => $reembolso,
            ]);
        }

        if ($accion === 'reintentar' && $reembolso->estado === 'fallido') {
            $reembolso->forceFill([
                'estado' => 'pendiente',
                'procesado_en' => null,
                'metadata' => array_merge((array) ($reembolso->metadata ?? []), [
                    'reintento_por_admin_id' => $admin->id,
                    'reintento_en' => now()->toIso8601String(),
                    'nota_admin' => $validated['nota_admin'] ?? null,
                ]),
            ])->save();
        }

        if ($reembolso->metodo === 'mismo_medio_pago') {
            ProcesarReembolsoJob::dispatch($reembolso->id);

            return response()->json([
                'message' => 'Reembolso encolado para procesamiento.',
                'data' => $reembolso->fresh(),
            ]);
        }

        return response()->json([
            'message' => 'El reembolso no es automatico. Usa accion=marcar_completado para cierre manual.',
            'data' => $reembolso,
        ], 422);
    }
}
