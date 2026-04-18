<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('nombre', 120);
            $table->string('apellido', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('telefono_pais', 8)->nullable();
            $table->string('pais_residencia', 80)->nullable();
            $table->string('idioma_preferido', 8)->default('es');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
