<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->unsignedSmallInteger('paquete_id')->nullable()->after('tipo_consulta_id');
            $table->char('membresia_id', 36)->nullable()->after('paquete_id');
            $table->unsignedInteger('cupon_id')->nullable()->after('membresia_id');

            $table->foreign('paquete_id')->references('id')->on('paquetes')->nullOnDelete();
            $table->foreign('cupon_id')->references('id')->on('cupones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropForeign(['paquete_id']);
            $table->dropForeign(['cupon_id']);
            $table->dropColumn(['paquete_id', 'membresia_id', 'cupon_id']);
        });
    }
};
