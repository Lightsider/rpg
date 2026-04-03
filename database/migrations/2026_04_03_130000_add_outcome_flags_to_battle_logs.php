<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('battle_logs', 'outcome')) {
                $table->string('outcome')->nullable()->after('zone');
            }
            if (!Schema::hasColumn('battle_logs', 'is_crit')) {
                $table->boolean('is_crit')->nullable()->after('outcome');
            }
            if (!Schema::hasColumn('battle_logs', 'is_max')) {
                $table->boolean('is_max')->nullable()->after('is_crit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            if (Schema::hasColumn('battle_logs', 'is_max')) {
                $table->dropColumn('is_max');
            }
            if (Schema::hasColumn('battle_logs', 'is_crit')) {
                $table->dropColumn('is_crit');
            }
            if (Schema::hasColumn('battle_logs', 'outcome')) {
                $table->dropColumn('outcome');
            }
        });
    }
};
