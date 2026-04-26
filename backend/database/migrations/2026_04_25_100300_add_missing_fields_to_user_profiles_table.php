<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('zona_horaria', 50)->default('America/Santiago')->after('idioma_preferido');
            $table->enum('genero', ['femenino', 'masculino', 'no_binario', 'prefiero_no_decir'])->nullable()->after('zona_horaria');
            $table->date('fecha_nacimiento_publica')->nullable()->after('genero');
            $table->string('avatar_url', 500)->nullable()->after('fecha_nacimiento_publica');
            $table->text('biografia')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['zona_horaria', 'genero', 'fecha_nacimiento_publica', 'avatar_url', 'biografia']);
        });
    }
};
