<?php

declare(strict_types=1);

namespace App\Domain\Battle\Repositories;

interface FighterPositionRepositoryInterface
{
    /**
     * @param array<int, array{x:int,y:int}> $resolvedMoves keyed by character id
     */
    public function applyResolvedMoves(int $battleId, array $resolvedMoves): void;
}

