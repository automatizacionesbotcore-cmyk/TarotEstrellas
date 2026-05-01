<?php

namespace Tests\Feature;

use App\Mail\TransferenciaAprobadaMail;
use App\Mail\TransferenciaRechazadaMail;
use App\Models\Cita;
use App\Models\ComprobanteTransferencia;
use App\Models\Pago;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComprobanteEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        Mail::fake();
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'super_admin')->value('id') => ['asignado_en' => now()],
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

    private function makeComprobanteConCita(User $cliente, string $estadoCita = 'pendiente_abono', string $tipoPago = 'abono_20'): array
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid'                  => (string) Str::uuid(),
            'codigo_referencia'     => 'TE-TEST-' . Str::upper(Str::random(6)),
            'cliente_id'            => $cliente->id,
            'especialista_id'       => null,
            'tipo_consulta_id'      => $tipo->id,
            'inicio_utc'            => now()->addDays(3),
            'fin_utc'               => now()->addDays(3)->addMinutes(120),
            'duracion_minutos'      => 120,
            'zona_horaria_cliente'  => 'America/Santiago',
            'estado'                => $estadoCita,
            'canal_pago'            => 'transferencia',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda'                => 'CLP',
            'es_primera_consulta'   => false,
        ]);

        $pago = Pago::query()->create([
            'uuid'           => (string) Str::uuid(),
            'cita_id'        => $cita->id,
            'tipo'           => $tipoPago,
            'canal'          => 'transferencia',
            'monto_centavos' => $tipoPago === 'abono_20' ? 10000 : 40000,
            'moneda'         => 'CLP',
            'estado'         => 'pendiente',
        ]);

        $comprobante = ComprobanteTransferencia::query()->create([
            'uuid'              => (string) Str::uuid(),
            'cita_id'           => $cita->id,
            'pago_id'           => $pago->id,
            'estado_validacion' => 'revision_requerida',
            'archivo_path'      => 'comprobantes/test.pdf',
            'banco_destino'     => 'BancoEstado',
            'monto_centavos'    => $pago->monto_centavos,
            'moneda'            => 'CLP',
        ]);

        return [$cita, $pago, $comprobante];
    }

    public function test_aprobar_comprobante_envia_mail_y_actualiza_estado_cita(): void
    {
        $admin   = $this->makeAdmin();
        $cliente = $this->makeCliente();
        Sanctum::actingAs($admin);

        [$cita, $pago, $comprobante] = $this->makeComprobanteConCita($cliente, 'pendiente_abono', 'abono_20');

        $this->postJson("/api/admin/comprobantes/{$comprobante->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('message', 'Comprobante aprobado manualmente.');

        // Email enviado al cliente
        Mail::assertSent(TransferenciaAprobadaMail::class, function ($mail) use ($cliente) {
            return $mail->hasTo($cliente->email);
        });

        // Cita pasa a reservada
        $this->assertDatabaseHas('citas', [
            'id'     => $cita->id,
            'estado' => 'reservada',
        ]);

        // Pago a completado
        $this->assertDatabaseHas('pagos', [
            'id'     => $pago->id,
            'estado' => 'completado',
        ]);

        // Comprobante marcado aprobado
        $this->assertDatabaseHas('comprobantes_transferencia', [
            'id'               => $comprobante->id,
            'estado_validacion' => 'aprobado_manual',
        ]);
    }

    public function test_aprobar_saldo_80_pasa_cita_a_confirmada(): void
    {
        $admin   = $this->makeAdmin();
        $cliente = $this->makeCliente();
        Sanctum::actingAs($admin);

        [$cita, $pago, $comprobante] = $this->makeComprobanteConCita($cliente, 'reservada', 'saldo_80');

        $this->postJson("/api/admin/comprobantes/{$comprobante->id}/aprobar")
            ->assertOk();

        $this->assertDatabaseHas('citas', [
            'id'     => $cita->id,
            'estado' => 'confirmada',
        ]);

        Mail::assertSent(TransferenciaAprobadaMail::class);
    }

    public function test_rechazar_comprobante_envia_mail_y_revierte_estado(): void
    {
        $admin   = $this->makeAdmin();
        $cliente = $this->makeCliente();
        Sanctum::actingAs($admin);

        [$cita, $pago, $comprobante] = $this->makeComprobanteConCita($cliente, 'pendiente_abono', 'abono_20');

        $this->postJson("/api/admin/comprobantes/{$comprobante->id}/rechazar", [
            'razon_rechazo' => 'El monto no corresponde al 20% del servicio.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Comprobante rechazado manualmente.');

        // Email enviado al cliente
        Mail::assertSent(TransferenciaRechazadaMail::class, function ($mail) use ($cliente) {
            return $mail->hasTo($cliente->email);
        });

        // Cita sigue en pendiente_abono
        $this->assertDatabaseHas('citas', [
            'id'     => $cita->id,
            'estado' => 'pendiente_abono',
        ]);

        // Pago rechazado
        $this->assertDatabaseHas('pagos', [
            'id'     => $pago->id,
            'estado' => 'rechazado',
        ]);

        // Comprobante con razon_rechazo
        $this->assertDatabaseHas('comprobantes_transferencia', [
            'id'               => $comprobante->id,
            'estado_validacion' => 'rechazado_manual',
            'razon_rechazo'    => 'El monto no corresponde al 20% del servicio.',
        ]);
    }

    public function test_rechazar_sin_razon_retorna_422(): void
    {
        $admin   = $this->makeAdmin();
        $cliente = $this->makeCliente();
        Sanctum::actingAs($admin);

        [,, $comprobante] = $this->makeComprobanteConCita($cliente);

        $this->postJson("/api/admin/comprobantes/{$comprobante->id}/rechazar", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('razon_rechazo');
    }

    public function test_cliente_no_puede_aprobar_comprobante(): void
    {
        $cliente = $this->makeCliente();
        Sanctum::actingAs($cliente);

        [,, $comprobante] = $this->makeComprobanteConCita($cliente);

        $this->postJson("/api/admin/comprobantes/{$comprobante->id}/aprobar")
            ->assertForbidden();

        Mail::assertNothingSent();
    }
}
