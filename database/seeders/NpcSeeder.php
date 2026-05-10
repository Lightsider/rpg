<?php

namespace Database\Seeders;

use App\Domain\Npc\NpcType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NpcSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('npc_templates')->truncate();

        $defPrefixes = [
            'Guardian' => ['constitution' => 8, 'agility' => 0],
            'Shadow' => ['constitution' => 4, 'agility' => 4],
            'Balanced' => ['constitution' => 6, 'agility' => 2]
        ];
        
        $atkPrefixes = [
            'Steadfast' => ['strength' => 8, 'wit' => 0],
            'Versatile' => ['strength' => 6, 'wit' => 2],
            'Executioner' => ['strength' => 4, 'wit' => 4]
        ];

        foreach ($defPrefixes as $def => $defStats) {
            foreach ($atkPrefixes as $atk => $atkStats) {
                DB::table('npc_templates')->insert([
                    'name' => "{$def} {$atk}",
                    'type' => NpcType::HUMANOID->value,
                    'level' => 1,
                    'strength' => $atkStats['strength'],
                    'agility' => $defStats['agility'],
                    'constitution' => $defStats['constitution'],
                    'wit' => $atkStats['wit'],
                    'behavior_model' => 'aggressive',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
