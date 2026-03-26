<?php

namespace App\Domain\Store;

class StoreItem
{
    private int $id;
    private int $storeId;
    private int $itemId;
    private int $price;
    private ?string $currencyType;

    public function __construct(int $id, int $storeId, int $itemId, int $price, ?string $currencyType)
    {
        $this->id = $id;
        $this->storeId = $storeId;
        $this->itemId = $itemId;
        $this->price = $price;
        $this->currencyType = $currencyType;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getCurrencyType(): ?string
    {
        return $this->currencyType;
    }
}
