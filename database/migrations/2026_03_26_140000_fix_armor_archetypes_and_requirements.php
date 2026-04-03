<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $armorItems = DB::table('items')->whereNotNull('armor_subtype');

        (clone $armorItems)
            ->where('name', 'like', 'Guardian %')
            ->update([
                'archetype' => 'tank',
                'required_strength' => 0,
                'required_wit' => 0,
                'required_dexterity' => 0,
                'required_constitution' => 8,
            ]);

        (clone $armorItems)
            ->where('name', 'like', 'Balanced %')
            ->update([
                'archetype' => 'universal',
                'required_strength' => 0,
                'required_wit' => 0,
                'required_dexterity' => 2,
                'required_constitution' => 6,
            ]);

        (clone $armorItems)
            ->where('name', 'like', 'Shadow %')
            ->update([
                'archetype' => 'dodge',
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
