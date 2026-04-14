<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

/**
 * Application service to submit a full set of actions for a character in a battle.
 */
class SubmitBattleActions
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly QueueAttackAction $queueAttackAction,
        private readonly QueueOffhandAttackAction $queueOffhandAttackAction,
        private readonly QueueDefenseAction $queueDefenseAction,
        private readonly QueueMoveAction $queueMoveAction,
        private readonly CommitRoundAction $commitRoundAction,
        private readonly RoundExpirationHandler $roundExpirationHandler
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array{battle: Battle, timer_remaining: int, actions_submitted: array<int>}
     */
    public function execute(int $battleId, int $userId, array $actions): array
    {
        $this->roundExpirationHandler->handleExpiredRounds();

        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new DomainException('Fight not found.');
        }

        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        if (!$battle->getParticipantById($character->getId())) {
            throw new DomainException('You are not a participant in this fight.');
        }

        $nonOffhandActions = collect($actions)->where('type', '!=', 'attack_offhand');
        $offhandActions = collect($actions)->where('type', 'attack_offhand');

        // 1. Off-hand Dagger Limit
        if ($offhandActions->count() > $character->getBonusOffhandAP()) {
            throw new DomainException('Cannot use more off-hand attacks than available bonus AP.');
        }

        // 2. Main Attack Limit
        $mainAttacks = $nonOffhandActions->where('type', 'attack')->count();
        if ($mainAttacks > 2) {
            throw new DomainException('Maximum 2 main-hand attacks per round.');
        }

        // 3. AP Pool Partitioning (General AP vs Defensive Bonus)
        $maxGeneral = 3;
        $bonusDefensive = $character->getBonusDefensiveAP();

        // Calculate total AP cost of non-offhand actions
        $totalNonOffhandCost = 0;
        foreach ($nonOffhandActions as $action) {
            if ($action['type'] === 'move') {
                $totalNonOffhandCost += 1 + count($action['blocks'] ?? []);
            } else {
                $totalNonOffhandCost += 1;
            }
        }

        if ($totalNonOffhandCost > ($maxGeneral + $bonusDefensive)) {
            throw new DomainException('Not enough Action Points for these actions.');
        }

        if ($totalNonOffhandCost > $maxGeneral) {
            // The excess AP must be accounted for by blocks
            $blocksCount = $nonOffhandActions->where('type', 'block')->count();
            $moveBlocks = $nonOffhandActions->where('type', 'move')->sum(fn($a) => count($a['blocks'] ?? []));
            $totalBlocks = $blocksCount + $moveBlocks;

            if ($totalBlocks < ($totalNonOffhandCost - $maxGeneral)) {
                throw new DomainException('Extra action from shield can only be used for defense.');
            }
        }

        foreach ($actions as $actionData) {
            $type = $actionData['type'];
            $zone = $actionData['zone'] ?? null;
            if ($zone === 'body') {
                $zone = 'torso';
            }

            if ($type === 'attack') {
                if (!$zone) {
                    throw new DomainException('Attack action is missing target zone.');
                }
                $targetId = isset($actionData['target_id']) ? (int) $actionData['target_id'] : null;
                $this->queueAttackAction->execute($battleId, $character->getId(), $zone, $targetId);
            } elseif ($type === 'attack_offhand') {
                if (!$zone) {
                    throw new DomainException('Offhand attack action is missing target zone.');
                }
                $targetId = isset($actionData['target_id']) ? (int) $actionData['target_id'] : null;
                $this->queueOffhandAttackAction->execute($battleId, $character->getId(), $zone, $targetId);
            } elseif ($type === 'block') {
                if (!$zone) {
                    throw new DomainException('Block action is missing target zone.');
                }
                $this->queueDefenseAction->execute($battleId, $character->getId(), $zone);
            } elseif ($type === 'move') {
                $target = $actionData['target'] ?? null;
                if (!is_array($target) || !array_key_exists('x', $target) || !array_key_exists('y', $target)) {
                    throw new DomainException('Move action is missing target coordinates.');
                }
                $blocks = $actionData['blocks'] ?? [];
                $this->queueMoveAction->execute(
                    $battleId,
                    $character->getId(),
                    (int) $target['x'],
                    (int) $target['y'],
                    $blocks
                );
            }
        }

        $this->commitRoundAction->execute($battleId, $character->getId());

        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new DomainException('Fight not found.');
        }

        return [
            'battle' => $battle,
            'timer_remaining' => $this->calculateTimerRemaining($battle),
            'actions_submitted' => $battle->getCommittedCharacterIds(),
        ];
    }

    private function calculateTimerRemaining(Battle $battle): int
    {
        $now = new \DateTimeImmutable();
        $expiryTime = $battle->getRoundStartedAt()->modify("+{$battle->getRoundDurationSeconds()} seconds");
        return max(0, $expiryTime->getTimestamp() - $now->getTimestamp());
    }
}
