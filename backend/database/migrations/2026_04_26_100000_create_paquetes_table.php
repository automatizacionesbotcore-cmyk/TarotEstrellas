<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes', function (Blueprint $table) {
            $table->smallInteger('id', true, true);
            $table->string('slug', 50)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['paquete', 'membresia']);
            $table->smallInteger('consultas_incluidas', false, true);
            $table->smallInteger('vigencia_dias', false, true)->nullable();
            $table->unsignedBigInteger('precio_centavos');
            $table->char('moneda', 3);
            $table->decimal('descuento_porcentaje', 5, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('imagen_url', 500)->nullable();
            $table->boolean('destacado')->default(false);
            $table->smallInteger('orden_visualizacion')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('paquete_tipos_consulta', function (Blueprint $table) {
            $table->unsignedSmallInteger('paquete_id');
            $table->unsignedSmallInteger('tipo_consulta_id');
            $table->primary(['paquete_id', 'tipo_consulta_id']);
            $table->foreign('paquete_id')->references('id')->on('paquetes')->cascadeOnDelete();
            $table->foreign('tipo_consulta_id')->references('id')->on('tipos_consulta')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquete_tipos_consulta');
        Schema::dropIfExists('paquetes');
    }
};
