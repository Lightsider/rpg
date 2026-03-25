<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'ad_armor')) {
                $table->decimal('ad_armor', 10, 4)->default(0)->after('price');
                $table->decimal('dodge_bonus', 10, 4)->default(0)->after('ad_armor');
                $table->string('armor_subtype')->nullable()->after('dodge_bonus');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['ad_armor', 'dodge_bonus', 'armor_subtype']);
        });
    }
};
