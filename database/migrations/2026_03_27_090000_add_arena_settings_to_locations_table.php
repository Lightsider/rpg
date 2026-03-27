<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations', 'max_players')) {
                $table->integer('max_players')->nullable()->after('description');
            }
            if (!Schema::hasColumn('locations', 'start_timeout_seconds')) {
                $table->integer('start_timeout_seconds')->nullable()->after('max_players');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations', 'start_timeout_seconds')) {
                $table->dropColumn('start_timeout_seconds');
            }
            if (Schema::hasColumn('locations', 'max_players')) {
                $table->dropColumn('max_players');
            }
        });
    }
};
