<?php

declare(strict_types=1);

namespace App\Application\Store;

use App\Application\Contracts\EventDispatcherInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Events\InventoryUpdated;

class PurchaseStoreItem
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BuyStoreItem $buyStoreItem,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function execute(int $userId, int $storeItemId): int
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $this->buyStoreItem->execute($character->getId(), $storeItemId);
        $this->eventDispatcher->dispatch(new InventoryUpdated($character->getId()));

        return $character->getId();
    }
}
