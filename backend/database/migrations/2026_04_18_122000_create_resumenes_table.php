<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('grabacion_id')->nullable()->constrained('grabaciones')->nullOnDelete();
            $table->foreignId('transcripcion_id')->unique()->constrained('transcripciones')->cascadeOnDelete();
            $table->longText('contenido');
            $table->string('proveedor', 40)->default('anthropic');
            $table->string('modelo', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cita_id', 'created_at']);
            $table->index('proveedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumenes');
    }
};