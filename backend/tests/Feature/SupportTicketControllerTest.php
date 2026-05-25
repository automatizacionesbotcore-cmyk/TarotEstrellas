<?php

namespace Tests\Feature;

use App\Mail\SupportTicketCreatedMail;
use App\Mail\SupportTicketUpdatedMail;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_guest_can_create_support_ticket_with_image(): void
    {
        Mail::fake();
        Storage::fake('local');

        $response = $this->postJson('/api/soporte', [
            'nombre' => 'Cliente Nuevo',
            'email' => 'cliente-nuevo@example.com',
            'tipo_error' => 'registro',
            'asunto' => 'No puedo registrarme',
            'descripcion' => 'El formulario no termina el proceso de registro.',
            'imagenes' => [UploadedFile::fake()->image('error.png')],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.estado', 'nuevo')
            ->assertJsonPath('data.prioridad', 'alta');

        $this->assertDatabaseHas('support_tickets', ['email' => 'cliente-nuevo@example.com']);
        $this->assertDatabaseCount('support_ticket_attachments', 1);
        Mail::assertSent(SupportTicketCreatedMail::class);
    }

    public function test_client_can_list_and_reply_own_ticket(): void
    {
        Mail::fake();
        $cliente = $this->makeUserWithRole('cliente', ['email' => 'cliente@example.com']);
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/me/soporte', [
            'tipo_error' => 'plataforma',
            'asunto' => 'Error en mi cuenta',
            'descripcion' => 'Necesito ayuda con mi panel de cliente.',
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->getJson('/api/me/soporte')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $uuid);

        $this->postJson("/api/me/soporte/{$uuid}/responder", [
            'mensaje' => 'Agrego más detalles del problema.',
        ])->assertOk();

        $this->assertDatabaseHas('support_ticket_messages', [
            'mensaje' => 'Agrego más detalles del problema.',
            'autor_tipo' => 'cliente',
        ]);
    }

    public function test_super_admin_updates_ticket_and_notifies_user(): void
    {
        Mail::fake();
        $superAdmin = $this->makeUserWithRole('super_admin', ['email' => 'super@example.com']);
        $ticket = SupportTicket::create([
            'nombre' => 'Cliente',
            'email' => 'cliente-ticket@example.com',
            'tipo_error' => 'login',
            'asunto' => 'No puedo ingresar',
            'descripcion' => 'Me aparece error al iniciar sesión.',
        ]);

        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/admin/soporte/{$ticket->uuid}", [
            'estado' => 'en_revision',
            'prioridad' => 'alta',
            'respuesta' => 'Estamos revisando tu caso.',
            'visible_para_cliente' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_revision');

        Mail::assertSent(SupportTicketUpdatedMail::class);
    }

    public function test_admin_especialista_cannot_manage_support_tickets(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/soporte')->assertStatus(403);
    }

    private function makeUserWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
        $user->profile()->create(['nombre' => $attrs['name'] ?? ucfirst($role)]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $role)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
