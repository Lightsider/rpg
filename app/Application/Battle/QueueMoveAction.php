<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\TurnAction;
use Exception;

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
     * @throws Exception If battle or character not found, or cannot queue move.
     */
    public function execute(int $battleId, int $characterId, int $toX, int $toY): void
    {
        // 1. Load battle
        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new Exception('Battle not found.');
        }

        // 2. Ensure round not expired
        if ($battle->isRoundExpired()) {
            throw new Exception('Round has expired.');
        }

        // 3. Get character
        $character = $battle->getParticipantById($characterId);
        if (!$character) {
            throw new Exception('Character not found in this battle.');
        }

        // 4. Ensure character alive
        if ($character->getCurrentHp() <= 0) {
            throw new Exception('Character is dead.');
        }

        // 5. Ensure not committed
        if ($battle->isCharacterCommitted($characterId)) {
            throw new Exception('Character has already committed their actions.');
        }

        // 6. Ensure 1 AP available
        if (!$character->canSpendAP(self::MOVE_AP_COST)) {
            throw new Exception('Not enough Action Points to move.');
        }

        // 7. Ensure target cell is adjacent
        if (!$this->isAdjacent($character->getX(), $character->getY(), $toX, $toY)) {
            throw new Exception('Target cell is not adjacent.');
        }

        // 8. Spend 1 AP
        $character->spendAP(self::MOVE_AP_COST);

        // 9. Create MOVE TurnAction
        $action = new TurnAction(
            characterId: $characterId,
            type: ActionType::MOVE,
            fromX: $character->getX(),
            fromY: $character->getY(),
            toX: $toX,
            toY: $toY
        );

        // 10. Add to queue
        $battle->queueAction($action);

        // 11. Save battle
        $this->battleRepository->save($battle);
    }

    /**
     * Checks if two cells are adjacent (including diagonals or only cardinal?)
     * Rules say "Атаковать можно только соседнюю клетку" and "За ход можно перемещаться".
     * Usually in grid games adjacent means distance <= 1 in both coordinates (King's move).
     */
    private function isAdjacent(int $fromX, int $fromY, int $toX, int $toY): bool
    {
        $dx = abs($fromX - $toX);
        $dy = abs($fromY - $toY);

        return ($dx <= 1 && $dy <= 1) && !($dx === 0 && $dy === 0);
    }
}
