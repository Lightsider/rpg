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
        Schema::create('level_sublevels', function (Blueprint $table) {
            $table->integer('level');
            $table->integer('sublevel_index');
            $table->bigInteger('xp_threshold');
            $table->bigInteger('reward_copper');
            
            $table->primary(['level', 'sublevel_index']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('level_sublevels');
    }
};
