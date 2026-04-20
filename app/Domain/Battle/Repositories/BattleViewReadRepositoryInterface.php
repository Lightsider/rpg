<?php

declare(strict_types=1);

namespace App\Domain\Battle\Repositories;

interface BattleViewReadRepositoryInterface
{
    /**
     * @return array{width:int,height:int,positions:array<int,array{character_id:int,x:int,y:int}>}
     */
    public function getMapSnapshot(int $battleId, int $fallbackWidth, int $fallbackHeight): array;
}

