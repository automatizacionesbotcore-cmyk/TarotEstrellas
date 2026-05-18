<?php

namespace Tests\Feature;

use App\Models\PerfilEspecialista;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminEspecialistasControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_super_admin_envia_reset_password_a_especialista(): void
    {
        Notification::fake();

        $superAdmin = $this->makeSuperAdmin();
        $especialista = $this->makeEspecialista(['email' => 'especialista-reset@example.com']);

        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/admin/especialistas/'.$especialista->id.'/reset-password')
            ->assertOk()
            ->assertJsonPath('message', 'Se envio un enlace de restablecimiento al especialista.');

        Notification::assertSentTo($especialista, ResetPasswordNotification::class);
    }

    public function test_admin_especialista_no_puede_enviar_reset_password_a_otro_especialista(): void
    {
        Notification::fake();

        $admin = $this->makeEspecialista(['email' => 'admin-especialista@example.com']);
        $especialista = $this->makeEspecialista(['email' => 'otro-especialista@example.com']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/especialistas/'.$especialista->id.'/reset-password')
            ->assertStatus(403);

        Notification::assertNothingSent();
    }

    private function makeSuperAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Super Admin']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'super_admin')->value('id') => ['asignado_en' => now()],
        ]);

        return $u;
    }

    private function makeEspecialista(array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
        $u->profile()->create(['nombre' => $attrs['name'] ?? 'Especialista']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        PerfilEspecialista::query()->create([
            'user_id' => $u->id,
            'slug' => 'especialista-'.$u->id,
            'especialidad' => 'Tarot',
            'activo' => true,
            'orden_display' => 0,
        ]);

        return $u;
    }
}
