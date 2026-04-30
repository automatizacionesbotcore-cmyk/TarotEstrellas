<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('autor_rol', 20);
            $table->text('pregunta');
            $table->longText('respuesta')->nullable();
            $table->string('proveedor', 40)->default('anthropic');
            $table->string('modelo', 100)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->unsignedInteger('contexto_sesiones')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'created_at']);
            $table->index(['autor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_conversaciones');
    }
};
