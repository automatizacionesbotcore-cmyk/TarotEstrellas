<?php

namespace App\Jobs;

use App\Jobs\GenerarResumenJob;
use App\Models\Grabacion;
use App\Models\Transcripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcesarTranscripcionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $grabacionId)
    {
    }

    public function handle(): void
    {
        $grabacion = Grabacion::query()->find($this->grabacionId);
        if (! $grabacion) {
            return;
        }

        if (! in_array($grabacion->estado, ['pendiente_transcripcion', 'procesando_transcripcion'], true)) {
            return;
        }

        $recordingUrl = (string) ($grabacion->url_grabacion ?? '');
        if ($recordingUrl === '') {
            $this->markAsError($grabacion, 'La grabacion no tiene URL para procesar transcripcion.');
            return;
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            $this->markAsError($grabacion, 'OPENAI_API_KEY no configurada para transcripcion.');
            return;
        }

        $grabacion->forceFill([
            'estado' => 'procesando_transcripcion',
        ])->save();

        $tempFile = tempnam(sys_get_temp_dir(), 'tarot_rec_');
        if ($tempFile === false) {
            $this->markAsError($grabacion, 'No fue posible crear archivo temporal para transcripcion.');
            return;
        }

        try {
            $download = Http::timeout(120)->get($recordingUrl);
            if (! $download->successful()) {
                $this->markAsError($grabacion, 'No fue posible descargar la grabacion para transcripcion.');
                return;
            }

            file_put_contents($tempFile, $download->body());

            $model = (string) config('services.openai.whisper_model', 'whisper-1');
            $response = Http::timeout(300)
                ->withToken($apiKey)
                ->attach('file', file_get_contents($tempFile) ?: '', 'recording.mp4')
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $model,
                    'response_format' => 'json',
                    'temperature' => 0,
                ]);

            if (! $response->successful()) {
                $this->markAsError(
                    $grabacion,
                    'OpenAI Whisper devolvio un error al procesar la transcripcion.',
                    [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 1200),
                    ]
                );
                return;
            }

            $transcript = trim((string) $response->json('text', ''));
            if ($transcript === '') {
                $this->markAsError($grabacion, 'Whisper no devolvio texto para la transcripcion.');
                return;
            }

            $transcripcion = Transcripcion::query()->updateOrCreate(
                ['grabacion_id' => $grabacion->id],
                [
                    'cita_id' => $grabacion->cita_id,
                    'contenido' => $transcript,
                    'proveedor' => 'openai',
                    'modelo' => $model,
                    'idioma' => (string) $response->json('language', 'es'),
                    'metadata' => [
                        'openai_response' => $response->json(),
                    ],
                ]
            );

            $grabacion->forceFill([
                'estado' => 'transcripcion_completada',
                'transcripcion_procesada_en' => now(),
                'metadata' => array_merge((array) ($grabacion->metadata ?? []), [
                    'transcripcion' => [
                        'status' => 'ok',
                        'processed_at' => now()->toIso8601String(),
                    ],
                ]),
            ])->save();

            GenerarResumenJob::dispatch($transcripcion->id);
        } catch (Throwable $e) {
            $this->markAsError($grabacion, 'Error inesperado al procesar transcripcion: '.$e->getMessage());
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function markAsError(Grabacion $grabacion, string $message, array $context = []): void
    {
        $grabacion->forceFill([
            'estado' => 'error_transcripcion',
            'metadata' => array_merge((array) ($grabacion->metadata ?? []), [
                'transcripcion' => [
                    'status' => 'error',
                    'message' => $message,
                    'context' => $context,
                    'processed_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }
}
