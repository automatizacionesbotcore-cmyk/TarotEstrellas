<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->text('daily_room_url')->nullable()->after('canal_pago');
            $table->string('daily_room_name', 120)->nullable()->after('daily_room_url');
            $table->boolean('grabacion_solicitada')->default(false)->after('daily_room_name');
            $table->unsignedBigInteger('grabacion_extra_centavos')->default(0)->after('grabacion_solicitada');

            $table->index('daily_room_name');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex(['daily_room_name']);
            $table->dropColumn(['daily_room_url', 'daily_room_name', 'grabacion_solicitada', 'grabacion_extra_centavos']);
        });
    }
};
