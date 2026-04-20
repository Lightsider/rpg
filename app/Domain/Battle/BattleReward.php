<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use JsonSerializable;

/**
 * Value Object representing the rewards from a battle.
 */
class BattleReward implements JsonSerializable
{
    /**
     * @param array<int> $items List of item IDs (optional/future)
     */
    public function __construct(
        public readonly int $xp = 0,
        public readonly int $copper = 0,
        public readonly array $items = []
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'xp' => $this->xp,
            'copper' => $this->copper,
            'items' => $this->items,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            xp: $data['xp'] ?? 0,
            copper: $data['copper'] ?? 0,
            items: $data['items'] ?? []
        );
    }
}
