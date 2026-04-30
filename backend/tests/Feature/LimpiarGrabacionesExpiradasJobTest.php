<?php

namespace Tests\Feature;

use App\Jobs\LimpiarGrabacionesExpiradasJob;
use App\Models\Cita;
use App\Models\Grabacion;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LimpiarGrabacionesExpiradasJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        config()->set('services.daily.api_key', '');
    }

    public function test_borra_grabaciones_expiradas_y_marca_estado(): void
    {
        $vencida = $this->makeGrabacion(expiraEn: now()->subDay());
        $futura = $this->makeGrabacion(expiraEn: now()->addDays(30));
        $sinFecha = $this->makeGrabacion(expiraEn: null);

        (new LimpiarGrabacionesExpiradasJob())->handle(app(\App\Services\DailyRoomService::class));

        $vencida->refresh();
        $futura->refresh();
        $sinFecha->refresh();

        $this->assertNotNull($vencida->borrada_en);
        $this->assertNull($vencida->url_grabacion);
        $this->assertSame('expirada', $vencida->estado);

        $this->assertNull($futura->borrada_en);
        $this->assertSame('https://mock.daily.co/rec/x.mp4', $futura->url_grabacion);

        $this->assertNull($sinFecha->borrada_en);
    }

    public function test_no_reborra_grabaciones_ya_borradas(): void
    {
        $g = $this->makeGrabacion(expiraEn: now()->subDay());
        $g->forceFill(['borrada_en' => now()->subHour()])->save();
        $borradaOriginal = $g->borrada_en;

        (new LimpiarGrabacionesExpiradasJob())->handle(app(\App\Services\DailyRoomService::class));

        $g->refresh();
        $this->assertEquals($borradaOriginal->toIso8601String(), $g->borrada_en->toIso8601String());
    }

    private function makeGrabacion(?\DateTimeInterface $expiraEn): Grabacion
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-LIMP-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subDays(95),
            'fin_utc' => now()->subDays(95)->addMinutes(60),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'resumen_completado',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 399000,
            'precio_final_centavos' => 399000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        return Grabacion::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'daily_room_name' => 'cita-'.$cita->uuid,
            'daily_recording_id' => 'rec_'.Str::random(8),
            'url_grabacion' => 'https://mock.daily.co/rec/x.mp4',
            'estado' => 'transcrita',
            'expira_en' => $expiraEn,
        ]);
    }
}
