<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Value Object representing a battle map with dimensions and spatial logic.
 */
class Map
{
    public function __construct(
        private readonly int $width,
        private readonly int $height
    ) {
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Checks if coordinates are within the map boundaries.
     */
    public function isWithinBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < $this->width && $y >= 0 && $y < $this->height;
    }

    /**
     * Checks if two cells are adjacent (8-directional).
     */
    public function isAdjacent(int $x1, int $y1, int $x2, int $y2): bool
    {
        $dx = abs($x1 - $x2);
        $dy = abs($y1 - $y2);

        return ($dx <= 1 && $dy <= 1) && !($dx === 0 && $dy === 0);
    }
}
