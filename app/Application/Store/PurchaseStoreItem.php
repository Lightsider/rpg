<?php

declare(strict_types=1);

namespace App\Application\Store;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class PurchaseStoreItem
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BuyStoreItem $buyStoreItem
    ) {
    }

    public function execute(int $userId, int $storeItemId): int
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found');
        }

        $this->buyStoreItem->execute($character->getId(), $storeItemId);

        return $character->getId();
    }
}
