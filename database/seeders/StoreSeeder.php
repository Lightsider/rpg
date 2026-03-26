<?php

namespace Database\Seeders;

use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\StoreModel;
use App\Infrastructure\Eloquent\Models\StoreItemModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $shopLocation = LocationModel::where('name', 'Shop')->first();

        if (!$shopLocation) {
            $this->command->warn('Shop location not found. Skipping Store seeder.');
            return;
        }

        $store = StoreModel::firstOrCreate(
            ['location_id' => $shopLocation->id],
            ['name' => 'General Store']
        );

        $items = ItemModel::all();

        foreach ($items as $item) {
            StoreItemModel::firstOrCreate([
                'store_id' => $store->id,
                'item_id' => $item->id,
            ], [
                'price' => 0,
                'currency_type' => null,
            ]);
        }
    }
}
