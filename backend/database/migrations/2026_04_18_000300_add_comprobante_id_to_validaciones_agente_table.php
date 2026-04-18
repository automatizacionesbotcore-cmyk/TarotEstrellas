<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validaciones_agente', function (Blueprint $table) {
            $table->foreignId('comprobante_id')
                ->nullable()
                ->after('id')
                ->constrained('comprobantes_transferencia')
                ->nullOnDelete();

            $table->index('comprobante_id');
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_agente', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comprobante_id');
        });
    }
};
