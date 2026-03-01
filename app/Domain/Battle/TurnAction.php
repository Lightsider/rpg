<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Immutable Value Object representing a turn action.
 */
class TurnAction
{
    public function __construct(
        private readonly int $characterId,
        private readonly ActionType $type,
        private readonly ?TargetZone $targetZone = null,
        private readonly ?int $fromX = null,
        private readonly ?int $fromY = null,
        private readonly ?int $toX = null,
        private readonly ?int $toY = null
    ) {
    }

    public function getCharacterId(): int
    {
        return $this->characterId;
    }

    public function getType(): ActionType
    {
        return $this->type;
    }

    public function getTargetZone(): ?TargetZone
    {
        return $this->targetZone;
    }

    public function getFromX(): ?int
    {
        return $this->fromX;
    }

    public function getFromY(): ?int
    {
        return $this->fromY;
    }

    public function getToX(): ?int
    {
        return $this->toX;
    }

    public function getToY(): ?int
    {
        return $this->toY;
    }
}
