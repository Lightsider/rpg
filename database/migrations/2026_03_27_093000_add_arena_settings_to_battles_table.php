<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            if (!Schema::hasColumn('battles', 'max_participants')) {
                $table->integer('max_participants')->nullable()->after('round_duration_seconds');
            }
            if (!Schema::hasColumn('battles', 'start_timeout_seconds')) {
                $table->integer('start_timeout_seconds')->nullable()->after('max_participants');
            }
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            if (Schema::hasColumn('battles', 'start_timeout_seconds')) {
                $table->dropColumn('start_timeout_seconds');
            }
            if (Schema::hasColumn('battles', 'max_participants')) {
                $table->dropColumn('max_participants');
            }
        });
    }
};
