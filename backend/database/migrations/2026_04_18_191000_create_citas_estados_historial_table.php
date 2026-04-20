<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas_estados_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->string('motivo', 255)->nullable();
            $table->foreignId('cambiado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cambiado_en')->useCurrent();
            $table->json('metadatos')->nullable();

            $table->index(['cita_id', 'cambiado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas_estados_historial');
    }
};
