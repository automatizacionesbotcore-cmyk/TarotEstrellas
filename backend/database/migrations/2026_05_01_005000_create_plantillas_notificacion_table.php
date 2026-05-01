<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plantillas_notificacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();          // recordatorio_24h, reembolso_aprobado…
            $table->string('canal', 30);                      // email | whatsapp
            $table->string('nombre', 191);
            $table->string('asunto', 191)->nullable();
            $table->text('cuerpo');
            $table->json('variables_disponibles')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('activa')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plantillas_notificacion_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_notificacion')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('asunto', 191)->nullable();
            $table->text('cuerpo');
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamps();
            $table->unique(['plantilla_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_notificacion_versiones');
        Schema::dropIfExists('plantillas_notificacion');
    }
};
