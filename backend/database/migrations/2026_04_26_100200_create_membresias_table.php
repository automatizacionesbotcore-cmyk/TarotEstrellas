<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membresias', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cliente_id')->constrained('users');
            $table->unsignedSmallInteger('paquete_id');
            $table->foreign('paquete_id')->references('id')->on('paquetes');
            $table->foreignId('pago_inicial_id')->nullable()->constrained('pagos');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('consultas_incluidas');
            $table->unsignedSmallInteger('consultas_usadas')->default(0);
            $table->enum('estado', ['activa', 'expirada', 'cancelada', 'agotada']);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'estado']);
            $table->index('fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membresias');
    }
};
