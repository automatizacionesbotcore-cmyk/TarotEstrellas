<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_setting_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_setting_id')->nullable()->constrained('app_settings')->nullOnDelete();
            $table->string('key', 120);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['key', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_setting_audits');
    }
};
