<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmadaMail;
use App\Models\Cita;
use App\Models\NotificacionEnviada;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrarEmailEnviadoListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_envio_email_se_persiste_en_notificaciones_enviadas(): void
    {
        // Usamos el log/array transport real para que se dispare MessageSent (no Mail::fake).
        config(['mail.default' => 'array']);

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::factory()->create();
        $cita = Cita::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'codigo_referencia' => 'TE-T-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addHour(),
            'fin_utc' => now()->addHour()->addMinutes(30),
            'duracion_minutos' => 30,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 250000,
            'precio_final_centavos' => 250000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        Mail::to($cliente->email)->send(new CitaConfirmadaMail($cita));

        $this->assertDatabaseCount('notificaciones_enviadas', 1);
        $notif = NotificacionEnviada::first();
        $this->assertSame('email', $notif->canal);
        $this->assertSame('cita_confirmada', $notif->tipo);
        $this->assertSame($cliente->email, $notif->destinatario);
        $this->assertSame($cliente->id, $notif->user_id);
        $this->assertSame('enviado', $notif->estado);
        $this->assertNotNull($notif->uuid);
        $this->assertSame($cita->id, (int) ($notif->metadata['cita_id'] ?? 0));
    }
}
