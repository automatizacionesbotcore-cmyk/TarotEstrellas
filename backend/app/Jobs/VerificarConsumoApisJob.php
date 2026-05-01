<?php

namespace App\Jobs;

use App\Mail\AlertaConsumoApiMail;
use App\Models\ApiUsageAlert;
use App\Models\NotificacionEnviada;
use App\Services\ApiUsageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VerificarConsumoApisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ApiUsageService $svc): void
    {
        $snap = $svc->snapshot();
        $periodo = $snap['periodo'];
        $recipients = $svc->getAlertRecipients();

        foreach ($snap['rows'] as $row) {
            if (! in_array($row['estado'], ['warning', 'exceeded'], true)) {
                continue;
            }

            // Idempotencia: una alerta por (provider, period, nivel)
            $existed = ApiUsageAlert::query()
                ->where('provider', $row['provider'])
                ->where('period', $periodo)
                ->where('nivel', $row['estado'])
                ->exists();
            if ($existed) continue;

            ApiUsageAlert::create([
                'provider'      => $row['provider'],
                'period'        => $periodo,
                'nivel'         => $row['estado'],
                'valor_actual'  => $row['usado'],
                'limite'        => $row['limite'],
                'porcentaje'    => $row['porcentaje'],
                'unidad'        => $row['unidad'],
                'notificado_en' => now(),
            ]);

            // Email a super_admin + admin_especialista
            if (! empty($recipients)) {
                Mail::to($recipients)->send(new AlertaConsumoApiMail($row, $periodo));
            }

            // Notificación in-app (canal=in_app) por destinatario
            foreach ($recipients as $email) {
                NotificacionEnviada::create([
                    'uuid'         => (string) Str::uuid(),
                    'canal'        => 'in_app',
                    'tipo'         => 'alerta_api_'.$row['estado'],
                    'destinatario' => $email,
                    'asunto'       => "API {$row['provider']} · {$row['porcentaje']}%",
                    'preview'      => "Consumo {$row['usado']} / {$row['limite']} {$row['unidad']} ({$row['porcentaje']}%)",
                    'estado'       => 'enviado',
                    'proveedor'    => 'sistema',
                    'metadata'     => $row + ['periodo' => $periodo],
                    'enviado_en'   => now(),
                ]);
            }
        }
    }
}
