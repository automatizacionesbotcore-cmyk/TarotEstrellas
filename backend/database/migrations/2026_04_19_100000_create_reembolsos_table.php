<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reembolsos', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->unsignedBigInteger('monto_centavos');
            $table->char('moneda', 3);
            $table->string('estado', 30)->default('pendiente');
            $table->string('razon', 50);
            $table->string('metodo', 30)->default('mismo_medio_pago');
            $table->timestamp('solicitado_en')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'razon', 'estado']);
            $table->index(['cita_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reembolsos');
    }
};