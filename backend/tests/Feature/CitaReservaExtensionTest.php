<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitaReservaExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_cliente_can_extend_transfer_reservation_once_for_10_minutes(): void
    {
        Carbon::setTestNow('2026-06-20 10:00:00');

        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-21 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = (string) $create->json('data.uuid');
        $original = (string) $create->json('data.reservada_hasta');

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertOk()
            ->assertJsonPath('message', 'Reserva extendida por 10 minutos.')
            ->assertJsonPath('data.extensiones_aplicadas', 1)
            ->assertJsonPath('data.extensiones_permitidas', 1);

        $reservadaHasta = DB::table('citas')->where('uuid', $uuid)->value('reservada_hasta');
        $extensionAplicadaEn = DB::table('citas')->where('uuid', $uuid)->value('extension_reserva_aplicada_en');

        $this->assertNotNull($extensionAplicadaEn);
        $this->assertTrue(
            Carbon::parse($reservadaHasta)->equalTo(Carbon::parse($original)->addMinutes(10)),
            'La reserva no fue extendida exactamente 10 minutos.'
        );

        Carbon::setTestNow();
    }

    public function test_cliente_cannot_extend_reservation_twice(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-22 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = (string) $create->json('data.uuid');

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')->assertOk();

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La reserva ya fue extendida anteriormente.');
    }

    public function test_cliente_respects_configurable_extension_minutes_and_limits(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'MINUTOS_EXTENSION_TRANSFERENCIA'],
            ['category' => 'pagos_transferencia', 'value' => '5', 'editable_admin' => true]
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'EXTENSIONES_PERMITIDAS'],
            ['category' => 'pagos_transferencia', 'value' => '2', 'editable_admin' => true]
        );

        Carbon::setTestNow('2026-06-22 10:00:00');

        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-23 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = (string) $create->json('data.uuid');
        $original = (string) $create->json('data.reservada_hasta');

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertOk()
            ->assertJsonPath('message', 'Reserva extendida por 5 minutos.')
            ->assertJsonPath('data.extensiones_aplicadas', 1)
            ->assertJsonPath('data.extensiones_permitidas', 2);

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertOk()
            ->assertJsonPath('message', 'Reserva extendida por 5 minutos.')
            ->assertJsonPath('data.extensiones_aplicadas', 2)
            ->assertJsonPath('data.extensiones_permitidas', 2);

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La reserva ya fue extendida anteriormente.');

        $reservadaHasta = DB::table('citas')->where('uuid', $uuid)->value('reservada_hasta');
        $this->assertTrue(
            Carbon::parse($reservadaHasta)->equalTo(Carbon::parse($original)->addMinutes(10)),
            'La reserva no fue extendida con los minutos configurados.'
        );

        Carbon::setTestNow();
    }

    public function test_cliente_cannot_extend_non_transfer_cita(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-23 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'stripe',
        ])->assertCreated();

        $uuid = (string) $create->json('data.uuid');

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo se puede extender reserva en citas con pago por transferencia.');
    }

    public function test_cliente_cannot_extend_expired_reservation(): void
    {
        Carbon::setTestNow('2026-06-24 10:00:00');

        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-25 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = (string) $create->json('data.uuid');

        Carbon::setTestNow('2026-06-24 11:00:01');

        $this->postJson('/api/citas/'.$uuid.'/extender-reserva')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La reserva ya expiro y no puede extenderse.');

        Carbon::setTestNow();
    }

    private function makeUserWithRole(string $roleNombre): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
