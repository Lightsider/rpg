<?php

namespace App\Domain\Item\Repositories;

use App\Domain\Item\Item;

interface ItemRepositoryInterface
{
    public function findById(int $id): ?Item;
}
