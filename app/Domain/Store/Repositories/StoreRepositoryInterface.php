<?php

namespace App\Domain\Store\Repositories;

use App\Domain\Store\Store;

interface StoreRepositoryInterface
{
    public function findById(int $id): ?Store;
    public function findByLocationId(int $locationId): ?Store;
}
