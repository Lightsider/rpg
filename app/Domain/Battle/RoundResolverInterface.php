<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Interface for the service that resolves all actions in a round.
 */
interface RoundResolverInterface
{
    /**
     * Resolves all queued actions in the battle.
     */
    public function resolve(Battle $battle): RoundResolutionResult;
}
