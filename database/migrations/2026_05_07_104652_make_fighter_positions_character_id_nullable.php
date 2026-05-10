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
        Schema::table('fighter_positions', function (Blueprint $table) {
            $table->dropForeign(['character_id']);
            $table->bigInteger('character_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fighter_positions', function (Blueprint $table) {
            $table->bigInteger('character_id')->nullable(false)->change();
            $table->foreign('character_id')->references('id')->on('characters')->onDelete('cascade');
        });
    }
};
