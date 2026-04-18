<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('codigo_referencia', 20)->unique();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('especialista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tipo_consulta_id');
            $table->foreign('tipo_consulta_id')->references('id')->on('tipos_consulta')->restrictOnDelete();

            $table->dateTime('inicio_utc');
            $table->dateTime('fin_utc');
            $table->unsignedSmallInteger('duracion_minutos');
            $table->string('zona_horaria_cliente', 50);

            $table->string('estado', 30)->default('pendiente_abono');
            $table->string('canal_pago', 20)->default('stripe');
            $table->unsignedBigInteger('precio_total_centavos');
            $table->unsignedBigInteger('precio_final_centavos');
            $table->char('moneda', 3)->default('CLP');

            $table->string('tema_principal', 50)->nullable();
            $table->text('notas_cliente')->nullable();
            $table->text('notas_chachita')->nullable();

            $table->timestamp('reservada_hasta')->nullable();
            $table->timestamp('confirmada_en')->nullable();
            $table->timestamp('finalizada_en')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->boolean('es_primera_consulta')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'estado']);
            $table->index(['especialista_id', 'inicio_utc']);
            $table->index(['estado', 'inicio_utc']);
            $table->index('reservada_hasta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
