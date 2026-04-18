<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('citas') || Schema::hasColumn('citas', 'extension_reserva_conteo')) {
            return;
        }

        Schema::table('citas', function (Blueprint $table) {
            $table->unsignedTinyInteger('extension_reserva_conteo')
                ->default(0)
                ->after('extension_reserva_aplicada_en');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('citas') || ! Schema::hasColumn('citas', 'extension_reserva_conteo')) {
            return;
        }

        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn('extension_reserva_conteo');
        });
    }
};
