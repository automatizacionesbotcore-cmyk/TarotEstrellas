<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\CuentaBancaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CuentaBancariaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        return $user;
    }

    private function makeCliente(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $user;
    }

    private function cuentaData(array $override = []): array
    {
        return array_merge([
            'banco'          => 'BancoEstado',
            'tipo_cuenta'    => 'corriente',
            'numero_cuenta'  => '12345678',
            'nombre_titular' => 'Tarot Estrellas',
            'rut_titular'    => '11111111-1',
        ], $override);
    }

    public function test_admin_can_create_cuenta_bancaria(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/cuentas-bancarias', $this->cuentaData())
            ->assertCreated()
            ->assertJsonPath('data.banco', 'BancoEstado')
            ->assertJsonPath('data.activa', true);

        $this->assertDatabaseHas('cuentas_bancarias', [
            'user_id' => $admin->id,
            'banco'   => 'BancoEstado',
        ]);
    }

    public function test_admin_cannot_exceed_3_cuentas(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/admin/cuentas-bancarias', $this->cuentaData([
                'numero_cuenta' => "1000000{$i}",
            ]))->assertCreated();
        }

        $this->postJson('/api/admin/cuentas-bancarias', $this->cuentaData(['numero_cuenta' => '99999999']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya tienes el máximo de 3 cuentas bancarias configuradas.');
    }

    public function test_admin_can_update_cuenta_bancaria(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $cuenta = CuentaBancaria::query()->create([
            'user_id'        => $admin->id,
            'banco'          => 'BancoEstado',
            'tipo_cuenta'    => 'corriente',
            'numero_cuenta'  => '12345678',
            'nombre_titular' => 'Original',
            'rut_titular'    => '11111111-1',
            'orden'          => 0,
            'activa'         => true,
        ]);

        $this->patchJson("/api/admin/cuentas-bancarias/{$cuenta->id}", ['nombre_titular' => 'Actualizado'])
            ->assertOk()
            ->assertJsonPath('data.nombre_titular', 'Actualizado');
    }

    public function test_admin_can_delete_cuenta_bancaria(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $cuenta = CuentaBancaria::query()->create([
            'user_id'        => $admin->id,
            'banco'          => 'Santander',
            'tipo_cuenta'    => 'corriente',
            'numero_cuenta'  => '99887766',
            'nombre_titular' => 'Delete Me',
            'rut_titular'    => '11111111-1',
            'orden'          => 0,
            'activa'         => true,
        ]);

        $this->deleteJson("/api/admin/cuentas-bancarias/{$cuenta->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cuentas_bancarias', ['id' => $cuenta->id]);
    }

    public function test_cliente_cannot_access_admin_cuentas_endpoint(): void
    {
        $cliente = $this->makeCliente();
        Sanctum::actingAs($cliente);

        $this->postJson('/api/admin/cuentas-bancarias', $this->cuentaData())
            ->assertForbidden();
    }

    public function test_public_endpoint_returns_active_cuentas(): void
    {
        $admin = $this->makeAdmin();

        CuentaBancaria::query()->create([
            'user_id'        => $admin->id,
            'banco'          => 'BancoEstado',
            'tipo_cuenta'    => 'vista',
            'numero_cuenta'  => '55555555',
            'nombre_titular' => 'Test',
            'rut_titular'    => '12345678-9',
            'orden'          => 0,
            'activa'         => true,
        ]);

        CuentaBancaria::query()->create([
            'user_id'        => $admin->id,
            'banco'          => 'Inactivo',
            'tipo_cuenta'    => 'corriente',
            'numero_cuenta'  => '00000000',
            'nombre_titular' => 'Inactivo',
            'rut_titular'    => '12345678-9',
            'orden'          => 1,
            'activa'         => false,
        ]);

        $this->getJson('/api/cuentas-bancarias')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.banco', 'BancoEstado');
    }
}
