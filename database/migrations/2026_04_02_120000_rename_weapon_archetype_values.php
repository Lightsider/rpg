<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Weapons + Seals only (type = 'weapon' or type = 'seal')
        DB::table('items')->whereIn('type', ['weapon', 'seal'])->where('archetype', 'tank')->update(['archetype' => 'stable']);
        DB::table('items')->whereIn('type', ['weapon', 'seal'])->where('archetype', 'universal')->update(['archetype' => 'hybrid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('items')->whereIn('type', ['weapon', 'seal'])->where('archetype', 'stable')->update(['archetype' => 'tank']);
        DB::table('items')->whereIn('type', ['weapon', 'seal'])->where('archetype', 'hybrid')->update(['archetype' => 'universal']);
    }
};
