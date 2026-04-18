<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('canal', 20);
            $table->unsignedBigInteger('monto_centavos');
            $table->char('moneda', 3);
            $table->string('estado', 30)->default('pendiente');
            $table->string('stripe_payment_intent_id', 100)->nullable();
            $table->string('stripe_charge_id', 100)->nullable();
            $table->timestamp('pagado_en')->nullable();
            $table->string('fallo_razon', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cita_id', 'tipo', 'estado']);
            $table->index('pagado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
