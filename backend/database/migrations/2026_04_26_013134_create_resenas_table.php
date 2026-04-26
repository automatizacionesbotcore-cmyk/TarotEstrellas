<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resenas', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('cita_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('especialista_id');
            $table->foreign('especialista_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('puntuacion');
            $table->text('comentario')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resenas');
    }
};
