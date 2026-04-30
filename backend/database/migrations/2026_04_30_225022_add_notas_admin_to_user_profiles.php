<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->text('notas_admin')->nullable()->after('biografia');
            $table->unsignedBigInteger('notas_admin_actualizadas_por')->nullable()->after('notas_admin');
            $table->timestamp('notas_admin_actualizadas_en')->nullable()->after('notas_admin_actualizadas_por');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['notas_admin', 'notas_admin_actualizadas_por', 'notas_admin_actualizadas_en']);
        });
    }
};
