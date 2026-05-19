<?php

namespace Tests\Feature;

use App\Jobs\EnviarReporteAgendaEspecialistaJob;
use App\Mail\AgendaEspecialistaReportMail;
use App\Models\Cita;
use App\Models\PerfilEspecialista;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnviarReporteAgendaEspecialistaJobTest extends TestCase
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

    public function test_daily_report_sends_today_appointments_to_each_specialist(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-19 08:00:00', 'America/Santiago'));

        $especialista = $this->makeEspecialista('especialista-diario@example.com');
        $cliente = $this->makeCliente();
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAILY-1',
            'cliente_id' => $cliente->id,
            'especialista_id' => $especialista->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => CarbonImmutable::parse('2026-05-19 10:00:00', 'America/Santiago')->setTimezone('UTC')->toDateTimeString(),
            'fin_utc' => CarbonImmutable::parse('2026-05-19 11:00:00', 'America/Santiago')->setTimezone('UTC')->toDateTimeString(),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'transferencia',
            'precio_total_centavos' => 10000,
            'precio_final_centavos' => 10000,
            'moneda' => 'CLP',
        ]);

        (new EnviarReporteAgendaEspecialistaJob('diario'))->handle();

        Mail::assertSent(AgendaEspecialistaReportMail::class, function (AgendaEspecialistaReportMail $mail) use ($especialista) {
            return $mail->hasTo($especialista->email)
                && $mail->periodo === 'diario'
                && $mail->citas->count() === 1;
        });
    }

    public function test_weekly_report_includes_current_week_only(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-18 07:00:00', 'America/Santiago'));

        $especialista = $this->makeEspecialista('especialista-semanal@example.com');
        $cliente = $this->makeCliente();
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        foreach ([
            ['TE-WEEK-1', '2026-05-18 12:00:00'],
            ['TE-WEEK-2', '2026-05-22 12:00:00'],
            ['TE-OUT-1', '2026-05-25 12:00:00'],
        ] as [$ref, $startChile]) {
            $start = CarbonImmutable::parse($startChile, 'America/Santiago');
            Cita::query()->create([
                'uuid' => (string) Str::uuid(),
                'codigo_referencia' => $ref,
                'cliente_id' => $cliente->id,
                'especialista_id' => $especialista->id,
                'tipo_consulta_id' => $tipo->id,
                'inicio_utc' => $start->setTimezone('UTC')->toDateTimeString(),
                'fin_utc' => $start->addHour()->setTimezone('UTC')->toDateTimeString(),
                'duracion_minutos' => 60,
                'zona_horaria_cliente' => 'America/Santiago',
                'estado' => 'confirmada',
                'canal_pago' => 'transferencia',
                'precio_total_centavos' => 10000,
                'precio_final_centavos' => 10000,
                'moneda' => 'CLP',
            ]);
        }

        (new EnviarReporteAgendaEspecialistaJob('semanal'))->handle();

        Mail::assertSent(AgendaEspecialistaReportMail::class, function (AgendaEspecialistaReportMail $mail) use ($especialista) {
            return $mail->hasTo($especialista->email)
                && $mail->periodo === 'semanal'
                && $mail->citas->count() === 2;
        });
    }

    private function makeEspecialista(string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);

        PerfilEspecialista::query()->create([
            'user_id' => $user->id,
            'slug' => 'especialista-'.Str::lower(Str::random(8)),
            'especialidad' => 'Tarot',
            'activo' => true,
            'orden_display' => 1,
        ]);

        return $user;
    }

    private function makeCliente(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['nombre' => 'Cliente', 'apellido' => 'Agenda']);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
