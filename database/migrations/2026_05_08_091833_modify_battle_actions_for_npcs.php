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
        Schema::table('battle_actions', function (Blueprint $table) {
            $table->dropForeign(['character_id']);
            $table->dropForeign(['target_id']);
            
            $table->bigInteger('character_id')->nullable()->change();
            $table->bigInteger('target_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battle_actions', function (Blueprint $table) {
            $table->bigInteger('character_id')->nullable(false)->change();
            $table->foreign('character_id')->references('id')->on('characters')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('characters')->onDelete('set null');
        });
    }
};
