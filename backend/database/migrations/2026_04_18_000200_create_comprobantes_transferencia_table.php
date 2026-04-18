<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_transferencia', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->string('archivo_url', 500)->nullable();
            $table->string('archivo_tipo', 20)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->json('datos_extraidos')->nullable();
            $table->string('estado_validacion', 40)->default('pendiente');
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_en')->nullable();
            $table->string('razon_rechazo', 500)->nullable();
            $table->string('id_transaccion_bancaria', 100)->nullable();
            $table->timestamps();

            $table->index('pago_id');
            $table->index('estado_validacion');
            $table->index('id_transaccion_bancaria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_transferencia');
    }
};
