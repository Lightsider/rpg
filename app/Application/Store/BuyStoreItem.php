<?php

namespace App\Application\Store;

use App\Domain\DomainException;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Store\Repositories\StoreItemRepositoryInterface;
use App\Domain\Item\Repositories\CharacterItemRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;

class BuyStoreItem
{
    private CharacterRepositoryInterface $characterRepository;
    private StoreItemRepositoryInterface $storeItemRepository;
    private CharacterItemRepositoryInterface $characterItemRepository;
    private ItemRepositoryInterface $itemRepository;

    public function __construct(
        CharacterRepositoryInterface $characterRepository,
        StoreItemRepositoryInterface $storeItemRepository,
        CharacterItemRepositoryInterface $characterItemRepository,
        ItemRepositoryInterface $itemRepository
    ) {
        $this->characterRepository = $characterRepository;
        $this->storeItemRepository = $storeItemRepository;
        $this->characterItemRepository = $characterItemRepository;
        $this->itemRepository = $itemRepository;
    }

    public function execute(int $characterId, int $storeItemId): void
    {
        $character = $this->characterRepository->findById($characterId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $storeItem = $this->storeItemRepository->findById($storeItemId);
        if (!$storeItem) {
            throw new DomainException('Store item not found.');
        }

        $item = $this->itemRepository->findById($storeItem->getItemId());
        if (!$item) {
            throw new DomainException('Item not found.');
        }

        // MVP: price is always 0.
        // We'll skip currency check/deduction here.

        // Add item to character's inventory (backpack)
        $this->characterItemRepository->addToBackpack($characterId, $item->getId());
    }
}
