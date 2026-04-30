<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Grabacion;
use App\Models\Resumen;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use App\Services\BriefingIAService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BriefingIAServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        config()->set('services.anthropic.api_key', 'test-key');
        config()->set('services.anthropic.briefing_model', 'claude-3-5-sonnet-latest');
    }

    public function test_genera_briefing_con_anthropic_mock(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => "## Resumen del cliente\nCliente recurrente.\n## Temas recurrentes\nAmor.\n## Ultima sesion\nHablo de duelo.\n## Datos natales relevantes\nGeminis.\n## Recomendaciones para la proxima sesion\nAbordar gentileza."]],
                'usage' => ['input_tokens' => 200, 'output_tokens' => 80],
            ], 200),
        ]);

        $cliente = $this->makeClienteConHistorial();

        $service = app(BriefingIAService::class);
        $res = $service->generar($cliente);

        $this->assertStringContainsString('Resumen del cliente', $res['contenido']);
        $this->assertSame(200, $res['tokens_in']);
        $this->assertSame(80, $res['tokens_out']);
        $this->assertGreaterThanOrEqual(1, $res['sesiones_consideradas']);
    }

    public function test_lanza_runtime_si_anthropic_falla(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('error', 500)]);

        $cliente = $this->makeClienteConHistorial();
        $this->expectException(\RuntimeException::class);

        app(BriefingIAService::class)->generar($cliente);
    }

    public function test_endpoint_admin_briefing(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '## Resumen del cliente\nOK.']],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 10],
            ], 200),
        ]);

        $admin = $this->makeAdmin();
        $cliente = $this->makeClienteConHistorial();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/clientes/'.$cliente->uuid.'/briefing')
            ->assertOk()
            ->assertJsonStructure(['data' => ['contenido', 'modelo', 'sesiones_consideradas']]);
    }

    public function test_endpoint_briefing_403_sin_admin(): void
    {
        $cliente = $this->makeClienteConHistorial();
        Sanctum::actingAs($cliente);
        $this->getJson('/api/admin/clientes/'.$cliente->uuid.'/briefing')->assertStatus(403);
    }

    private function makeAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Admin']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeClienteConHistorial(): User
    {
        $cliente = User::factory()->create(['email_verified_at' => now(), 'name' => 'Maria']);
        $cliente->profile()->create(['nombre' => 'Maria', 'pais_residencia' => 'CL']);
        $cliente->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();
        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-BR-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subDays(7),
            'fin_utc' => now()->subDays(7)->addMinutes(60),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'completada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 399000,
            'precio_final_centavos' => 399000,
            'moneda' => 'CLP',
            'tema_principal' => 'amor',
            'es_primera_consulta' => false,
        ]);

        Resumen::query()->create([
            'cita_id' => $cita->id,
            'transcripcion_id' => Transcripcion::query()->create([
                'grabacion_id' => Grabacion::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'cita_id' => $cita->id,
                    'daily_room_name' => 'r-'.$cita->uuid,
                    'estado' => 'transcrita',
                ])->id,
                'cita_id' => $cita->id,
                'contenido' => 'Texto crudo.',
                'idioma' => 'es',
                'proveedor' => 'whisper',
                'modelo' => 'whisper-1',
            ])->id,
            'contenido' => 'Cliente consulto sobre relaciones. Mostro apertura.',
            'idioma' => 'es',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-sonnet-latest',
        ]);

        return $cliente;
    }
}
