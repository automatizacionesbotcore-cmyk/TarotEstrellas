<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('referencia_externa', 120)->nullable()->after('stripe_charge_id');
            $table->unique('referencia_externa');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['referencia_externa']);
            $table->dropColumn('referencia_externa');
        });
    }
};
