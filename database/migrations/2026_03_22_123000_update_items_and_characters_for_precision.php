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
        // 1. Precise damage in items
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('min_damage', 10, 4)->default(0)->change();
            $table->decimal('max_damage', 10, 4)->default(0)->change();
            $table->decimal('flat_crit_bonus', 10, 4)->default(0)->change();
        });

        // 2. Seal slots in characters
        Schema::table('characters', function (Blueprint $table) {
            if (!Schema::hasColumn('characters', 'seal_1_id')) {
                $table->unsignedBigInteger('seal_1_id')->nullable()->after('weapon_id');
                $table->unsignedBigInteger('seal_2_id')->nullable()->after('seal_1_id');
                $table->unsignedBigInteger('seal_3_id')->nullable()->after('seal_2_id');
                $table->unsignedBigInteger('seal_4_id')->nullable()->after('seal_3_id');

                $table->foreign('seal_1_id')->references('id')->on('items');
                $table->foreign('seal_2_id')->references('id')->on('items');
                $table->foreign('seal_3_id')->references('id')->on('items');
                $table->foreign('seal_4_id')->references('id')->on('items');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['seal_1_id']);
            $table->dropForeign(['seal_2_id']);
            $table->dropForeign(['seal_3_id']);
            $table->dropForeign(['seal_4_id']);
            $table->dropColumn(['seal_1_id', 'seal_2_id', 'seal_3_id', 'seal_4_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->integer('min_damage')->default(0)->change();
            $table->integer('max_damage')->default(0)->change();
            $table->integer('flat_crit_bonus')->default(0)->change();
        });
    }
};
