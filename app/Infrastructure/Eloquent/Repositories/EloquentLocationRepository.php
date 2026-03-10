<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Location\Location;
use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Infrastructure\Eloquent\Models\LocationModel;

class EloquentLocationRepository implements LocationRepositoryInterface
{
    public function findById(int $id): ?Location
    {
        /** @var \App\Infrastructure\Eloquent\Models\LocationModel|null $model */
        $model = LocationModel::find($id);
        if (!$model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    public function findAll(): array
    {
        $models = LocationModel::all();

        return $models->map(fn(LocationModel $model) => $this->mapToDomain($model))->toArray();
    }

    private function mapToDomain(LocationModel $model): Location
    {
        return new Location(
            id: (int) $model->id,
            name: (string) $model->name,
            description: (string) ($model->description ?? ''),
            connectedLocationIds: [], // To be implemented later
            npcIds: [], // To be implemented later
        );
    }
}
