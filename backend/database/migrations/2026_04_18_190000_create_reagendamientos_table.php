<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reagendamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_original_id')->constrained('citas')->cascadeOnDelete();
            $table->foreignId('cita_nueva_id')->constrained('citas')->cascadeOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->boolean('gratuito')->default(true);
            $table->foreignId('reagendado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('cita_nueva_id');
            $table->index('cita_original_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reagendamientos');
    }
};
