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
            $table->string('archetype')->default('universal')->after('type');
            $table->integer('required_strength')->default(0)->after('archetype');
            $table->integer('required_wit')->default(0)->after('required_strength');
            $table->integer('flat_crit_bonus')->default(0)->after('max_damage_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['archetype', 'required_strength', 'required_wit', 'flat_crit_bonus']);
        });
    }
};
