<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos_cliente', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->string('origen', 40); // reembolso, ajuste_admin, promocion, no_show
            $table->unsignedBigInteger('monto_centavos');
            $table->char('moneda', 3);
            $table->timestamp('vigente_hasta')->nullable();
            $table->string('estado', 20)->default('disponible'); // disponible, usado, expirado, anulado
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('cita_origen_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('cita_uso_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('usado_en')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
            $table->index('vigente_hasta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos_cliente');
    }
};
