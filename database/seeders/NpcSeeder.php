<?php

namespace Database\Seeders;

use App\Domain\Npc\NpcType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NpcSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('npc_templates')->updateOrInsert(
            ['name' => 'Humanoid Shadow'],
            [
                'type' => NpcType::HUMANOID->value,
                'level' => 1,
                'strength' => 4,
                'agility' => 4,
                'constitution' => 4,
                'wit' => 4,
                'behavior_model' => 'aggressive',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
