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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('hp')->default(100);
            $table->integer('max_hp')->default(100);
            $table->integer('strength')->default(10);
            $table->integer('dexterity')->default(10);
            $table->string('weapon')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hp', 'max_hp', 'strength', 'dexterity', 'weapon']);
        });
    }
};
