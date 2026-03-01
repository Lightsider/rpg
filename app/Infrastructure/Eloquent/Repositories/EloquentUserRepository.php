<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\User\Entities\UserEntity;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Infrastructure\Eloquent\Models\User as EloquentUser;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(UserEntity $user): UserEntity
    {
        $model = EloquentUser::create($user->toArray());
        return $this->mapToEntity($model);
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $model = EloquentUser::where('email', $email)->first();
        return $model ? $this->mapToEntity($model) : null;
    }

    public function findById(int $id): ?UserEntity
    {
        $model = EloquentUser::find($id);
        return $model ? $this->mapToEntity($model) : null;
    }

    public function findByIdModel(int $id): ?EloquentUser
    {
        return EloquentUser::find($id);
    }

    public function update(UserEntity $user): UserEntity
    {
        $model = EloquentUser::findOrFail($user->id);
        $model->update($user->toArray());
        return $this->mapToEntity($model);
    }

    private function mapToEntity(EloquentUser $user): UserEntity
    {
        return UserEntity::create($user->getAttributes());
    }
}
