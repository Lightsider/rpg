<?php

namespace Database\Seeders;

use App\Infrastructure\Eloquent\Models\LocationModel;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        LocationModel::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Training Grounds',
                'description' => 'A place where warriors practice combat.',
                'max_players' => 6,
                'start_timeout_seconds' => 600,
            ]
        );

        LocationModel::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Shop',
                'description' => 'A cozy stall filled with gear, trinkets, and trade.',
                'max_players' => null,
                'start_timeout_seconds' => null,
            ]
        );
    }
}
