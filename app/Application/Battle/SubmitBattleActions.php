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
        private readonly QueueDefenseAction $queueDefenseAction,
        private readonly QueueMoveAction $queueMoveAction,
        private readonly CommitRoundAction $commitRoundAction
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array{battle: Battle, timer_remaining: int, actions_submitted: array<int>}
     */
    public function execute(int $battleId, int $userId, array $actions): array
    {
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

        $attacksCount = collect($actions)->where('type', 'attack')->count();
        if ($attacksCount > 2) {
            throw new DomainException('Maximum 2 attacks per round.');
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
                $this->queueAttackAction->execute($battleId, $character->getId(), $zone);
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
