<?php

namespace Database\Seeders;

use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ItemSeeder::class,
            LocationSeeder::class,
            StoreSeeder::class,
            NpcSeeder::class,
        ]);
    }
}
