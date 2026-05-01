<?php

namespace App\Services;

use App\Models\AgenteConversacion;
use App\Models\AppSetting;
use App\Models\Cita;
use App\Support\AgenteCostos;
use Carbon\CarbonImmutable;

class ApiUsageService
{
    /** Defaults (USD / minutos por mes) si no hay AppSetting. */
    public const DEFAULTS = [
        'anthropic' => ['unidad' => 'usd',     'limite' => 50.0],
        'openai'    => ['unidad' => 'usd',     'limite' => 50.0],
        'daily'     => ['unidad' => 'minutes', 'limite' => 5000.0],
    ];

    /** Nombres de claves en app_settings. */
    public const SETTINGS_KEY = 'api_limits';      // valor JSON: {"anthropic":{"limite":50,"warn_pct":80}, ...}
    public const ALERT_EMAILS = 'api_alert_emails';// CSV de destinatarios extra

    public function getLimits(): array
    {
        $raw = AppSetting::query()->where('key', self::SETTINGS_KEY)->value('value');
        $cfg = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

        $out = [];
        foreach (self::DEFAULTS as $prov => $def) {
            $out[$prov] = [
                'unidad'   => $def['unidad'],
                'limite'   => (float) ($cfg[$prov]['limite']   ?? $def['limite']),
                'warn_pct' => (float) ($cfg[$prov]['warn_pct'] ?? 80),
            ];
        }
        return $out;
    }

    /** Costo USD por proveedor LLM (anthropic|openai) en el mes dado. */
    public function getLlmUsage(string $proveedor, ?CarbonImmutable $mes = null): float
    {
        $mes = $mes ?: CarbonImmutable::now();
        $rows = AgenteConversacion::query()
            ->where('proveedor', $proveedor)
            ->whereBetween('created_at', [$mes->startOfMonth(), $mes->endOfMonth()])
            ->select('modelo', \DB::raw('COALESCE(SUM(tokens_in),0)  as tin'),
                     \DB::raw('COALESCE(SUM(tokens_out),0) as tout'))
            ->groupBy('modelo')
            ->get();

        $total = 0.0;
        foreach ($rows as $r) {
            $total += AgenteCostos::costo($r->modelo, (int) $r->tin, (int) $r->tout);
        }
        return round($total, 4);
    }

    /** Minutos consumidos en Daily.co (aprox: suma duración_minutos de citas confirmadas/realizadas en el mes). */
    public function getDailyMinutes(?CarbonImmutable $mes = null): int
    {
        $mes = $mes ?: CarbonImmutable::now();
        return (int) Cita::query()
            ->whereIn('estado', ['confirmada', 'realizada', 'finalizada'])
            ->whereBetween('inicio_utc', [$mes->startOfMonth(), $mes->endOfMonth()])
            ->sum('duracion_minutos');
    }

    /** Snapshot completo para dashboard. */
    public function snapshot(?CarbonImmutable $mes = null): array
    {
        $mes = $mes ?: CarbonImmutable::now();
        $limits = $this->getLimits();
        $rows = [];

        foreach ($limits as $prov => $cfg) {
            $usado = match ($prov) {
                'anthropic', 'openai' => $this->getLlmUsage($prov, $mes),
                'daily'               => (float) $this->getDailyMinutes($mes),
                default               => 0.0,
            };
            $pct = $cfg['limite'] > 0 ? round(($usado / $cfg['limite']) * 100, 2) : 0.0;
            $estado = match (true) {
                $pct >= 100             => 'exceeded',
                $pct >= $cfg['warn_pct']=> 'warning',
                default                 => 'ok',
            };
            $rows[] = [
                'provider'   => $prov,
                'unidad'     => $cfg['unidad'],
                'usado'      => $usado,
                'limite'     => $cfg['limite'],
                'porcentaje' => $pct,
                'warn_pct'   => $cfg['warn_pct'],
                'estado'     => $estado,
            ];
        }

        return [
            'periodo' => $mes->format('Y-m'),
            'desde'   => $mes->startOfMonth()->toDateTimeString(),
            'hasta'   => $mes->endOfMonth()->toDateTimeString(),
            'rows'    => $rows,
        ];
    }

    /** Destinatarios para alertas: super_admins + admin_especialistas + extras configurados. */
    public function getAlertRecipients(): array
    {
        $extra = AppSetting::query()->where('key', self::ALERT_EMAILS)->value('value');
        $extraList = is_string($extra) ? array_filter(array_map('trim', explode(',', $extra))) : [];

        $emails = \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('nombre', ['super_admin', 'admin_especialista']))
            ->pluck('email')
            ->all();

        return array_values(array_unique(array_merge($emails, $extraList)));
    }
}
