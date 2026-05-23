<?php

namespace Tests\Feature;

use App\Jobs\ProcesarTranscripcionJob;
use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use App\Services\DailyRecordingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyRecordingSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_sync_imports_finished_daily_recording_and_dispatches_transcription(): void
    {
        Queue::fake();

        config([
            'services.daily.api_key' => 'daily_test_key',
            'services.daily.base_url' => 'https://api.daily.co/v1',
        ]);

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();
        $roomName = 'cita-sync-room';

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAIL-YSNC',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subHour(),
            'fin_utc' => now(),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'flow',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
            'grabacion_solicitada' => true,
            'daily_room_name' => $roomName,
        ]);

        Http::fake([
            'https://api.daily.co/v1/recordings*' => Http::sequence()
                ->push([
                    'data' => [[
                        'id' => 'rec_sync_123',
                        'room_name' => $roomName,
                        'status' => 'finished',
                        'duration' => 70,
                    ]],
                ])
                ->push([
                    'download_link' => 'https://daily.example.com/rec_sync_123.mp4',
                    'expires' => now()->addHour()->timestamp,
                ]),
        ]);

        $stats = app(DailyRecordingSyncService::class)->sync(limit: 10, processNow: false);

        $this->assertSame(1, $stats['checked']);
        $this->assertSame(1, $stats['imported']);
        $this->assertSame(1, $stats['processed']);

        $this->assertDatabaseHas('grabaciones', [
            'cita_id' => $cita->id,
            'daily_recording_id' => 'rec_sync_123',
            'daily_room_name' => $roomName,
            'estado' => 'pendiente_transcripcion',
        ]);

        Queue::assertPushed(ProcesarTranscripcionJob::class, 1);
    }
}
