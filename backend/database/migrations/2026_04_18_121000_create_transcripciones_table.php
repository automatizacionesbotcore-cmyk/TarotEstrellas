<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('grabacion_id')->unique()->constrained('grabaciones')->cascadeOnDelete();
            $table->longText('contenido');
            $table->string('proveedor', 40)->default('openai');
            $table->string('modelo', 80)->nullable();
            $table->string('idioma', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cita_id', 'created_at']);
            $table->index('proveedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripciones');
    }
};