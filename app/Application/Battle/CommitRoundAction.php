<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolverInterface;
use App\Domain\DomainException;
use Illuminate\Support\Facades\DB;

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
     * @throws DomainException If battle not found or round expired.
     */
    public function execute(int $battleId, int $characterId): void
    {
        DB::transaction(function () use ($battleId, $characterId) {
            // 1. Load battle
            $battle = $this->battleRepository->findById($battleId);
            if (!$battle) {
                throw new DomainException('Battle not found.');
            }

            // 2. Validate state (Must be ACTIVE for commitments)
            if ($battle->getState() !== BattleState::ACTIVE) {
                throw new DomainException('Battle is not in ACTIVE state for commitments.');
            }

            // 3. Ensure round not expired
            if ($battle->isRoundExpired()) {
                throw new DomainException('Round has expired.');
            }

            // 4. If already committed → do nothing (Idempotency)
            if ($battle->isCharacterCommitted($characterId)) {
                return;
            }

            // 5. Mark character as committed
            $battle->commitCharacter($characterId);

            // 6. If ALL alive participants have committed
            if ($battle->areAllCommitted()) {
                // Double check it's STILL ACTIVE (double safety against race conditions in transactions)
                if ($battle->getState() === BattleState::ACTIVE) {
                    // → resolve round
                    $this->roundResolver->resolve($battle);

                    // → start new round (if not finished)
                    if ($battle->getState() !== BattleState::FINISHED) {
                        $battle->startNewRound();
                    }
                }
            }

            // 7. Save battle
            $this->battleRepository->save($battle);
        });
    }
}
