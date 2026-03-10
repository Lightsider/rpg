<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\TurnAction;
use App\Domain\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Application service to queue a move action in a battle.
 */
class QueueMoveAction
{
    private const int MOVE_AP_COST = 1;

    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    /**
     * Executes the queue move logic.
     *
     * @throws DomainException If battle or character not found, or cannot queue move.
     */
    public function execute(int $battleId, int $characterId, int $toX, int $toY): void
    {
        DB::transaction(function () use ($battleId, $characterId, $toX, $toY) {
            // 1. Load battle
            $battle = $this->battleRepository->findById($battleId);
            if (!$battle) {
                throw new DomainException('Battle not found.');
            }

            // 2. Ensure round not expired
            if ($battle->isRoundExpired()) {
                throw new DomainException('Round has expired.');
            }

            // 3. Get character
            $character = $battle->getParticipantById($characterId);
            if (!$character) {
                throw new DomainException('Character not found in this battle.');
            }

            // 4. Ensure character alive
            if ($character->getCurrentHp() <= 0) {
                throw new DomainException('Character is dead.');
            }

            // 5. Ensure not committed
            if ($battle->isCharacterCommitted($characterId)) {
                throw new DomainException('Character has already committed their actions.');
            }

            // 6. Ensure enough AP
            if (!$character->canSpendAP(self::MOVE_AP_COST)) {
                throw new DomainException('Not enough Action Points to move.');
            }

            // 7. Create MOVE TurnAction
            $action = new TurnAction(
                characterId: $characterId,
                type: ActionType::MOVE,
                fromX: $character->getX(),
                fromY: $character->getY(),
                toX: $toX,
                toY: $toY
            );

            // 8. Add to queue (performs spatial validation: bounds, adjacency, occupancy)
            $battle->queueAction($action);

            // 9. Spend AP (only if queueAction didn't throw)
            $character->spendAP(self::MOVE_AP_COST);

            // 11. Save battle
            $this->battleRepository->save($battle);
        });
    }
}
