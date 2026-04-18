<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTransferValidationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_admin_can_read_transfer_validation_settings(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings/transfer-validation');

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains('TRANSFERENCIA_BANCO', $keys);
        $this->assertContains('TRANSFERENCIA_CUENTA', $keys);
        $this->assertContains('TRANSFERENCIA_RUT', $keys);
        $this->assertContains('MINUTOS_ANTIGUEDAD_COMPROBANTE', $keys);
    }

    public function test_non_admin_cannot_read_transfer_validation_settings(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/settings/transfer-validation')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_read_transfer_validation_settings(): void
    {
        $this->getJson('/api/admin/settings/transfer-validation')
            ->assertStatus(401);
    }

    public function test_admin_can_update_transfer_validation_settings(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $payload = [
            'TRANSFERENCIA_BANCO' => 'Banco de Chile',
            'TRANSFERENCIA_CUENTA' => '999000111',
            'TRANSFERENCIA_RUT' => '12345678-9',
            'MINUTOS_ANTIGUEDAD_COMPROBANTE' => 45,
        ];

        $this->patchJson('/api/admin/settings/transfer-validation', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Configuracion de validacion de transferencias actualizada.');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'TRANSFERENCIA_BANCO',
            'value' => 'Banco de Chile',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'TRANSFERENCIA_CUENTA',
            'value' => '999000111',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'TRANSFERENCIA_RUT',
            'value' => '12345678-9',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'MINUTOS_ANTIGUEDAD_COMPROBANTE',
            'value' => '45',
        ]);

        $this->assertDatabaseCount('app_setting_audits', 4);
        $this->assertDatabaseHas('app_setting_audits', [
            'key' => 'TRANSFERENCIA_BANCO',
            'new_value' => 'Banco de Chile',
            'changed_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_update_requires_at_least_one_setting_field(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se recibieron cambios para aplicar.');
    }

    public function test_non_admin_cannot_update_transfer_validation_settings(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Otro Banco',
        ])->assertStatus(403);
    }

    public function test_admin_update_validates_minutes_range(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'MINUTOS_ANTIGUEDAD_COMPROBANTE' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['MINUTOS_ANTIGUEDAD_COMPROBANTE']);
    }

    public function test_admin_update_does_not_create_audit_when_value_does_not_change(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'BancoEstado',
        ])->assertOk();

        $this->assertDatabaseCount('app_setting_audits', 0);
    }

    public function test_admin_can_list_transfer_validation_audits_with_filter_and_pagination(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Banco 1',
            'TRANSFERENCIA_CUENTA' => '111',
        ])->assertOk();

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_RUT' => '22222222-2',
        ])->assertOk();

        $response = $this->getJson('/api/admin/settings/transfer-validation/audits?key=TRANSFERENCIA_BANCO&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'TRANSFERENCIA_BANCO');
    }

    public function test_non_admin_cannot_list_transfer_validation_audits(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/settings/transfer-validation/audits')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_list_transfer_validation_audits(): void
    {
        $this->getJson('/api/admin/settings/transfer-validation/audits')
            ->assertStatus(401);
    }

    public function test_admin_can_filter_transfer_validation_audits_by_changed_by_user_id(): void
    {
        $adminA = $this->makeUserWithRole('admin_especialista');
        $adminB = $this->makeUserWithRole('admin_especialista');

        Sanctum::actingAs($adminA);
        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Banco A',
        ])->assertOk();

        Sanctum::actingAs($adminB);
        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_RUT' => '99999999-9',
        ])->assertOk();

        $response = $this->getJson('/api/admin/settings/transfer-validation/audits?changed_by_user_id='.$adminB->id);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.key', 'TRANSFERENCIA_RUT')
            ->assertJsonPath('data.0.changed_by_user_id', $adminB->id);
    }

    public function test_admin_can_filter_transfer_validation_audits_by_date_range(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Banco Historico',
        ])->assertOk();

        DB::table('app_setting_audits')
            ->where('key', 'TRANSFERENCIA_BANCO')
            ->update(['changed_at' => Carbon::yesterday()]);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_CUENTA' => '777',
        ])->assertOk();

        $today = Carbon::today()->format('Y-m-d');
        $response = $this->getJson('/api/admin/settings/transfer-validation/audits?from_date='.$today.'&to_date='.$today);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.key', 'TRANSFERENCIA_CUENTA');
    }

    public function test_admin_audits_reject_invalid_date_range(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/settings/transfer-validation/audits?from_date=2026-04-20&to_date=2026-04-10')
            ->assertStatus(422)
            ->assertJsonPath('message', 'El rango de fechas es invalido: from_date no puede ser mayor que to_date.');
    }

    public function test_admin_can_revert_transfer_validation_setting_from_audit(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Banco Cambiado',
        ])->assertOk();

        $audit = DB::table('app_setting_audits')
            ->where('key', 'TRANSFERENCIA_BANCO')
            ->orderByDesc('id')
            ->first();

        $this->postJson('/api/admin/settings/transfer-validation/audits/'.$audit->id.'/revert')
            ->assertOk()
            ->assertJsonPath('message', 'Configuracion revertida correctamente.')
            ->assertJsonPath('data.key', 'TRANSFERENCIA_BANCO')
            ->assertJsonPath('data.value', 'BancoEstado');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'TRANSFERENCIA_BANCO',
            'value' => 'BancoEstado',
        ]);

        $this->assertDatabaseCount('app_setting_audits', 2);
        $this->assertDatabaseHas('app_setting_audits', [
            'key' => 'TRANSFERENCIA_BANCO',
            'old_value' => 'Banco Cambiado',
            'new_value' => 'BancoEstado',
            'changed_by_user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_revert_transfer_validation_setting_from_audit(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Banco Cambiado',
        ])->assertOk();

        $audit = DB::table('app_setting_audits')
            ->where('key', 'TRANSFERENCIA_BANCO')
            ->orderByDesc('id')
            ->first();

        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->postJson('/api/admin/settings/transfer-validation/audits/'.$audit->id.'/revert')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_revert_transfer_validation_setting_from_audit(): void
    {
        $this->postJson('/api/admin/settings/transfer-validation/audits/1/revert')
            ->assertStatus(401);
    }

    public function test_admin_can_export_transfer_validation_audits_csv(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/transfer-validation', [
            'TRANSFERENCIA_BANCO' => 'Banco CSV',
            'TRANSFERENCIA_CUENTA' => 'CSV-001',
        ])->assertOk();

        $response = $this->get('/api/admin/settings/transfer-validation/audits/export?key=TRANSFERENCIA_BANCO');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment; filename="transfer-validation-audits-', $contentDisposition);

        $content = $response->getContent();
        $this->assertStringContainsString('id,key,old_value,new_value,changed_by_user_id,changed_by_name,ip_address,changed_at', $content);
        $this->assertStringContainsString('TRANSFERENCIA_BANCO', $content);
        $this->assertStringNotContainsString('TRANSFERENCIA_CUENTA', $content);
    }

    public function test_non_admin_cannot_export_transfer_validation_audits_csv(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->get('/api/admin/settings/transfer-validation/audits/export')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_export_transfer_validation_audits_csv(): void
    {
        $this->getJson('/api/admin/settings/transfer-validation/audits/export')
            ->assertStatus(401);
    }

    private function makeUserWithRole(string $roleNombre): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
