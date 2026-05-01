<?php

namespace Tests\Feature;

use App\Jobs\AvisarVencimientoMembresiasJob;
use App\Jobs\DetectarNoShowAutomaticoJob;
use App\Jobs\ExpirarMembresiasJob;
use App\Models\Cita;
use App\Models\CreditoCliente;
use App\Models\Membresia;
use App\Models\Paquete;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class B3JobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_expirar_membresias_marca_vencidas(): void
    {
        $cliente = $this->makeClient();
        $paquete = $this->makePaquete();
        $m = Membresia::create([
            'cliente_id' => $cliente->id,
            'paquete_id' => $paquete->id,
            'fecha_inicio' => now()->subMonth(),
            'fecha_fin' => now()->subDay(),
            'consultas_incluidas' => 5,
            'consultas_usadas' => 2,
            'estado' => 'activa',
        ]);

        (new ExpirarMembresiasJob())->handle();

        $this->assertSame('expirada', $m->fresh()->estado);
    }

    public function test_expirar_membresias_marca_creditos_expirados(): void
    {
        $cliente = $this->makeClient();
        $c = CreditoCliente::create([
            'cliente_id' => $cliente->id,
            'origen' => 'promocion',
            'monto_centavos' => 10000,
            'moneda' => 'CLP',
            'vigente_hasta' => now()->subDay(),
            'estado' => 'disponible',
        ]);

        (new ExpirarMembresiasJob())->handle();

        $this->assertSame('expirado', $c->fresh()->estado);
    }

    public function test_avisar_vencimiento_log(): void
    {
        $cliente = $this->makeClient();
        $paquete = $this->makePaquete();
        Membresia::create([
            'cliente_id' => $cliente->id,
            'paquete_id' => $paquete->id,
            'fecha_inicio' => now()->subDays(23),
            'fecha_fin' => now()->addDays(7),
            'consultas_incluidas' => 5,
            'consultas_usadas' => 1,
            'estado' => 'activa',
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('Membresia proxima a vencer', \Mockery::any());

        (new AvisarVencimientoMembresiasJob(7))->handle();
    }

    public function test_detectar_no_show_loguea_candidatas(): void
    {
        $cliente = $this->makeClient();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();
        Cita::create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-NS-' . Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subHour(),
            'fin_utc' => now()->subMinutes(20),
            'duracion_minutos' => 30,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 250000,
            'precio_final_centavos' => 250000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        Log::shouldReceive('warning')->once();

        (new DetectarNoShowAutomaticoJob(15))->handle();
    }

    public function test_credito_disponible_scope(): void
    {
        $cliente = $this->makeClient();
        $disponible = CreditoCliente::create([
            'cliente_id' => $cliente->id, 'origen' => 'promocion',
            'monto_centavos' => 5000, 'moneda' => 'CLP', 'estado' => 'disponible',
        ]);
        CreditoCliente::create([
            'cliente_id' => $cliente->id, 'origen' => 'promocion',
            'monto_centavos' => 5000, 'moneda' => 'CLP', 'estado' => 'usado',
        ]);
        CreditoCliente::create([
            'cliente_id' => $cliente->id, 'origen' => 'promocion',
            'monto_centavos' => 5000, 'moneda' => 'CLP', 'estado' => 'disponible',
            'vigente_hasta' => now()->subDay(),
        ]);

        $list = CreditoCliente::query()->where('cliente_id', $cliente->id)->disponibles()->get();
        $this->assertCount(1, $list);
        $this->assertSame($disponible->id, $list->first()->id);
    }

    private function makePaquete(): Paquete
    {
        return Paquete::create([
            'slug' => 'test-' . Str::random(6),
            'nombre' => 'Test',
            'tipo' => 'membresia',
            'consultas_incluidas' => 5,
            'vigencia_dias' => 30,
            'precio_centavos' => 100000,
            'moneda' => 'CLP',
            'activo' => true,
        ]);
    }

    private function makeClient(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }
}
