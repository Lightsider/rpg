<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;

class CharacterStatValidator
{
    /**
     * @param array<string, mixed> $stats
     */
    public function validateStats(array $stats): void
    {
        $requiredKeys = ['str', 'con', 'dex', 'wit'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $stats)) {
                throw new DomainException('All four stats are required.');
            }
        }

        foreach ($requiredKeys as $key) {
            if (!is_int($stats[$key])) {
                throw new DomainException('All stats must be integers.');
            }

            if ($stats[$key] < 0) {
                throw new DomainException('Stats cannot be negative.');
            }
        }

        $totalPool = config('game.stat_pool_level_1');
        $coreRatio = config('game.core_stat_ratio');

        if (!is_numeric($totalPool) || !is_numeric($coreRatio)) {
            throw new DomainException('Stat pool configuration is missing or invalid.');
        }

        $totalPool = (int) $totalPool;
        $coreRatio = (float) $coreRatio;
        $minimumCoreStat = (int) floor($totalPool * $coreRatio);

        $total = array_sum($stats);
        if ($total !== $totalPool) {
            throw new DomainException("Total stat points must equal {$totalPool}");
        }

        if ($stats['str'] < $minimumCoreStat) {
            throw new DomainException('Strength cannot be lower than the minimum allowed value.');
        }

        if ($stats['con'] < $minimumCoreStat) {
            throw new DomainException('Endurance cannot be lower than the minimum allowed value.');
        }
    }
}
