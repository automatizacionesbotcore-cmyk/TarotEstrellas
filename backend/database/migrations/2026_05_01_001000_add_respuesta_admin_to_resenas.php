<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->text('respuesta_admin')->nullable()->after('comentario');
            $table->timestamp('respondida_en')->nullable()->after('respuesta_admin');
            $table->unsignedBigInteger('respondida_por')->nullable()->after('respondida_en');
            $table->foreign('respondida_por')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->dropForeign(['respondida_por']);
            $table->dropColumn(['respuesta_admin', 'respondida_en', 'respondida_por']);
        });
    }
};
