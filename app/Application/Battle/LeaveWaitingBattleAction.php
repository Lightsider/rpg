<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\DomainException;
use App\Application\Contracts\TransactionInterface;

class LeaveWaitingBattleAction
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly TransactionInterface $transaction
    ) {
    }

    public function execute(int $battleId, int $characterId): void
    {
        $this->transaction->run(function () use ($battleId, $characterId) {
            if (!$this->battleRepository->lockForUpdate($battleId)) {
                throw new DomainException('Fight not found.');
            }

            $battle = $this->battleRepository->findById($battleId);
            if (!$battle) {
                throw new DomainException('Fight not found.');
            }

            if ($battle->getState() !== BattleState::WAITING) {
                throw new DomainException('Fight is no longer waiting.');
            }

            if (!$this->battleRepository->hasParticipant($battleId, $characterId)) {
                throw new DomainException('You are not a participant in this fight.');
            }

            $this->battleRepository->removeParticipant($battleId, $characterId);
            $this->battleRepository->delete($battleId);
        });
    }
}
