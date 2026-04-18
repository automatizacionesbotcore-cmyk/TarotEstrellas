<?php

namespace Tests\Feature;

use App\Jobs\ExpirarReservasJob;
use App\Models\Cita;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpirarReservasJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_expira_citas_pendientes_abono_fuera_de_ventana(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-05-10 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        Cita::query()->where('uuid', $uuid)->update([
            'reservada_hasta' => now()->subMinute(),
        ]);

        (new ExpirarReservasJob())->handle();

        $cita = Cita::query()->where('uuid', $uuid)->firstOrFail();

        $this->assertSame('expirada', $cita->estado);
        $this->assertNotNull($cita->cancelada_en);
        $this->assertSame('Expirada por falta de abono dentro de la ventana de tiempo.', $cita->motivo_cancelacion);
    }

    public function test_no_expira_citas_aun_dentro_de_ventana(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-05-11 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        Cita::query()->where('uuid', $uuid)->update([
            'reservada_hasta' => now()->addMinutes(5),
        ]);

        (new ExpirarReservasJob())->handle();

        $cita = Cita::query()->where('uuid', $uuid)->firstOrFail();

        $this->assertSame('pendiente_abono', $cita->estado);
        $this->assertNull($cita->cancelada_en);
    }

    private function makeAuthenticatedClient(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
