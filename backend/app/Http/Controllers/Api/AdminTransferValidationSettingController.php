<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AppSettingAudit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTransferValidationSettingController extends Controller
{
    private const SETTING_KEYS = [
        'TRANSFERENCIA_BANCO',
        'TRANSFERENCIA_CUENTA',
        'TRANSFERENCIA_RUT',
        'MINUTOS_ANTIGUEDAD_COMPROBANTE',
    ];

    public function show(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden consultar configuraciones.',
            ], 403);
        }

        $settings = AppSetting::query()
            ->whereIn('key', self::SETTING_KEYS)
            ->orderBy('key')
            ->get(['key', 'value', 'editable_admin', 'updated_at']);

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden actualizar configuraciones.',
            ], 403);
        }

        $validated = $request->validate([
            'TRANSFERENCIA_BANCO' => ['sometimes', 'string', 'max:100'],
            'TRANSFERENCIA_CUENTA' => ['sometimes', 'string', 'max:50'],
            'TRANSFERENCIA_RUT' => ['sometimes', 'string', 'max:20'],
            'MINUTOS_ANTIGUEDAD_COMPROBANTE' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        if (empty($validated)) {
            return response()->json([
                'message' => 'No se recibieron cambios para aplicar.',
            ], 422);
        }

        foreach ($validated as $key => $value) {
            if (! in_array($key, self::SETTING_KEYS, true)) {
                continue;
            }

            $newValue = (string) $value;
            $setting = AppSetting::query()->firstOrNew(['key' => $key]);
            $oldValue = $setting->exists ? $setting->value : null;

            if ($setting->exists && (string) $oldValue === $newValue) {
                continue;
            }

            $setting->category = 'pagos_transferencia';
            $setting->value = $newValue;
            $setting->editable_admin = true;
            $setting->save();

            AppSettingAudit::query()->create([
                'app_setting_id' => $setting->id,
                'key' => $key,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed_by_user_id' => $admin->id,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'changed_at' => now(),
            ]);
        }

        $updated = AppSetting::query()
            ->whereIn('key', self::SETTING_KEYS)
            ->orderBy('key')
            ->get(['key', 'value', 'editable_admin', 'updated_at']);

        return response()->json([
            'message' => 'Configuracion de validacion de transferencias actualizada.',
            'data' => $updated,
        ]);
    }

    public function audits(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden consultar auditoria de configuraciones.',
            ], 403);
        }

        $validated = $this->validateAuditFilters($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $query = $this->buildAuditQuery($validated);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $audits = $query->paginate($perPage);

        return response()->json($audits);
    }

    public function exportAudits(Request $request)
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden exportar auditoria de configuraciones.',
            ], 403);
        }

        $validated = $this->validateAuditFilters($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $rows = $this->buildAuditQuery($validated)
            ->limit(5000)
            ->get();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'id',
            'key',
            'old_value',
            'new_value',
            'changed_by_user_id',
            'changed_by_name',
            'ip_address',
            'changed_at',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row->id,
                $row->key,
                $row->old_value,
                $row->new_value,
                $row->changed_by_user_id,
                optional($row->changedBy)->name,
                $row->ip_address,
                optional($row->changed_at)->toDateTimeString(),
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $filename = 'transfer-validation-audits-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function revertAudit(Request $request, int $auditId): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'message' => 'Solo administradores pueden revertir configuraciones.',
            ], 403);
        }

        $audit = AppSettingAudit::query()
            ->where('id', $auditId)
            ->whereIn('key', self::SETTING_KEYS)
            ->first();

        if (! $audit) {
            return response()->json([
                'message' => 'No se encontro el registro de auditoria solicitado.',
            ], 404);
        }

        $result = DB::transaction(function () use ($audit, $admin, $request) {
            $setting = AppSetting::query()->firstOrNew(['key' => $audit->key]);
            $currentValue = $setting->exists ? $setting->value : null;
            $targetValue = $audit->old_value;

            if ((string) $currentValue === (string) $targetValue) {
                return [
                    'changed' => false,
                    'setting' => $setting,
                ];
            }

            $setting->category = 'pagos_transferencia';
            $setting->value = $targetValue;
            $setting->editable_admin = true;
            $setting->save();

            AppSettingAudit::query()->create([
                'app_setting_id' => $setting->id,
                'key' => $audit->key,
                'old_value' => $currentValue,
                'new_value' => $targetValue,
                'changed_by_user_id' => $admin->id,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'changed_at' => now(),
            ]);

            return [
                'changed' => true,
                'setting' => $setting,
            ];
        });

        return response()->json([
            'message' => $result['changed']
                ? 'Configuracion revertida correctamente.'
                : 'No se realizaron cambios: la configuracion ya tenia el valor objetivo.',
            'data' => [
                'key' => $result['setting']->key,
                'value' => $result['setting']->value,
                'editable_admin' => (bool) $result['setting']->editable_admin,
                'updated_at' => $result['setting']->updated_at,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function validateAuditFilters(Request $request)
    {
        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'in:'.implode(',', self::SETTING_KEYS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'changed_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
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

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildAuditQuery(array $filters): Builder
    {
        $query = AppSettingAudit::query()
            ->whereIn('key', self::SETTING_KEYS)
            ->with(['changedBy:id,name,email'])
            ->orderByDesc('changed_at');

        if (! empty($filters['key'])) {
            $query->where('key', $filters['key']);
        }

        if (! empty($filters['changed_by_user_id'])) {
            $query->where('changed_by_user_id', $filters['changed_by_user_id']);
        }

        if (! empty($filters['from_date'])) {
            $from = Carbon::createFromFormat('Y-m-d', $filters['from_date'])->startOfDay();
            $query->where('changed_at', '>=', $from);
        }

        if (! empty($filters['to_date'])) {
            $to = Carbon::createFromFormat('Y-m-d', $filters['to_date'])->endOfDay();
            $query->where('changed_at', '<=', $to);
        }

        return $query;
    }
}
