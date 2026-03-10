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
        Schema::create('battle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->onDelete('cascade');
            $table->integer('round_number');
            $table->string('type'); // hit, miss, block, move, death
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('target_id')->nullable()->constrained('users');
            $table->integer('damage')->nullable();
            $table->string('zone')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battle_logs');
    }
};
