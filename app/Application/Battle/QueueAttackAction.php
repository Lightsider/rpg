<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use Exception;

/**
 * Application service to queue an attack action in a battle.
 */
class QueueAttackAction
{
    private const int ATTACK_AP_COST = 1;

    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    /**
     * Executes the queue attack logic.
     *
     * @throws Exception If battle not found, not character's turn, or cannot queue attack.
     */
    public function execute(int $battleId, int $characterId, string $targetZone): void
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

        // 3. Validation: alive and not committed
        if ($character->getCurrentHp() <= 0) {
            throw new Exception('Character is dead.');
        }

        if ($battle->isCharacterCommitted($characterId)) {
            throw new Exception('Character has already committed their actions.');
        }

        // 4. Ensure character canQueueAttack()
        if (!$character->canQueueAttack()) {
            throw new Exception('Character cannot queue more attacks or lacks AP.');
        }

        // 5. Spend 1 AP
        $character->spendAP(self::ATTACK_AP_COST);

        // 6. Register attack usage
        $character->registerAttackUsage();

        // 7. Create TurnAction of type ATTACK
        $action = new TurnAction(
            $characterId,
            ActionType::ATTACK,
            TargetZone::from($targetZone)
        );

        // 8. Add to battle queue
        $battle->queueAction($action);

        // 9. Save battle
        $this->battleRepository->save($battle);
    }
}
