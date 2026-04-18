<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencia_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('email_recordatorios')->default(true);
            $table->boolean('email_marketing')->default(false);
            $table->boolean('whatsapp_recordatorios')->default(true);
            $table->boolean('whatsapp_marketing')->default(false);
            $table->string('zona_horaria', 64)->default('America/Santiago');
            $table->string('canal_preferido', 20)->default('email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencia_notificaciones');
    }
};
