<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_consulta', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug', 50)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('duracion_minutos');
            $table->unsignedBigInteger('precio_referencial_centavos')->default(0);
            $table->char('moneda', 3)->default('CLP');
            $table->string('imagen_url', 500)->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->boolean('requiere_datos_natales')->default(false);
            $table->smallInteger('orden_visualizacion')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'orden_visualizacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_consulta');
    }
};
