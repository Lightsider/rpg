<?php

namespace App\Domain\Store;

class Store
{
    private int $id;
    private string $name;
    private int $locationId;

    public function __construct(int $id, string $name, int $locationId)
    {
        $this->id = $id;
        $this->name = $name;
        $this->locationId = $locationId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLocationId(): int
    {
        return $this->locationId;
    }
}
