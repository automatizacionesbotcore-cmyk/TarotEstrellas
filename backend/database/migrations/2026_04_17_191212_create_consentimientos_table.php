<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('version_documento', 30);
            $table->boolean('otorgado')->default(true);
            $table->timestamp('otorgado_en')->nullable();
            $table->string('ip_otorgamiento', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};
