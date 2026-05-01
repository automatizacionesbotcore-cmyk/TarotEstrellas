<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('banco', 80);
            $table->enum('tipo_cuenta', ['corriente', 'vista', 'ahorro', 'rut']);
            $table->string('numero_cuenta', 30);
            $table->string('nombre_titular', 100);
            $table->string('rut_titular', 12);
            $table->unsignedTinyInteger('orden')->default(0)->comment('0-2, máximo 3 cuentas por especialista');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'orden'], 'cuentas_bancarias_user_orden_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
