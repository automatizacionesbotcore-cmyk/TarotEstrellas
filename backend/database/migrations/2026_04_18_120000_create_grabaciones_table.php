<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grabaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('daily_webhook_event_id')->nullable()->constrained('daily_webhook_events')->nullOnDelete();
            $table->string('daily_recording_id', 120)->nullable();
            $table->string('daily_room_name', 120)->nullable();
            $table->text('url_grabacion')->nullable();
            $table->string('estado', 40)->default('pendiente_transcripcion');
            $table->timestamp('transcripcion_procesada_en')->nullable();
            $table->timestamp('resumen_generado_en')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cita_id', 'estado']);
            $table->index('daily_recording_id');
            $table->index('daily_room_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grabaciones');
    }
};