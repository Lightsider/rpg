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
            $table->integer('strength')->nullable();
            $table->integer('agility')->nullable();
            $table->integer('constitution')->nullable();
            $table->integer('wit')->nullable();
            $table->json('equipment')->nullable();
            $table->string('name')->nullable(); // For random names
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battle_participants', function (Blueprint $table) {
            $table->dropColumn(['strength', 'agility', 'constitution', 'wit', 'equipment', 'name']);
        });
    }
};
