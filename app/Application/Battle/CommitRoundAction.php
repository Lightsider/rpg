<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolverInterface;
use Exception;

/**
 * Application service to commit a character's actions and potentially resolve the round.
 */
class CommitRoundAction
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly RoundResolverInterface $roundResolver
    ) {
    }

    /**
     * Executes the commit round (commitment) logic.
     *
     * @throws Exception If battle not found or round expired.
     */
    public function execute(int $battleId, int $characterId): void
    {
        // 1. Load battle
        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new Exception('Battle not found.');
        }

        // 2. Ensure round active
        if ($battle->isRoundExpired()) {
            throw new Exception('Round has expired.');
        }

        // 3. Mark character as committed
        $battle->commitCharacter($characterId);

        // 4. If ALL alive participants have committed
        if ($battle->areAllCommitted()) {
            // → resolve round
            $this->roundResolver->resolve($battle);

            // → start new round (increments number, clears queue/commitments, resets character states)
            $battle->startNewRound();
        }

        // 5. Save battle
        $this->battleRepository->save($battle);
    }
}
