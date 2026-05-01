<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('user_email', 191)->nullable();
            $t->string('user_role', 50)->nullable();
            $t->string('action', 80);                 // created, updated, deleted, login, login_failed, request, ...
            $t->string('auditable_type', 191)->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('changes')->nullable();          // {old: {...}, new: {...}}
            $t->string('route', 191)->nullable();
            $t->string('method', 10)->nullable();
            $t->string('url', 500)->nullable();
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->json('payload')->nullable();          // request payload (sanitized)
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->timestamps();
            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['action', 'created_at']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
