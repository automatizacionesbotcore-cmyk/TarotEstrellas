<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notificaciones_enviadas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('canal', 30)->index();           // email | whatsapp | sms | push
            $table->string('tipo', 80)->index();            // recordatorio_cita | reembolso | grabacion_pronto_borrar...
            $table->string('destinatario', 191)->nullable();
            $table->string('asunto', 191)->nullable();
            $table->text('preview')->nullable();
            $table->string('estado', 30)->default('enviado')->index(); // enviado | error | rebotado | leido
            $table->string('proveedor', 50)->nullable();
            $table->string('proveedor_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('enviado_en')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_enviadas');
    }
};
