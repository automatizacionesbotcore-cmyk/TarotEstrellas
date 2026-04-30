<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grabaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('grabaciones', 'uuid')) {
                $table->char('uuid', 36)->nullable()->unique()->after('id');
            }
            $table->timestamp('expira_en')->nullable()->after('resumen_generado_en');
            $table->timestamp('borrada_en')->nullable()->after('expira_en');
            $table->index('expira_en');
        });
    }

    public function down(): void
    {
        Schema::table('grabaciones', function (Blueprint $table) {
            $table->dropIndex(['expira_en']);
            $table->dropColumn(['expira_en', 'borrada_en']);
        });
    }
};
