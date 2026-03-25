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
        Schema::create('fight_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fight_id')->constrained('battles')->onDelete('cascade');
            $table->integer('width');
            $table->integer('height');
            $table->timestamps();

            $table->unique('fight_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fight_maps');
    }
};
