<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use App\Domain\DomainException;
use App\Application\Contracts\TransactionInterface;

/**
 * Application service to queue a move action in a battle.
 */
class QueueMoveAction
{
    private const int MOVE_AP_COST = 1;

    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly ?TransactionInterface $transaction = null
    ) {
    }

    /**
     * Executes the queue move logic.
     *
     * @param array<int, string> $blocks
     * @throws DomainException If battle or character not found, or cannot queue move.
     */
    public function execute(int $battleId, int $characterId, int $toX, int $toY, array $blocks = []): void
    {
        $this->withinTransaction(function () use ($battleId, $characterId, $toX, $toY, $blocks) {
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
            $uniqueBlocks = array_values(array_unique($blocks));
            if (count($blocks) !== count($uniqueBlocks)) {
                throw new DomainException('Duplicate block zones are not allowed.');
            }
            foreach ($blocks as $zone) {
                TargetZone::from($zone);
            }

            $existingBlockZones = [];
            foreach ($battle->getQueuedActionsForCharacter($characterId) as $queuedAction) {
                if ($queuedAction->getType() === ActionType::DEFEND && $queuedAction->getTargetZone() !== null) {
                    $existingBlockZones[] = $queuedAction->getTargetZone()->value;
                }

                if ($queuedAction->getType() === ActionType::MOVE) {
                    foreach ($queuedAction->getBlocks() as $blockZone) {
                        $existingBlockZones[] = $blockZone;
                    }
                }
            }
            foreach ($blocks as $zone) {
                if (in_array($zone, $existingBlockZones, true)) {
                    throw new DomainException('Duplicate block zones are not allowed.');
                }
            }

            $totalCost = self::MOVE_AP_COST + count($blocks);
            if (!$character->canSpendAP($totalCost)) {
                throw new DomainException('Not enough Action Points to move with blocks.');
            }

            // 7. Create MOVE TurnAction
            $action = new TurnAction(
                characterId: $characterId,
                type: ActionType::MOVE,
                fromX: $character->getX(),
                fromY: $character->getY(),
                toX: $toX,
                toY: $toY,
                blocks: $blocks
            );

            // 8. Add to queue (performs spatial validation: bounds, adjacency, occupancy)
            $battle->queueAction($action);

            // 9. Spend AP (only if queueAction didn't throw)
            $character->spendAP($totalCost);

            // 11. Save battle
            $this->battleRepository->save($battle);
        });
    }

    private function withinTransaction(callable $callback): void
    {
        if ($this->transaction === null) {
            $callback();
            return;
        }

        $this->transaction->run($callback);
    }
}





