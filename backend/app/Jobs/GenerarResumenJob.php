<?php

namespace App\Jobs;

use App\Models\Resumen;
use App\Models\Transcripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenerarResumenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $transcripcionId;

    public function __construct(int $transcripcionId)
    {
        $this->transcripcionId = $transcripcionId;
    }

    public function handle(): void
    {
        $transcripcion = Transcripcion::query()->with('grabacion')->find($this->transcripcionId);
        if (! $transcripcion || ! $transcripcion->grabacion) {
            return;
        }

        $apiKey = (string) config('services.anthropic.api_key', '');
        if ($apiKey === '') {
            $this->markSummaryError($transcripcion, 'ANTHROPIC_API_KEY no configurada para resumen IA.');
            return;
        }

        $model = (string) config('services.anthropic.summary_model', 'claude-3-5-haiku-latest');

        try {
            $response = Http::timeout(180)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 700,
                    'temperature' => 0.2,
                    'system' => 'Eres un asistente que resume sesiones esotericas en espanol, con tono empatico y claro.',
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Genera un resumen de 3 parrafos en espanol de la siguiente transcripcion:\n\n".$transcripcion->contenido,
                    ]],
                ]);

            if (! $response->successful()) {
                $this->markSummaryError($transcripcion, 'Anthropic devolvio un error al generar resumen.');
                return;
            }

            $summary = trim((string) data_get($response->json(), 'content.0.text', ''));
            if ($summary === '') {
                $this->markSummaryError($transcripcion, 'Anthropic no devolvio contenido de resumen.');
                return;
            }

            $resumen = Resumen::query()->updateOrCreate(
                ['transcripcion_id' => $transcripcion->id],
                [
                    'cita_id' => $transcripcion->cita_id,
                    'grabacion_id' => $transcripcion->grabacion_id,
                    'contenido' => $summary,
                    'proveedor' => 'anthropic',
                    'modelo' => $model,
                    'metadata' => [
                        'anthropic_response' => $response->json(),
                    ],
                ]
            );

            NotificarResumenListoJob::dispatch($resumen->id);

            $transcripcion->grabacion->forceFill([
                'estado' => 'resumen_completado',
                'resumen_generado_en' => now(),
                'metadata' => array_merge((array) ($transcripcion->grabacion->metadata ?? []), [
                    'resumen' => [
                        'status' => 'ok',
                        'processed_at' => now()->toIso8601String(),
                    ],
                ]),
            ])->save();
        } catch (Throwable $e) {
            $this->markSummaryError($transcripcion, 'Error inesperado al generar resumen: '.$e->getMessage());
        }
    }

    private function markSummaryError(Transcripcion $transcripcion, string $message): void
    {
        $grabacion = $transcripcion->grabacion;
        if (! $grabacion) {
            return;
        }

        $grabacion->forceFill([
            'estado' => 'error_resumen',
            'metadata' => array_merge((array) ($grabacion->metadata ?? []), [
                'resumen' => [
                    'status' => 'error',
                    'message' => $message,
                    'processed_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }
}