<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_usage_alerts', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 50);                     // anthropic, daily, openai, ...
            $t->string('period', 10);                       // YYYY-MM
            $t->string('nivel', 20);                        // warning | exceeded
            $t->decimal('valor_actual', 12, 4);             // USD o minutos
            $t->decimal('limite', 12, 4);
            $t->decimal('porcentaje', 6, 2);                // 0-200
            $t->string('unidad', 20);                       // 'usd' | 'minutes'
            $t->timestamp('notificado_en')->useCurrent();
            $t->timestamps();
            $t->unique(['provider', 'period', 'nivel'], 'api_alert_unique_per_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_alerts');
    }
};
