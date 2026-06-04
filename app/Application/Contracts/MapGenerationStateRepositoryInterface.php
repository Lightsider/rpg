<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface MapGenerationStateRepositoryInterface
{
    /**
     * @return array{width:int, height:int}
     */
    public function ensureMap(int $fightId, int $width, int $height): array;

    public function syncBattleMapDimensions(int $fightId, int $width, int $height): void;

    public function moveAllPositionsOffMap(int $fightId): void;

    public function upsertPosition(int $fightId, int $characterId, int $x, int $y): void;
}
