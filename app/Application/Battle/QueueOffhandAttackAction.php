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
 * Application service to queue an offhand attack action in a battle.
 */
class QueueOffhandAttackAction
{
    private const int ATTACK_AP_COST = 1;

    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly ?TransactionInterface $transaction = null
    ) {
    }

    /**
     * Executes the queue offhand attack logic.
     *
     * @throws DomainException If battle not found, not character's turn, or cannot queue attack.
     */
    public function execute(int $battleId, int $characterId, string $targetZone, ?int $targetCharacterId = null): void
    {
        $this->withinTransaction(function () use ($battleId, $characterId, $targetZone, $targetCharacterId) {
            $battle = $this->battleRepository->findById($battleId);
            if (!$battle) {
                throw new DomainException('Battle not found.');
            }

            if ($battle->isRoundExpired()) {
                throw new DomainException('Round has expired.');
            }

            $character = $battle->getParticipantById($characterId);
            if (!$character) {
                throw new DomainException('Character not found in this battle.');
            }

            if ($character->getCurrentHp() <= 0) {
                throw new DomainException('Character is dead.');
            }

            if ($battle->isCharacterCommitted($characterId)) {
                throw new DomainException('Character has already committed their actions.');
            }

            if (!$character->canQueueOffhandAttack()) {
                throw new DomainException('Character cannot queue more offhand attacks or lacks AP.');
            }

            $action = new TurnAction(
                $characterId,
                ActionType::ATTACK_OFFHAND,
                TargetZone::from($targetZone),
                $targetCharacterId
            );

            $battle->queueAction($action);

            $character->spendAP(self::ATTACK_AP_COST);
            $character->registerOffhandAttackUsage();

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
