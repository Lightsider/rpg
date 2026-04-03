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

    /**
     * @param array<int, Character> $participants
     * @param array<int, string> $participantTeams
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
        private array $committedCharacterIds = [],
        private ?int $maxParticipants = null,
        private ?int $startTimeoutSeconds = null,
        private array $participantTeams = []
    ) {
    }

    public function addParticipant(Character $character): void
    {
        if ($this->state !== BattleState::WAITING) {
            throw new Exception('Can only join a battle in WAITING state.');
        }

        if (array_key_exists($character->getId(), $this->participants)) {
            throw new Exception('Character already in battle.');
        }

        $this->participants[$character->getId()] = $character;
    }

    public function assignTeam(int $characterId, string $team): void
    {
        if (!array_key_exists($characterId, $this->participants)) {
            throw new Exception('Character not in battle.');
        }

        $this->participantTeams[$characterId] = $team;
    }

    /**
     * @return array<int, string>
     */
    public function getParticipantTeams(): array
    {
        return $this->participantTeams;
    }

    public function getParticipantTeam(int $characterId): ?string
    {
        return $this->participantTeams[$characterId] ?? null;
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
     * Starts the battle from WAITING state without incrementing the round number.
     */
    public function startFromLobby(): void
    {
        if ($this->state === BattleState::FINISHED) {
            throw new Exception('Cannot start a finished battle.');
        }

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
            $targetId = $action->getTargetId();
            $opponent = $targetId !== null ? $this->getParticipantById($targetId) : null;
            if ($targetId !== null && !$opponent) {
                throw new \App\Domain\DomainException('Target not found in this battle.');
            }
            if (!$opponent) {
                $opponent = $this->getOpponent($action->getCharacterId());
            }
            if (!$opponent) {
                if ($this->hasTeammateOnly($action->getCharacterId())) {
                    throw new \App\Domain\DomainException('Cannot attack a teammate.');
                }
                throw new \App\Domain\DomainException('No opponent found to attack.');
            }

            if ($opponent->getCurrentHp() <= 0) {
                throw new \App\Domain\DomainException('Target is already dead.');
            }

            $attackerTeam = $this->participantTeams[$action->getCharacterId()] ?? null;
            $defenderTeam = $this->participantTeams[$opponent->getId()] ?? null;
            if ($attackerTeam !== null && $defenderTeam !== null && $attackerTeam === $defenderTeam) {
                throw new \App\Domain\DomainException('Cannot attack a teammate.');
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
        $attackerTeam = $this->participantTeams[$characterId] ?? null;
        foreach ($this->participants as $participant) {
            if ($participant->getId() === $characterId) {
                continue;
            }
            if ($participant->getCurrentHp() <= 0) {
                continue;
            }
            $defenderTeam = $this->participantTeams[$participant->getId()] ?? null;
            if ($attackerTeam !== null && $defenderTeam !== null && $attackerTeam === $defenderTeam) {
                continue;
            }
            return $participant;
        }
        return null;
    }

    private function hasTeammateOnly(int $characterId): bool
    {
        $attackerTeam = $this->participantTeams[$characterId] ?? null;
        if ($attackerTeam === null) {
            return false;
        }

        $hasOther = false;
        foreach ($this->participants as $participant) {
            if ($participant->getId() === $characterId) {
                continue;
            }
            if ($participant->getCurrentHp() <= 0) {
                continue;
            }
            $hasOther = true;
            $defenderTeam = $this->participantTeams[$participant->getId()] ?? null;
            if ($defenderTeam !== null && $defenderTeam !== $attackerTeam) {
                return false;
            }
        }

        return $hasOther;
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
        $aliveParticipants = $this->getAliveParticipants();
        $finished = $this->hasSingleSurvivor($aliveParticipants)
            || $this->hasSingleTeamStanding($aliveParticipants);

        if ($finished) {
            $this->state = BattleState::FINISHED;
        }
    }

    /**
     * @return array<int, Character>
     */
    public function getVictoryWinners(): array
    {
        $aliveParticipants = $this->getAliveParticipants();
        if ($aliveParticipants === []) {
            return [];
        }

        $winnerTeam = $this->getWinningTeam($aliveParticipants);
        if ($winnerTeam !== null) {
            return array_values(array_filter(
                $aliveParticipants,
                fn(Character $c) => ($this->participantTeams[$c->getId()] ?? null) === $winnerTeam
            ));
        }

        if ($this->hasSingleSurvivor($aliveParticipants)) {
            return array_values($aliveParticipants);
        }

        return [];
    }

    public function getWinningTeamName(): ?string
    {
        return $this->getWinningTeam($this->getAliveParticipants());
    }

    /**
     * @return array<int, Character>
     */
    private function getAliveParticipants(): array
    {
        return array_filter(
            $this->participants,
            fn(Character $c) => $c->getCurrentHp() > 0
        );
    }

    /**
     * @param array<int, Character> $aliveParticipants
     */
    private function hasSingleSurvivor(array $aliveParticipants): bool
    {
        return count($aliveParticipants) <= 1;
    }

    /**
     * @param array<int, Character> $aliveParticipants
     */
    private function hasSingleTeamStanding(array $aliveParticipants): bool
    {
        return $this->getWinningTeam($aliveParticipants) !== null;
    }

    /**
     * @param array<int, Character> $aliveParticipants
     */
    private function getWinningTeam(array $aliveParticipants): ?string
    {
        if ($aliveParticipants === []) {
            return null;
        }

        $teams = [];
        $hasUnassigned = false;
        foreach ($aliveParticipants as $participant) {
            $team = $this->participantTeams[$participant->getId()] ?? null;
            if ($team === null) {
                $hasUnassigned = true;
                continue;
            }
            $teams[$team] = true;
        }

        // If anyone is unassigned, fall back to single-survivor victory.
        if ($hasUnassigned) {
            return null;
        }

        if (count($teams) <= 1) {
            return array_key_first($teams);
        }

        return null;
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

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function getStartTimeoutSeconds(): ?int
    {
        return $this->startTimeoutSeconds;
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
            'max_participants' => $this->getMaxParticipants(),
            'start_timeout_seconds' => $this->getStartTimeoutSeconds(),
            'participant_teams' => $this->getParticipantTeams(),
        ];
    }
}


