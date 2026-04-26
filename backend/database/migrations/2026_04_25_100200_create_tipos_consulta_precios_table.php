<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_consulta_precios', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tipo_consulta_id');
            $table->char('moneda', 3);
            $table->unsignedBigInteger('precio_centavos');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->foreign('tipo_consulta_id')->references('id')->on('tipos_consulta')->cascadeOnDelete();
            $table->index(['tipo_consulta_id', 'moneda', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_consulta_precios');
    }
};
