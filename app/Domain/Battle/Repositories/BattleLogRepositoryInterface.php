<?php

declare(strict_types=1);

namespace App\Domain\Battle\Repositories;

use App\Domain\Battle\BattleLogEntry;

interface BattleLogRepositoryInterface
{
    /**
     * @param int $battleId
     * @param BattleLogEntry[] $entries
     */
    public function saveBatch(int $battleId, array $entries): void;

    /**
     * @param int $battleId
     * @return array<int, array> Returns logs grouped by round
     */
    public function findByBattleId(int $battleId): array;
}
