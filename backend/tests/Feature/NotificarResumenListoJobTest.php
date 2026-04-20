<?php

namespace Tests\Feature;

use App\Jobs\NotificarResumenListoJob;
use App\Mail\ResumenListoMail;
use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
use App\Models\Resumen;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificarResumenListoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_job_sends_email_when_summary_exists_and_client_has_email(): void
    {
        Mail::fake();

        $resumen = $this->createResumen();

        (new NotificarResumenListoJob($resumen->id))->handle();

        Mail::assertSent(ResumenListoMail::class, function (ResumenListoMail $mail) use ($resumen) {
            return $mail->resumen->id === $resumen->id;
        });
    }

    private function createResumen(): Resumen
    {
        $cliente = User::factory()->create([
            'email' => 'cliente@test.com',
            'email_verified_at' => now(),
        ]);

        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-NOTI-JOB1',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'resumen_completado',
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
            'daily_recording_id' => 'rec_noti_123',
            'daily_room_name' => 'room_noti_1',
            'url_grabacion' => 'https://r2.example.com/recordings/noti.mp4',
            'estado' => 'resumen_completado',
            'transcripcion_procesada_en' => now(),
            'resumen_generado_en' => now(),
            'metadata' => ['test' => true],
        ]);

        $transcripcion = Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'contenido' => 'Transcripcion lista para notificacion.',
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
            'metadata' => ['test' => true],
        ]);

        return Resumen::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'transcripcion_id' => $transcripcion->id,
            'contenido' => 'Resumen listo para el cliente.',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
            'metadata' => ['test' => true],
        ]);
    }
}
