<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validaciones_agente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();

            $table->boolean('regla_1_cuenta_ok')->nullable();
            $table->boolean('regla_2_monto_ok')->nullable();
            $table->boolean('regla_3_referencia_ok')->nullable();
            $table->boolean('regla_4_unicidad_ok')->nullable();
            $table->boolean('regla_5_ventana_tiempo_ok')->nullable();

            $table->string('decision', 30);
            $table->string('razon', 500);
            $table->string('modelo_ia', 50)->default('rules-engine-v1');
            $table->unsignedInteger('tokens_usados')->nullable();
            $table->unsignedInteger('duracion_ms')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['decision', 'created_at']);
            $table->index('cliente_id');
            $table->index('cita_id');
            $table->index('pago_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validaciones_agente');
    }
};
