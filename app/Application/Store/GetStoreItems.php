<?php

namespace App\Application\Store;

use App\Domain\DomainException;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Domain\Store\Repositories\StoreItemRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;

class GetStoreItems
{
    private StoreRepositoryInterface $storeRepository;
    private StoreItemRepositoryInterface $storeItemRepository;
    private ItemRepositoryInterface $itemRepository;

    public function __construct(
        StoreRepositoryInterface $storeRepository,
        StoreItemRepositoryInterface $storeItemRepository,
        ItemRepositoryInterface $itemRepository
    ) {
        $this->storeRepository = $storeRepository;
        $this->storeItemRepository = $storeItemRepository;
        $this->itemRepository = $itemRepository;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function execute(int $storeId): array
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new DomainException('Store not found.');
        }

        $itemsWithDetails = $this->storeItemRepository->getByStoreIdWithItems($storeId);
        $result = [];

        foreach ($itemsWithDetails as $detail) {
            $storeItem = $detail['store_item'];
            $itemModel = $detail['item']; // This is the Eloquent model

            if ($itemModel) {
                $result[] = [
                    'store_item_id' => $storeItem->getId(),
                    'store_id' => $storeItem->getStoreId(),
                    'price' => $storeItem->getPrice(),
                    'currency_type' => $storeItem->getCurrencyType(),
                    'item' => [
                        'id' => $itemModel->id,
                        'name' => $itemModel->name,
                        'type' => $itemModel->type,
                        'min_damage' => $itemModel->min_damage,
                        'max_damage' => $itemModel->max_damage,
                        'damage_type' => $itemModel->damage_type,
                        'flat_crit_bonus' => $itemModel->flat_crit_bonus,
                        'crit_chance_bonus' => $itemModel->crit_chance_bonus,
                        'max_damage_rating' => $itemModel->max_damage_rating,
                        'archetype' => $itemModel->archetype,
                        'required_strength' => $itemModel->required_strength,
                        'required_wit' => $itemModel->required_wit,
                        'required_dexterity' => $itemModel->required_dexterity,
                        'required_constitution' => $itemModel->required_constitution,
                    ],
                ];
            }
        }

        return $result;
    }
}










