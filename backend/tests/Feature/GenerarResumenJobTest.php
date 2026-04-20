<?php

namespace Tests\Feature;

use App\Jobs\GenerarResumenJob;
use App\Jobs\NotificarResumenListoJob;
use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
use App\Models\Resumen;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerarResumenJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_job_generates_summary_and_updates_recording_state(): void
    {
        Queue::fake();

        config([
            'services.anthropic.api_key' => 'test-anthropic-key',
            'services.anthropic.summary_model' => 'claude-3-5-haiku-latest',
        ]);

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => 'Este es un resumen breve de la sesion en tres parrafos.',
                ]],
            ], 200),
        ]);

        $transcripcion = $this->createTranscripcion('Texto de transcripcion de prueba.');

        (new GenerarResumenJob($transcripcion->id))->handle();

        $transcripcion->grabacion->refresh();

        $this->assertSame('resumen_completado', $transcripcion->grabacion->estado);
        $this->assertNotNull($transcripcion->grabacion->resumen_generado_en);

        $this->assertDatabaseHas('resumenes', [
            'transcripcion_id' => $transcripcion->id,
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
        ]);

        $this->assertSame(
            'Este es un resumen breve de la sesion en tres parrafos.',
            (string) Resumen::query()->where('transcripcion_id', $transcripcion->id)->value('contenido')
        );

        Queue::assertPushed(NotificarResumenListoJob::class, 1);
    }

    public function test_job_marks_error_when_anthropic_key_missing(): void
    {
        config(['services.anthropic.api_key' => null]);

        $transcripcion = $this->createTranscripcion('Texto de transcripcion de prueba.');

        (new GenerarResumenJob($transcripcion->id))->handle();

        $transcripcion->grabacion->refresh();

        $this->assertSame('error_resumen', $transcripcion->grabacion->estado);
        $this->assertDatabaseCount('resumenes', 0);
    }

    private function createTranscripcion(string $contenido): Transcripcion
    {
        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-SUMM-JOB1',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'transcripcion_completada',
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

        $grabacion = Grabacion::query()->create([
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_summary_123',
            'daily_room_name' => 'room_summary_1',
            'url_grabacion' => 'https://r2.example.com/recordings/summary.mp4',
            'estado' => 'transcripcion_completada',
            'transcripcion_procesada_en' => now(),
            'metadata' => ['test' => true],
        ]);

        return Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'contenido' => $contenido,
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
            'metadata' => ['test' => true],
        ]);
    }
}