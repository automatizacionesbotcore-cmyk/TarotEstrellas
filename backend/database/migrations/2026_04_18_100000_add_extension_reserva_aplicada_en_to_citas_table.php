<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('citas') || Schema::hasColumn('citas', 'extension_reserva_aplicada_en')) {
            return;
        }

        Schema::table('citas', function (Blueprint $table) {
            $table->timestamp('extension_reserva_aplicada_en')->nullable()->after('reservada_hasta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('citas') || ! Schema::hasColumn('citas', 'extension_reserva_aplicada_en')) {
            return;
        }

        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn('extension_reserva_aplicada_en');
        });
    }
};
