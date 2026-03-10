<?php

declare(strict_types=1);

namespace App\Domain\Location;

class Location implements \JsonSerializable
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $description,
        private readonly array $connectedLocationIds = [],
        private readonly array $npcIds = [],
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getConnectedLocationIds(): array
    {
        return $this->connectedLocationIds;
    }

    public function getNpcIds(): array
    {
        return $this->npcIds;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'connected_locations' => $this->getConnectedLocationIds(),
            'npc_list' => $this->getNpcIds(),
            'available_fights' => [], // Placeholder for future-ready field
        ];
    }
}
