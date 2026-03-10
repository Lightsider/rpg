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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'weapon', 'shield', etc.

            // Weapon stats
            $table->integer('min_damage')->default(0);
            $table->integer('max_damage')->default(0);
            $table->string('damage_type')->nullable();
            $table->float('accuracy_bonus')->default(0);
            $table->integer('block_break_rating')->default(0);
            $table->float('pierce_multiplier')->default(0);
            $table->integer('max_damage_rating')->default(0);

            $table->timestamps();
        });

        // Add item_id to users to represent equipped weapon (for now)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('weapon_id')->nullable();
            $table->foreign('weapon_id')->references('id')->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
