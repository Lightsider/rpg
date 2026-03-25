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
        Schema::table('characters', function (Blueprint $table) {
            // Armor Slots
            if (!Schema::hasColumn('characters', 'helmet_id')) {
                $table->unsignedBigInteger('helmet_id')->nullable()->after('weapon_id');
                $table->unsignedBigInteger('chest_id')->nullable()->after('helmet_id');
                $table->unsignedBigInteger('legs_id')->nullable()->after('chest_id');
                $table->unsignedBigInteger('gloves_id')->nullable()->after('legs_id');

                $table->foreign('helmet_id')->references('id')->on('items');
                $table->foreign('chest_id')->references('id')->on('items');
                $table->foreign('legs_id')->references('id')->on('items');
                $table->foreign('gloves_id')->references('id')->on('items');

                // State
                $table->decimal('ad_armor_head', 10, 4)->default(0)->after('hp');
                $table->decimal('ad_armor_chest', 10, 4)->default(0)->after('ad_armor_head');
                $table->decimal('ad_armor_legs', 10, 4)->default(0)->after('ad_armor_chest');
                $table->decimal('ad_armor_hands', 10, 4)->default(0)->after('ad_armor_legs');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['helmet_id']);
            $table->dropForeign(['chest_id']);
            $table->dropForeign(['legs_id']);
            $table->dropForeign(['gloves_id']);
            $table->dropColumn([
                'helmet_id', 'chest_id', 'legs_id', 'gloves_id',
                'ad_armor_head', 'ad_armor_chest', 'ad_armor_legs', 'ad_armor_hands'
            ]);
        });
    }
};
