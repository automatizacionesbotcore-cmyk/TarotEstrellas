<?php

namespace Tests\Feature;

use App\Mail\SalaRapidaAbiertaMail;
use App\Models\Cita;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSesionRapidaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        config(['services.daily.api_key' => null]);
    }

    public function test_especialista_abre_sala_rapida_y_notifica_cliente(): void
    {
        Mail::fake();

        $especialista = $this->makeUserWithRole('admin_especialista', 'especialista@example.com');
        $cliente = $this->makeUserWithRole('cliente', 'cliente@example.com');
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        $response = $this->actingAs($especialista, 'sanctum')
            ->postJson('/api/admin/sesiones-rapidas', [
                'cliente_uuid' => $cliente->uuid,
                'tipo_consulta_id' => $tipo->id,
                'tema_principal' => 'Amor',
                'mensaje' => 'Ya puedes entrar a la sala.',
                'grabacion_solicitada' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Sala rapida abierta y notificada correctamente.');

        $citaUuid = $response->json('data.cita_uuid');
        $this->assertNotEmpty($citaUuid);

        $this->assertDatabaseHas('citas', [
            'uuid' => $citaUuid,
            'cliente_id' => $cliente->id,
            'especialista_id' => $especialista->id,
            'tipo_consulta_id' => $tipo->id,
            'estado' => 'confirmada',
            'canal_pago' => 'sesion_rapida',
            'grabacion_solicitada' => true,
        ]);

        $this->assertNotNull(Cita::query()->where('uuid', $citaUuid)->value('daily_room_name'));

        Mail::assertSent(SalaRapidaAbiertaMail::class, function (SalaRapidaAbiertaMail $mail) use ($cliente, $citaUuid) {
            return $mail->hasTo($cliente->email) && $mail->cita->uuid === $citaUuid;
        });
    }

    public function test_cliente_no_puede_abrir_sala_rapida(): void
    {
        $cliente = $this->makeUserWithRole('cliente', 'cliente2@example.com');
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        $this->actingAs($cliente, 'sanctum')
            ->postJson('/api/admin/sesiones-rapidas', [
                'cliente_uuid' => $cliente->uuid,
                'tipo_consulta_id' => $tipo->id,
            ])
            ->assertForbidden();
    }

    private function makeUserWithRole(string $roleNombre, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
