<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiUsageAlert;
use App\Models\AppSetting;
use App\Services\ApiUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApiUsageController extends Controller
{
    public function __construct(private readonly ApiUsageService $svc) {}

    public function index(Request $request): JsonResponse
    {
        $snap = $this->svc->snapshot();

        $alertas = ApiUsageAlert::query()
            ->orderByDesc('notificado_en')
            ->limit(50)
            ->get();

        return response()->json([
            ...$snap,
            'alertas_recientes' => $alertas,
            'destinatarios'     => $this->svc->getAlertRecipients(),
        ]);
    }

    public function updateLimits(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anthropic.limite'   => ['sometimes', 'numeric', 'min:0'],
            'anthropic.warn_pct' => ['sometimes', 'numeric', 'between:1,99'],
            'openai.limite'      => ['sometimes', 'numeric', 'min:0'],
            'openai.warn_pct'    => ['sometimes', 'numeric', 'between:1,99'],
            'daily.limite'       => ['sometimes', 'numeric', 'min:0'],
            'daily.warn_pct'     => ['sometimes', 'numeric', 'between:1,99'],
            'alert_emails'       => ['sometimes', 'string', 'max:1000'],
        ]);

        // Persistir límites
        $current = AppSetting::query()->where('key', ApiUsageService::SETTINGS_KEY)->value('value');
        $current = is_string($current) ? (json_decode($current, true) ?: []) : (is_array($current) ? $current : []);

        foreach (['anthropic', 'openai', 'daily'] as $prov) {
            if (isset($data[$prov])) {
                $current[$prov] = array_merge($current[$prov] ?? [], $data[$prov]);
            }
        }

        AppSetting::query()->updateOrCreate(
            ['key' => ApiUsageService::SETTINGS_KEY],
            ['category' => 'api', 'value' => json_encode($current), 'editable_admin' => true]
        );

        if (isset($data['alert_emails'])) {
            AppSetting::query()->updateOrCreate(
                ['key' => ApiUsageService::ALERT_EMAILS],
                ['category' => 'api', 'value' => $data['alert_emails'], 'editable_admin' => true]
            );
        }

        return response()->json([
            'message' => 'Límites actualizados.',
            'limits'  => $this->svc->getLimits(),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        \App\Jobs\VerificarConsumoApisJob::dispatchSync();
        return response()->json(['message' => 'Verificación ejecutada.']);
    }
}
