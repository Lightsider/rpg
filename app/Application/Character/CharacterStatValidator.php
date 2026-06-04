<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\DomainException;

class CharacterStatValidator
{
    /**
     * @param array<string, mixed> $stats
     * @param \App\Domain\Character\Character $character
     */
    public function validateStats(array $stats, \App\Domain\Character\Character $character): void
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

        $coreRatio = config('game.core_stat_ratio');
        if (!is_numeric($coreRatio)) {
            throw new DomainException('Stat pool configuration is missing or invalid.');
        }

        // The total allowed points is the sum of currently allocated stats + unallocated stats.
        $totalAllowed = $character->getStrength() + $character->getAgility() + 
                        $character->getConstitution() + $character->getWit() + 
                        $character->getUnallocatedStats();

        $coreRatio = (float) $coreRatio;
        $minimumCoreStat = (int) floor($totalAllowed * $coreRatio);

        $totalRequested = array_sum($stats);
        if ($totalRequested > $totalAllowed) {
            throw new DomainException("Total stat points requested ({$totalRequested}) exceeds total available ({$totalAllowed}).");
        }

        if ($stats['str'] < $minimumCoreStat) {
            throw new DomainException('Strength cannot be lower than the minimum allowed value.');
        }

        if ($stats['con'] < $minimumCoreStat) {
            throw new DomainException('Constitution cannot be lower than the minimum allowed value.');
        }
    }
}
