<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Store\StoreItem;
use App\Domain\Store\Repositories\StoreItemRepositoryInterface;
use App\Infrastructure\Eloquent\Models\StoreItemModel;

class EloquentStoreItemRepository implements StoreItemRepositoryInterface
{
    public function findById(int $id): ?StoreItem
    {
        /** @var StoreItemModel|null $model */
        $model = StoreItemModel::find($id);
        return $model ? $model->toDomain() : null;
    }

    /**
     * @return StoreItem[]
     */
    public function getByStoreId(int $storeId): array
    {
        $models = StoreItemModel::where('store_id', $storeId)->get();
        return $models->map(function ($model) {
            /** @var StoreItemModel $model */
            return $model->toDomain();
        })->all();
    }

    public function getByStoreIdWithItems(int $storeId): array
    {
        return StoreItemModel::with('item')
            ->where('store_id', $storeId)
            ->get()
            ->map(function (StoreItemModel $model) {
                return [
                    'store_item' => $model->toDomain(),
                    'item' => $model->item, // This is Eloquent model, but we can return it as array or hydrator later
                ];
            })
            ->all();
    }
}
