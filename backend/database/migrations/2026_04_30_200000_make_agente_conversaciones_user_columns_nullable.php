<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests) y MySQL (prod) tratan FK distinto: usamos DBAL via Schema::table.
        Schema::table('agente_conversaciones', function ($table) {
            $table->foreignId('cliente_id')->nullable()->change();
            $table->foreignId('autor_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('agente_conversaciones', function ($table) {
            $table->foreignId('cliente_id')->nullable(false)->change();
            $table->foreignId('autor_user_id')->nullable(false)->change();
        });
    }
};
