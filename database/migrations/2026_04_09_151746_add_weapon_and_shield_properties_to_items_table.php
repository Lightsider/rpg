<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_two_handed')->default(false)->after('max_damage_rating');
            $table->integer('parry_rating')->default(0)->after('is_two_handed');
            $table->integer('defensive_ap_bonus')->default(0)->after('parry_rating');
            $table->decimal('hp_multiplier', 5, 2)->default(0.0)->after('defensive_ap_bonus');
            $table->integer('block_rating')->default(0)->after('hp_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['is_two_handed', 'parry_rating', 'defensive_ap_bonus', 'hp_multiplier', 'block_rating']);
        });
    }
};
