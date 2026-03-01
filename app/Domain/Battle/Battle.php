<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Character\Character;
use DateTimeImmutable;
use Exception;

/**
 * Pure PHP Domain Model for a Battle.
 */
class Battle
{
    private const int DEFAULT_ROUND_DURATION = 60;

    /**
     * @param array<int, Character> $participants
     * @param array<int, TurnAction> $queuedActions
     * @param array<int> $committedCharacterIds
     */
    public function __construct(
        private readonly int $id,
        private readonly array $participants,
        private int $roundNumber = 1,
        private DateTimeImmutable $roundStartedAt = new DateTimeImmutable(),
        private int $roundDurationSeconds = self::DEFAULT_ROUND_DURATION,
        private bool $isFinished = false,
        private array $queuedActions = [],
        private array $committedCharacterIds = []
    ) {
    }

    /**
     * Starts a new round.
     */
    public function startNewRound(): void
    {
        if ($this->isFinished) {
            throw new Exception('Cannot start a new round in a finished battle.');
        }

        $this->roundNumber++;
        $this->roundStartedAt = new DateTimeImmutable();
        $this->committedCharacterIds = [];
        $this->queuedActions = [];

        foreach ($this->participants as $participant) {
            $participant->resetRoundState();
        }
    }

    /**
     * Checks if the current round has expired.
     */
    public function isRoundExpired(): bool
    {
        $now = new DateTimeImmutable();
        $expiryTime = $this->roundStartedAt->modify("+{$this->roundDurationSeconds} seconds");

        return $now >= $expiryTime;
    }

    /**
     * Commits a character's actions for the current round.
     */
    public function commitCharacter(int $characterId): void
    {
        if (!in_array($characterId, $this->committedCharacterIds, true)) {
            $this->committedCharacterIds[] = $characterId;
        }
    }

    /**
     * Returns true if all alive participants have committed their actions.
     */
    public function areAllCommitted(): bool
    {
        $aliveParticipants = array_filter(
            $this->participants,
            fn(Character $c) => $c->getCurrentHp() > 0
        );

        return count($this->committedCharacterIds) === count($aliveParticipants);
    }

    public function queueAction(TurnAction $action): void
    {
        if ($this->isFinished) {
            throw new Exception('Cannot queue action for a finished battle.');
        }

        if (in_array($action->getCharacterId(), $this->committedCharacterIds, true)) {
            throw new Exception('Character has already committed their actions.');
        }

        $this->queuedActions[] = $action;
    }

    /**
     * @return array<int, TurnAction>
     */
    public function getQueuedActionsForCharacter(int $characterId): array
    {
        return array_filter(
            $this->queuedActions,
            fn(TurnAction $action) => $action->getCharacterId() === $characterId
        );
    }

    public function clearQueuedActions(): void
    {
        $this->queuedActions = [];
    }

    /**
     * Sets isFinished to true if only one character (or zero) has HP > 0.
     */
    public function checkIfFinished(): void
    {
        $aliveCount = count(array_filter(
            $this->participants,
            fn(Character $c) => $c->getCurrentHp() > 0
        ));

        if ($aliveCount <= 1) {
            $this->isFinished = true;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return array<int, Character>
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function getParticipantById(int $characterId): ?Character
    {
        foreach ($this->participants as $participant) {
            if ($participant->getId() === $characterId) {
                return $participant;
            }
        }

        return null;
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function getRoundStartedAt(): DateTimeImmutable
    {
        return $this->roundStartedAt;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    /**
     * @return array<int, TurnAction>
     */
    public function getQueuedActions(): array
    {
        return $this->queuedActions;
    }

    /**
     * @return array<int>
     */
    public function getCommittedCharacterIds(): array
    {
        return $this->committedCharacterIds;
    }

    public function isCharacterCommitted(int $characterId): bool
    {
        return in_array($characterId, $this->committedCharacterIds, true);
    }
}
