<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos_agenda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('especialista_id');
            $table->enum('tipo', ['bloqueo', 'apertura_extra']);
            $table->enum('motivo', ['feriado', 'vacaciones', 'descanso', 'emergencia', 'evento', 'otro']);
            $table->string('descripcion', 255)->nullable();
            $table->dateTime('fecha_inicio_utc');
            $table->dateTime('fecha_fin_utc');
            $table->boolean('all_day')->default(false);
            $table->timestamps();

            $table->foreign('especialista_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['especialista_id', 'fecha_inicio_utc', 'fecha_fin_utc'], 'bloqueos_esp_fechas_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueos_agenda');
    }
};
