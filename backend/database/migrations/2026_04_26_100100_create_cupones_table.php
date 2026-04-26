<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('codigo', 30)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->enum('tipo_descuento', ['porcentaje', 'monto_fijo']);
            $table->decimal('valor_descuento', 10, 2);
            $table->char('moneda', 3)->nullable();
            $table->integer('uso_maximo_total')->nullable();
            $table->smallInteger('uso_maximo_por_cliente')->default(1);
            $table->integer('usos_totales')->default(0);
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();
            $table->unsignedBigInteger('monto_minimo_centavos')->nullable();
            $table->boolean('solo_primera_consulta')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'vigente_desde', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
