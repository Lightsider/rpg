<?php

namespace App\Domain\User\Repositories;

use App\Domain\User\Entities\UserEntity;

interface UserRepositoryInterface
{
    public function create(UserEntity $user): UserEntity;
    public function findByEmail(string $email): ?UserEntity;
    public function findById(int $id): ?UserEntity;
    public function update(UserEntity $user): UserEntity;
}
