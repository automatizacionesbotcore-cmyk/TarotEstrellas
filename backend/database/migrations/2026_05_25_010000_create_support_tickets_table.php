<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('codigo', 24)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->string('email');
            $table->string('tipo_error', 60);
            $table->string('asunto');
            $table->text('descripcion');
            $table->enum('estado', ['nuevo', 'en_revision', 'esperando_usuario', 'resuelto', 'cerrado'])->default('nuevo');
            $table->enum('prioridad', ['baja', 'normal', 'alta', 'urgente'])->default('normal');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultimo_cambio_at')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email', 'estado']);
            $table->index(['user_id', 'created_at']);
            $table->index(['estado', 'created_at']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('autor_tipo', ['cliente', 'admin', 'sistema'])->default('cliente');
            $table->text('mensaje');
            $table->boolean('visible_para_cliente')->default(true);
            $table->timestamps();
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('nombre_original');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
