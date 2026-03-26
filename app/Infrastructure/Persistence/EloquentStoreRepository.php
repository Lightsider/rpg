<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Store\Store;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Infrastructure\Eloquent\Models\StoreModel;

class EloquentStoreRepository implements StoreRepositoryInterface
{
    public function findById(int $id): ?Store
    {
        /** @var StoreModel|null $model */
        $model = StoreModel::find($id);
        return $model ? $model->toDomain() : null;
    }

    public function findByLocationId(int $locationId): ?Store
    {
        /** @var StoreModel|null $model */
        $model = StoreModel::where('location_id', $locationId)->first();
        return $model ? $model->toDomain() : null;
    }
}
