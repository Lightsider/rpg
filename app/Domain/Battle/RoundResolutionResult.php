<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Value Object containing the results of a round resolution.
 */
class RoundResolutionResult
{
    /**
     * @param BattleLogEntry[] $logs
     */
    public function __construct(
        public array $logs = []
    ) {
    }
}
