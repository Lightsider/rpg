<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use App\Domain\DomainException;
use Illuminate\Support\Facades\DB;

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
     * @throws DomainException If battle not found, not character's turn, or cannot queue attack.
     */
    public function execute(int $battleId, int $characterId, string $targetZone): void
    {
        DB::transaction(function () use ($battleId, $characterId, $targetZone) {
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

            // 3. Validation: alive and not committed
            if ($character->getCurrentHp() <= 0) {
                throw new DomainException('Character is dead.');
            }

            if ($battle->isCharacterCommitted($characterId)) {
                throw new DomainException('Character has already committed their actions.');
            }

            // 4. Ensure character canQueueAttack()
            if (!$character->canQueueAttack()) {
                throw new DomainException('Character cannot queue more attacks or lacks AP.');
            }

            // 5. Create TurnAction of type ATTACK
            $action = new TurnAction(
                $characterId,
                ActionType::ATTACK,
                TargetZone::from($targetZone)
            );

            // 6. Add to battle queue (performs spatial validation: attacker/target alive and adjacent)
            $battle->queueAction($action);

            // 7. Spend 1 AP (only if queueAction didn't throw)
            $character->spendAP(self::ATTACK_AP_COST);

            // 8. Register attack usage
            $character->registerAttackUsage();

            // 9. Save battle
            $this->battleRepository->save($battle);
        });
    }
}
