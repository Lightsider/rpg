<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Item\Item;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\WeaponHydrator;
use App\Infrastructure\Eloquent\ArmorHydrator;
use App\Infrastructure\Eloquent\SealHydrator;

class EloquentItemRepository implements ItemRepositoryInterface
{
    public function __construct(
        private readonly WeaponHydrator $weaponHydrator,
        private readonly ArmorHydrator $armorHydrator,
        private readonly SealHydrator $sealHydrator
    ) {
    }

    public function findById(int $id): ?Item
    {
        $model = ItemModel::find($id);
        if (!$model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    public function findAll(): array
    {
        return ItemModel::all()->map(fn(ItemModel $model) => $this->mapToDomain($model))->filter()->values()->toArray();
    }

    private function mapToDomain(ItemModel $model): ?Item
    {
        return match ($model->type) {
            'weapon', 'offhand_weapon' => $this->weaponHydrator->fromItem($model),
            'armor', 'helmet', 'chest', 'legs', 'gloves', 'shield' => $this->armorHydrator->fromItem($model),
            'seal' => $this->sealHydrator->fromItem($model),
            default => null,
        };
    }
}
