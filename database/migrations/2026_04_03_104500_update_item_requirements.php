<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('items')
            ->whereIn('type', ['weapon', 'seal'])
            ->where('archetype', 'stable')
            ->update([
                'required_strength' => 8,
                'required_wit' => 0,
            ]);

        DB::table('items')
            ->whereIn('type', ['weapon', 'seal'])
            ->where('archetype', 'hybrid')
            ->update([
                'required_strength' => 6,
                'required_wit' => 2,
            ]);

        DB::table('items')
            ->whereIn('type', ['weapon', 'seal'])
            ->where('archetype', 'crit')
            ->update([
                'required_strength' => 4,
                'required_wit' => 4,
            ]);

        DB::table('items')
            ->where('type', 'armor')
            ->where('archetype', 'tank')
            ->update([
                'required_strength' => 0,
                'required_wit' => 0,
                'required_dexterity' => 0,
                'required_constitution' => 8,
            ]);

        DB::table('items')
            ->where('type', 'armor')
            ->where('archetype', 'universal')
            ->update([
                'required_strength' => 0,
                'required_wit' => 0,
                'required_dexterity' => 2,
                'required_constitution' => 6,
            ]);

        DB::table('items')
            ->where('type', 'armor')
            ->where('archetype', 'dodge')
            ->update([
                'required_strength' => 0,
                'required_wit' => 0,
                'required_dexterity' => 4,
                'required_constitution' => 4,
            ]);
    }

    public function down(): void
    {
        // No rollback for data correction.
    }
};
