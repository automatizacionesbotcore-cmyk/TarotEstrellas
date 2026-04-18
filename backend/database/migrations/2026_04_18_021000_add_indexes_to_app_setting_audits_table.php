<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_setting_audits', function (Blueprint $table) {
            $table->index(['changed_by_user_id', 'changed_at'], 'app_setting_audits_user_changed_at_idx');
            $table->index(['app_setting_id', 'changed_at'], 'app_setting_audits_setting_changed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('app_setting_audits', function (Blueprint $table) {
            $table->dropIndex('app_setting_audits_user_changed_at_idx');
            $table->dropIndex('app_setting_audits_setting_changed_at_idx');
        });
    }
};
