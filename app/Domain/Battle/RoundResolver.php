<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Character\Character;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\Rng\DeterministicRandomGenerator;
use App\Domain\DomainException;
use App\Services\MovementResolver;
use Exception;

/**
 * Domain service to resolve a single round of battle.
 */
class RoundResolver implements RoundResolverInterface
{
    public function __construct(
        private readonly CombatResolver $combatResolver,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BlockPenetrationService $blockPenetrationService,
        private readonly MaxDamageService $maxDamageService,
        private readonly MovementResolver $movementResolver,
    ) {
    }

    public function resolve(Battle $battle): RoundResolutionResult
    {
        // Seeded RNG per battle/round for deterministic resolution.
        $seed = (int) sprintf('%u', crc32($battle->getId() . ':' . $battle->getRoundNumber()));
        $rng = new DeterministicRandomGenerator($seed);
        $this->combatResolver->setRandomGenerator($rng);
        $this->blockPenetrationService->setRandomGenerator($rng);
        $this->maxDamageService->setRandomGenerator($rng);

        // 1. Guard against double resolution
        if ($battle->getState() === BattleState::RESOLVING) {
            // Abort (or throw depending on preference, but 'abort' usually means exit early)
            // Here we throw to be explicit that something is wrong.
            throw new DomainException('Battle is already resolving.');
        }

        // 2. Set state and persist BEFORE heavy logic
        $battle->startResolving();
        $this->battleRepository->save($battle);

        $logs = [];

        $queuedActions = $battle->getQueuedActions();
        $participants = $battle->getParticipants();
        $preHp = [];

        // Map ID to Character for quick access
        $characterMap = [];
        foreach ($participants as $participant) {
            $characterMap[$participant->getId()] = $participant;
            $preHp[$participant->getId()] = $participant->getCurrentHp();
        }

        $actionsByCharacter = [];
        foreach ($queuedActions as $action) {
            $actionsByCharacter[$action->getCharacterId()][] = $action;
        }

        // 1. Resolve ALL MOVE actions first
        $this->movementResolver->resolveMovement($battle);

        foreach ($queuedActions as $action) {
            if ($action->getType() === ActionType::MOVE) {
                $character = $characterMap[$action->getCharacterId()] ?? null;
                if ($character) {
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: BattleLogType::MOVE,
                        actorId: $character->getId()
                    );
                }
            }
        }

        $skipBlocks = [];
        foreach ($participants as $participant) {
            if ($participant->getCurrentHp() <= 0) {
                continue;
            }

            $characterId = $participant->getId();
            if (!isset($actionsByCharacter[$characterId]) || count($actionsByCharacter[$characterId]) === 0) {
                $blockCount = min(
                    $participant->getCurrentActionPoints(),
                    count(TargetZone::cases())
                );
                $skipBlocks[$characterId] = $this->pickRandomSkipBlocks($battle, $characterId, $blockCount);

                $logs[] = new BattleLogEntry(
                    roundNumber: $battle->getRoundNumber(),
                    type: BattleLogType::SKIP,
                    actorId: $characterId
                );
            }
        }

        // 2. Register DEFENSE zones (including blocks attached to move actions)
        $defenses = [];
        foreach ($skipBlocks as $charId => $zones) {
            if (!isset($defenses[$charId])) {
                $defenses[$charId] = [];
            }
            foreach ($zones as $zone) {
                $defenses[$charId][] = $zone;
            }
        }
        foreach ($queuedActions as $action) {
            if ($action->getType() === ActionType::DEFEND) {
                $characterId = $action->getCharacterId();
                if (!isset($defenses[$characterId])) {
                    $defenses[$characterId] = [];
                }
                $defenses[$characterId][] = $action->getTargetZone()->value;
            }

            if ($action->getType() === ActionType::MOVE) {
                $characterId = $action->getCharacterId();
                if (!isset($defenses[$characterId])) {
                    $defenses[$characterId] = [];
                }
                foreach ($action->getBlocks() as $blockZone) {
                    $defenses[$characterId][] = $blockZone;
                }
            }
        }

        foreach ($defenses as $charId => $zones) {
            $char = $characterMap[$charId] ?? null;
            if ($char) {
                // Defensive registration log removed as it doesn't fit the required BattleLogType enum exactly,
                // and the user specified logs should follow the provided VO structure. 
                // Alternatively, we could add it if desired, but for now we follow the HIT/BLOCK/MISS logic.
            }
        }

        // 3. Resolve ATTACK actions (calculate results, don't apply yet)
        $attackResults = [];
        $attackContexts = [];
        foreach ($queuedActions as $action) {
            if ($action->getType() !== ActionType::ATTACK && $action->getType() !== ActionType::ATTACK_OFFHAND) {
                continue;
            }

            $attackerId = $action->getCharacterId();
            $attacker = $characterMap[$attackerId] ?? null;
            if (!$attacker || $attacker->getCurrentHp() <= 0) {
                continue;
            }

            $targetId = $action->getTargetId();
            $defender = $targetId !== null ? $battle->getParticipantById($targetId) : null;
            if ($defender && $defender->getCurrentHp() <= 0) {
                $defender = null;
            }

            if ($targetId === null && !$defender) {
                $defender = $this->findOpponentInRange($battle, $attacker, $participants);
            }

            $inRange = false;
            if ($defender) {
                $dx = abs($attacker->getX() - $defender->getX());
                $dy = abs($attacker->getY() - $defender->getY());
                $inRange = $dx <= 1 && $dy <= 1;
            }

            if ($targetId === null && !$defender) {
                $defender = $this->findOpponent($battle, $attackerId, $participants);
            }
            if (!$defender || $defender->getCurrentHp() <= 0) {
                continue;
            }

            $attackContexts[] = [
                'action' => $action,
                'attacker' => $attacker,
                'defender' => $defender,
                'inRange' => $inRange,
            ];
        }

        foreach ($attackContexts as $context) {
            if ($context['inRange']) {
                continue;
            }

            /** @var Character $attacker */
            $attacker = $context['attacker'];
            $attackerId = $attacker->getId();
            $defenses = $this->addAutoBlocks(
                $battle,
                $attacker,
                $defenses,
                'out_of_range'
            );
        }

        foreach ($attackContexts as $context) {
            /** @var Character $attacker */
            $attacker = $context['attacker'];
            /** @var Character $defender */
            $defender = $context['defender'];
            $action = $context['action'];
            $attackerId = $attacker->getId();

            if (!$context['inRange']) {
                $logs[] = new BattleLogEntry(
                    roundNumber: $battle->getRoundNumber(),
                    type: BattleLogType::ATTACK,
                    actorId: $attackerId,
                    targetId: $defender->getId(),
                    zone: $action->getTargetZone(),
                    damage: 0,
                    outcome: 'dodge'
                );
                continue;
            }

            $isBlocked = isset($defenses[$defender->getId()])
                && in_array($action->getTargetZone()->value, $defenses[$defender->getId()], true);

            $forcedWeapon = null;
            if ($action->getType() === ActionType::ATTACK_OFFHAND) {
                $offhand = $attacker->getEquipment()->getItem(\App\Domain\Equipment\EquipmentSlot::OFF_HAND);
                if (!$offhand instanceof \App\Domain\Weapon\Weapon) {
                    // This attack shouldn't have been queued if no dagger is equipped, 
                    // but we verify here for safety.
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: BattleLogType::ATTACK,
                        actorId: $attackerId,
                        targetId: $defender->getId(),
                        zone: $action->getTargetZone(),
                        damage: 0,
                        outcome: 'dodge' // Use dodge/miss to represent failure to land
                    );
                    continue;
                }
                $forcedWeapon = $offhand;
            }

            $result = $this->combatResolver->resolveAttack($attacker, $defender, $isBlocked, $action->getTargetZone(), $forcedWeapon);
            $attackResults[] = ['defender' => $defender, 'result' => $result, 'attacker' => $attacker, 'zone' => $action->getTargetZone()->value];

            $outcome = 'hit';
            $damage = $result->damage;
            if ($result->isDodged || $result->isMiss) {
                $outcome = 'dodge';
                $damage = 0;
            } elseif ($result->isParried) {
                $outcome = 'parry';
                $damage = 0;
            } elseif ($result->damage === 0 && $isBlocked) {
                $outcome = 'block';
            } elseif ($result->isPierced) {
                $outcome = 'block_break';
            }

            $logs[] = new BattleLogEntry(
                roundNumber: $battle->getRoundNumber(),
                type: BattleLogType::ATTACK,
                actorId: $attackerId,
                targetId: $defender->getId(),
                zone: $action->getTargetZone(),
                damage: $damage,
                outcome: $outcome,
                isCrit: $result->isCritical,
                isMax: $result->isMaxDamage
            );
        }

        // 4. Apply damage
        foreach ($attackResults as $attack) {
            /** @var Character $defender */
            $defender = $attack['defender'];
            /** @var AttackResult $result */
            $result = $attack['result'];
            if ($result->damage > 0) {
                $newHp = max(0, $defender->getCurrentHp() - $result->damage);
                $defender->setCurrentHp($newHp);
            }
        }

        // 5. Mark dead characters
        foreach ($participants as $participant) {
            $before = $preHp[$participant->getId()] ?? $participant->getCurrentHp();
            if ($before > 0 && $participant->getCurrentHp() <= 0) {
                $logs[] = new BattleLogEntry(
                    roundNumber: $battle->getRoundNumber(),
                    type: BattleLogType::DEATH,
                    actorId: $participant->getId()
                );
            }
        }

        $battle->clearQueuedActions();
        $battle->finishResolving();

        if ($battle->isFinished()) {
            foreach ($battle->getVictoryWinners() as $winner) {
                $logs[] = new BattleLogEntry(
                    roundNumber: $battle->getRoundNumber(),
                    type: BattleLogType::VICTORY,
                    actorId: $winner->getId()
                );
            }
        }

        // Reset block-penetration and max-damage counters when combat ends
        if ($battle->isFinished()) {

            // Restore HP to max and reset streaks after battle ends
            foreach ($participants as $participant) {
                $participant->restoreHp();
                $participant->initializeAdArmor();
                $participant->resetAllStreaks();
            }

            // Save the battle with restored HP to database
            $this->battleRepository->save($battle);
        }

        return new RoundResolutionResult($logs);
    }

    private function findOpponent(Battle $battle, int $characterId, array $participants): ?Character
    {
        $attackerTeam = $battle->getParticipantTeam($characterId);
        foreach ($participants as $participant) {
            if ($participant->getId() === $characterId) {
                continue;
            }
            $defenderTeam = $battle->getParticipantTeam($participant->getId());
            if ($attackerTeam !== null && $defenderTeam !== null && $attackerTeam === $defenderTeam) {
                continue;
            }
            return $participant;
        }
        return null;
    }

    private function findOpponentInRange(Battle $battle, Character $attacker, array $participants): ?Character
    {
        $attackerTeam = $battle->getParticipantTeam($attacker->getId());
        foreach ($participants as $participant) {
            if ($participant->getId() === $attacker->getId()) {
                continue;
            }
            if ($participant->getCurrentHp() <= 0) {
                continue;
            }
            $defenderTeam = $battle->getParticipantTeam($participant->getId());
            if ($attackerTeam !== null && $defenderTeam !== null && $attackerTeam === $defenderTeam) {
                continue;
            }
            $dx = abs($attacker->getX() - $participant->getX());
            $dy = abs($attacker->getY() - $participant->getY());
            if ($dx <= 1 && $dy <= 1) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pickRandomSkipBlocks(Battle $battle, int $characterId, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $zones = array_map(
            fn(TargetZone $zone) => $zone->value,
            TargetZone::cases()
        );

        $seed = (int) sprintf(
            '%u',
            crc32($battle->getId() . ':' . $battle->getRoundNumber() . ':skip:' . $characterId)
        );
        $rng = new DeterministicRandomGenerator($seed);
        $zones = $this->shuffleZones($zones, $rng);

        return array_slice($zones, 0, min($count, count($zones)));
    }

    /**
     * @param array<int, array<int, string>> $defenses
     * @return array<int, array<int, string>>
     */
    private function addAutoBlocks(
        Battle $battle,
        Character $character,
        array $defenses,
        string $reason
    ): array {
        $characterId = $character->getId();
        $existing = $defenses[$characterId] ?? [];
        $remainingAp = $character->getCurrentActionPoints();
        if ($remainingAp <= 0) {
            return $defenses;
        }

        $availableZones = array_filter(
            array_map(fn(TargetZone $zone) => $zone->value, TargetZone::cases()),
            fn(string $zone) => !in_array($zone, $existing, true)
        );

        if (count($availableZones) === 0) {
            return $defenses;
        }

        $blockCount = min($remainingAp, count($availableZones));
        if ($blockCount <= 0) {
            return $defenses;
        }

        $seed = (int) sprintf(
            '%u',
            crc32($battle->getId() . ':' . $battle->getRoundNumber() . ':' . $reason . ':' . $characterId)
        );
        $rng = new DeterministicRandomGenerator($seed);
        $availableZones = $this->shuffleZones(array_values($availableZones), $rng);
        $selected = array_slice($availableZones, 0, $blockCount);

        if (!isset($defenses[$characterId])) {
            $defenses[$characterId] = [];
        }
        foreach ($selected as $zone) {
            $defenses[$characterId][] = $zone;
        }

        return $defenses;
    }

    /**
     * @param array<int, string> $zones
     * @return array<int, string>
     */
    private function shuffleZones(array $zones, DeterministicRandomGenerator $rng): array
    {
        $count = count($zones);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = (int) floor($rng->nextFloat() * ($i + 1));
            $temp = $zones[$i];
            $zones[$i] = $zones[$j];
            $zones[$j] = $temp;
        }

        return $zones;
    }
}
