<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grabaciones', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->boolean('descargada_por_cliente')->default(false)->after('estado');
            $table->timestamp('primera_descarga_en')->nullable()->after('descargada_por_cliente');
            $table->timestamp('ultima_descarga_en')->nullable()->after('primera_descarga_en');
            $table->unsignedInteger('total_descargas')->default(0)->after('ultima_descarga_en');
            $table->index(['uuid', 'cita_id']);
        });
    }

    public function down(): void
    {
        Schema::table('grabaciones', function (Blueprint $table) {
            $table->dropIndex(['uuid', 'cita_id']);
            $table->dropColumn([
                'uuid',
                'descargada_por_cliente',
                'primera_descarga_en',
                'ultima_descarga_en',
                'total_descargas',
            ]);
        });
    }
};
