<?php

namespace Tests\Feature;

use App\Jobs\GenerarResumenJob;
use App\Jobs\ProcesarTranscripcionJob;
use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcesarTranscripcionJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_job_downloads_recording_and_persists_transcription(): void
    {
        Queue::fake();

        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.whisper_model' => 'whisper-1',
        ]);

        Http::fake([
            'https://r2.example.com/*' => Http::response('binary-media-content', 200),
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Esta es la transcripcion de prueba.',
                'language' => 'es',
            ], 200),
        ]);

        $grabacion = $this->createGrabacion('https://r2.example.com/recordings/test-1.mp4');

        (new ProcesarTranscripcionJob($grabacion->id))->handle();

        $grabacion->refresh();

        $this->assertSame('transcripcion_completada', $grabacion->estado);
        $this->assertNotNull($grabacion->transcripcion_procesada_en);

        $this->assertDatabaseHas('transcripciones', [
            'grabacion_id' => $grabacion->id,
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
        ]);

        $this->assertSame(
            'Esta es la transcripcion de prueba.',
            (string) Transcripcion::query()->where('grabacion_id', $grabacion->id)->value('contenido')
        );

        Queue::assertPushed(GenerarResumenJob::class, 1);
    }

    public function test_job_marks_error_when_openai_key_is_missing(): void
    {
        config(['services.openai.api_key' => null]);

        $grabacion = $this->createGrabacion('https://r2.example.com/recordings/test-2.mp4');

        (new ProcesarTranscripcionJob($grabacion->id))->handle();

        $grabacion->refresh();

        $this->assertSame('error_transcripcion', $grabacion->estado);
        $this->assertDatabaseCount('transcripciones', 0);
    }

    private function createGrabacion(string $urlGrabacion): Grabacion
    {
        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-TRANS-JOB1',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $event = DailyWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'recording.ready',
            'payload' => ['source' => 'test'],
            'processed_at' => now(),
        ]);

        return Grabacion::query()->create([
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_test_123',
            'daily_room_name' => 'room_test_1',
            'url_grabacion' => $urlGrabacion,
            'estado' => 'pendiente_transcripcion',
            'metadata' => ['test' => true],
        ]);
    }
}