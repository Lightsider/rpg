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
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->string('weapon_name')->nullable()->after('is_max');
            $table->string('damage_type')->nullable()->after('weapon_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn(['weapon_name', 'damage_type']);
        });
    }
};
