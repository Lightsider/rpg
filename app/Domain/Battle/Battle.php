<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Character\Character;
use DateTimeImmutable;
use Exception;

/**
 * Pure PHP Domain Model for a Battle.
 */
class Battle implements \JsonSerializable
{
    private const int DEFAULT_ROUND_DURATION = 60;
    private const int STARTING_LEFT_X = 0;
    private const int STARTING_RIGHT_OFFSET = 1;
    private const int STARTING_ROW_DIVISOR = 2;
    private const int MAX_PARTICIPANTS = 2;

    /**
     * @param array<int, Character> $participants
     * @param array<int, TurnAction> $queuedActions
     * @param array<int> $committedCharacterIds
     */
    public function __construct(
        private readonly int $id,
        private readonly int $locationId,
        private array $participants,
        private readonly Map $map,
        private int $roundNumber = 1,
        private DateTimeImmutable $roundStartedAt = new DateTimeImmutable(),
        private int $roundDurationSeconds = self::DEFAULT_ROUND_DURATION,
        private BattleState $state = BattleState::ACTIVE,
        private array $queuedActions = [],
        private array $committedCharacterIds = []
    ) {
    }

    public function addParticipant(Character $character): void
    {
        if ($this->state !== BattleState::WAITING) {
            throw new Exception('Can only join a battle in WAITING state.');
        }

        if (count($this->participants) >= self::MAX_PARTICIPANTS) {
            throw new Exception('Battle is already full.');
        }

        if (array_key_exists($character->getId(), $this->participants)) {
            throw new Exception('Character already in battle.');
        }

        $this->participants[$character->getId()] = $character;

        if (count($this->participants) === self::MAX_PARTICIPANTS) {
            $this->state = BattleState::ACTIVE;
            $this->roundStartedAt = new DateTimeImmutable();
            // Assign initial positions for 1v1 based on the current map size.
            $chars = array_values($this->participants);
            $startY = intdiv($this->map->getHeight(), self::STARTING_ROW_DIVISOR);
            $leftX = self::STARTING_LEFT_X;
            $rightX = $this->map->getWidth() - self::STARTING_RIGHT_OFFSET;
            $chars[0]->setPosition($leftX, $startY);
            $chars[1]->setPosition($rightX, $startY);
        }
    }

    /**
     * Starts a new round.
     */
    public function startNewRound(): void
    {
        if ($this->state === BattleState::FINISHED) {
            throw new Exception('Cannot start a new round in a finished battle.');
        }

        $this->roundNumber++;
        $this->roundStartedAt = new DateTimeImmutable();
        $this->committedCharacterIds = [];
        $this->queuedActions = [];
        $this->state = BattleState::ACTIVE;

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

    public function commitCharacter(int $characterId): void
    {
        if ($this->state !== BattleState::ACTIVE) {
            throw new Exception('Can only commit actions in ACTIVE state.');
        }

        $character = $this->getParticipantById($characterId);
        if ($character) {
            $character->commit();
        }

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
        if ($this->state !== BattleState::ACTIVE) {
            throw new Exception('Cannot queue action: battle is not in ACTIVE state.');
        }

        if (in_array($action->getCharacterId(), $this->committedCharacterIds, true)) {
            throw new Exception('Character has already committed their actions.');
        }

        $character = $this->getParticipantById($action->getCharacterId());
        if (!$character) {
            throw new Exception('Character not found in this battle.');
        }

        if ($character->getCurrentHp() <= 0) {
            throw new \App\Domain\DomainException('Cannot queue action: character is dead.');
        }

        if ($action->getType() === ActionType::MOVE) {
            $toX = $action->getToX();
            $toY = $action->getToY();

            if (!$this->map->isWithinBounds($toX, $toY)) {
                throw new \App\Domain\DomainException('Target cell is outside the map.');
            }

            $dx = abs($character->getX() - $toX);
            $dy = abs($character->getY() - $toY);
            if ($dx > 1 || $dy > 1) {
                throw new \App\Domain\DomainException('Target cell is not adjacent.');
            }
        }

        if ($action->getType() === ActionType::ATTACK) {
            // Find opponent (Assuming 1v1 as per rules)
            $opponent = $this->getOpponent($action->getCharacterId());
            if (!$opponent) {
                throw new \App\Domain\DomainException('No opponent found to attack.');
            }

            if ($opponent->getCurrentHp() <= 0) {
                throw new \App\Domain\DomainException('Target is already dead.');
            }

            $dx = abs($character->getX() - $opponent->getX());
            $dy = abs($character->getY() - $opponent->getY());

            if ($dx > 1 || $dy > 1) {
                throw new \App\Domain\DomainException('Target is not adjacent.');
            }
        }

        $this->queuedActions[] = $action;
    }

    private function getOpponent(int $characterId): ?Character
    {
        foreach ($this->participants as $participant) {
            if ($participant->getId() !== $characterId) {
                return $participant;
            }
        }
        return null;
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

    public function startResolving(): void
    {
        if ($this->state !== BattleState::ACTIVE) {
            throw new Exception('Round can resolve ONLY if state is ACTIVE.');
        }
        $this->state = BattleState::RESOLVING;
    }

    public function finishResolving(): void
    {
        if ($this->state !== BattleState::RESOLVING) {
            throw new Exception('Cannot finish resolving: not in RESOLVING state.');
        }

        $this->checkIfFinished();

        if ($this->state !== BattleState::FINISHED) {
            $this->state = BattleState::ACTIVE;
        }
    }

    /**
     * Sets state to FINISHED if only one character (or zero) has HP > 0.
     */
    public function checkIfFinished(): void
    {
        $aliveCount = count(array_filter(
            $this->participants,
            fn(Character $c) => $c->getCurrentHp() > 0
        ));

        if ($aliveCount <= 1) {
            $this->state = BattleState::FINISHED;
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

    public function getRoundDurationSeconds(): int
    {
        return $this->roundDurationSeconds;
    }

    public function isFinished(): bool
    {
        return $this->state === BattleState::FINISHED;
    }

    public function getState(): BattleState
    {
        return $this->state;
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

    public function getLocationId(): int
    {
        return $this->locationId;
    }

    public function getMap(): Map
    {
        return $this->map;
    }

    public function isCharacterCommitted(int $characterId): bool
    {
        return in_array($characterId, $this->committedCharacterIds, true);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'location_id' => $this->getLocationId(),
            'participants' => array_map(fn(Character $p) => $p->getName(), array_values($this->getParticipants())),
            'participant_ids' => array_keys($this->getParticipants()),
            'round_number' => $this->getRoundNumber(),
            'state' => $this->getState()->value,
            'committed_character_ids' => $this->getCommittedCharacterIds(),
        ];
    }
}
