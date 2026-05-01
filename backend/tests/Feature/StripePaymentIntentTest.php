<?php

namespace Tests\Feature;

use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests para la creación de citas con canal_pago válido.
 * Flow.cl fue movido a FASE 2 — solo transferencia y paypal están activos.
 */
class StripePaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_client_can_create_cita_with_transferencia(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas', [
            'tipo_consulta_slug'   => 'tarot',
            'inicio_local'         => '2026-06-10 14:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago'           => 'transferencia',
        ])
            ->assertCreated()
            ->assertJsonPath('data.canal_pago', 'transferencia')
            ->assertJsonPath('data.estado', 'pendiente_abono');
    }

    public function test_payment_intent_endpoint_rejects_flow_canal_pago(): void
    {
        // Flow es FASE 2 — 'flow' no está en la lista de valores permitidos
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas', [
            'tipo_consulta_slug'   => 'tarot',
            'inicio_local'         => '2026-06-11 14:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago'           => 'flow',
        ])->assertStatus(422);
    }
}
