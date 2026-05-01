<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripePaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_client_can_create_payment_intent_for_abono_20(): void
    {
        config([
            'services.flow.api_key'    => 'flow_api_test',
            'services.flow.secret_key' => 'flow_secret_test',
        ]);

        Http::fake([
            '*' => Http::response([
                'url'       => 'https://www.flow.cl/app/web/pay.php',
                'token'     => 'flow_token_abc123',
                'flowOrder' => 12345,
            ], 200),
        ]);

        $cliente = User::factory()->create();
        Sanctum::actingAs($cliente);

        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ABCD-EFGH',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'pendiente_abono',
            'canal_pago' => 'flow',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'reservada_hasta' => now()->addMinutes(30),
            'es_primera_consulta' => true,
        ]);

        $response = $this->postJson('/api/citas/'.$cita->uuid.'/pagar/flow', [
            'tipo' => 'abono_20',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'abono_20')
            ->assertJsonPath('data.monto_centavos', 10000);

        $this->assertDatabaseHas('pagos', [
            'cita_id' => $cita->id,
            'tipo'    => 'abono_20',
            'canal'   => 'flow',
            'estado'  => 'pendiente',
        ]);
    }

    public function test_payment_intent_endpoint_rejects_non_flow_cita(): void
    {
        config([
            'services.flow.api_key'    => 'flow_api_test',
            'services.flow.secret_key' => 'flow_secret_test',
        ]);

        $cliente = User::factory()->create();
        Sanctum::actingAs($cliente);

        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-WXYZ-IJKL',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'pendiente_abono',
            'canal_pago' => 'transferencia',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'reservada_hasta' => now()->addMinutes(30),
            'es_primera_consulta' => true,
        ]);

        $this->postJson('/api/citas/'.$cita->uuid.'/pagar/flow', [
            'tipo' => 'abono_20',
        ])->assertStatus(422);
    }
}
