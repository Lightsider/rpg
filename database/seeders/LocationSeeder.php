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
                'description' => 'A place where warriors practice combat.'
            ]
        );
    }
}
