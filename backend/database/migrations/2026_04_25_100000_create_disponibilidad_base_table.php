<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidad_base', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('especialista_id');
            $table->tinyInteger('dia_semana')->comment('0=domingo, 1=lunes... 6=sábado');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('especialista_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['especialista_id', 'dia_semana', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidad_base');
    }
};
