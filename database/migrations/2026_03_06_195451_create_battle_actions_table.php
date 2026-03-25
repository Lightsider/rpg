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
        Schema::create('battle_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('target_zone')->nullable();
            $table->integer('from_x')->nullable();
            $table->integer('from_y')->nullable();
            $table->integer('to_x')->nullable();
            $table->integer('to_y')->nullable();
            $table->integer('round_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battle_actions');
    }
};
