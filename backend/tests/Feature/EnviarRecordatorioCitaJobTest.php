<?php

namespace Tests\Feature;

use App\Jobs\EnviarRecordatorioCitaJob;
use App\Mail\RecordatorioCitaMail;
use App\Models\Cita;
use App\Models\NotificacionEnviada;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnviarRecordatorioCitaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_sends_email_reminder_for_reserved_appointment(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 10:00:00', 'America/Santiago'));

        $cita = $this->makeCita('reservada', CarbonImmutable::parse('2026-05-20 11:00:00', 'America/Santiago'));

        (new EnviarRecordatorioCitaJob(60, 'email'))->handle();

        Mail::assertSent(RecordatorioCitaMail::class, function (RecordatorioCitaMail $mail) use ($cita) {
            return $mail->cita->is($cita) && $mail->minutosAntes === 60;
        });
    }

    public function test_does_not_send_email_reminder_for_pending_deposit(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 10:00:00', 'America/Santiago'));

        $this->makeCita('pendiente_abono', CarbonImmutable::parse('2026-05-20 11:00:00', 'America/Santiago'));

        (new EnviarRecordatorioCitaJob(60, 'email'))->handle();

        Mail::assertNothingSent();
    }

    public function test_does_not_send_duplicate_reminder_for_same_window(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 10:00:00', 'America/Santiago'));

        $cita = $this->makeCita('reservada', CarbonImmutable::parse('2026-05-20 11:00:00', 'America/Santiago'));

        NotificacionEnviada::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $cita->cliente_id,
            'canal' => 'email',
            'tipo' => 'recordatorio_cita',
            'destinatario' => $cita->cliente->email,
            'asunto' => 'Recordatorio',
            'preview' => 'Recordatorio',
            'estado' => 'enviado',
            'proveedor' => 'array',
            'metadata' => ['cita_id' => (string) $cita->id, 'minutos_antes' => '60'],
            'enviado_en' => now(),
        ]);

        (new EnviarRecordatorioCitaJob(60, 'email'))->handle();

        Mail::assertNothingSent();
    }

    private function makeCita(string $estado, CarbonImmutable $inicioChile): Cita
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $cliente->profile()->create(['nombre' => 'Cliente']);
        $cliente->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-T-' . Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $inicioChile->setTimezone('UTC')->toDateTimeString(),
            'fin_utc' => $inicioChile->addMinutes((int) $tipo->duracion_minutos)->setTimezone('UTC')->toDateTimeString(),
            'duracion_minutos' => (int) $tipo->duracion_minutos,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => $estado,
            'canal_pago' => 'transferencia',
            'precio_total_centavos' => (int) $tipo->precio_referencial_centavos,
            'precio_final_centavos' => (int) $tipo->precio_referencial_centavos,
            'moneda' => $tipo->moneda,
        ]);
    }
}
