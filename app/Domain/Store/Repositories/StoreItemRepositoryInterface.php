<?php

namespace App\Domain\Store\Repositories;

use App\Domain\Store\StoreItem;

interface StoreItemRepositoryInterface
{
    public function findById(int $id): ?StoreItem;
    
    /**
     * @param int $storeId
     * @return StoreItem[]
     */
    public function getByStoreId(int $storeId): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getByStoreIdWithItems(int $storeId): array;
}
