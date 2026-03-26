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
        Schema::table('battle_participants', function (Blueprint $table) {
            $table->renameColumn('user_id', 'character_id');
        });

        Schema::table('battle_actions', function (Blueprint $table) {
            $table->renameColumn('user_id', 'character_id');
        });

        Schema::table('fighter_positions', function (Blueprint $table) {
            $table->renameColumn('user_id', 'character_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battle_participants', function (Blueprint $table) {
            $table->renameColumn('character_id', 'user_id');
        });

        Schema::table('battle_actions', function (Blueprint $table) {
            $table->renameColumn('character_id', 'user_id');
        });

        Schema::table('fighter_positions', function (Blueprint $table) {
            $table->renameColumn('character_id', 'user_id');
        });
    }
};
