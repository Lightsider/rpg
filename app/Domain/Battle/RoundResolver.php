<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Character\Character;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
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

        // Map ID to Character for quick access
        $characterMap = [];
        foreach ($participants as $participant) {
            $characterMap[$participant->getId()] = $participant;
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

        // 2. Register DEFENSE zones (including blocks attached to move actions)
        $defenses = [];
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
        foreach ($queuedActions as $action) {
            if ($action->getType() === ActionType::ATTACK) {
                $attackerId = $action->getCharacterId();
                $attacker = $characterMap[$attackerId] ?? null;
                if (!$attacker || $attacker->getCurrentHp() <= 0) {
                    continue;
                }

                $defender = $this->findOpponent($attackerId, $participants);
                if (!$defender || $defender->getCurrentHp() <= 0) {
                    continue;
                }

                // POST-MOVEMENT adjacency check
                $dx = abs($attacker->getX() - $defender->getX());
                $dy = abs($attacker->getY() - $defender->getY());
                if ($dx > 1 || $dy > 1) {
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: BattleLogType::DODGE,
                        actorId: $defender->getId(),
                        targetId: $attackerId
                    );
                    continue;
                }

                $isBlocked = isset($defenses[$defender->getId()])
                    && in_array($action->getTargetZone()->value, $defenses[$defender->getId()], true);

                $result = $this->combatResolver->resolveAttack($attacker, $defender, $isBlocked);
                $attackResults[] = ['defender' => $defender, 'result' => $result, 'attacker' => $attacker, 'zone' => $action->getTargetZone()->value];

                if ($result->isDodged || $result->isMiss) {
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: BattleLogType::DODGE,
                        actorId: $defender->getId(),
                        targetId: $attackerId
                    );
                } elseif ($result->damage === 0 && $isBlocked) {
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: BattleLogType::BLOCK,
                        actorId: $defender->getId(),
                        targetId: $attackerId,
                        zone: $action->getTargetZone()
                    );
                } elseif ($result->isPierced) {
                    // Block was broken (penetrated)
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: BattleLogType::BLOCK_BREAK,
                        actorId: $attackerId,
                        targetId: $defender->getId(),
                        zone: $action->getTargetZone(),
                        damage: $result->damage
                    );
                    // Also log max_damage if it triggered
                    if ($result->isMaxDamage) {
                        $logs[] = new BattleLogEntry(
                            roundNumber: $battle->getRoundNumber(),
                            type: BattleLogType::MAX_DAMAGE,
                            actorId: $attackerId,
                            targetId: $defender->getId(),
                            zone: $action->getTargetZone(),
                            damage: $result->damage
                        );
                    }
                } else {
                    $logType = $result->isMaxDamage ? BattleLogType::MAX_DAMAGE : BattleLogType::HIT;
                    $logs[] = new BattleLogEntry(
                        roundNumber: $battle->getRoundNumber(),
                        type: $logType,
                        actorId: $attackerId,
                        targetId: $defender->getId(),
                        zone: $action->getTargetZone(),
                        damage: $result->damage
                    );
                }
            }
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
            if ($participant->getCurrentHp() <= 0) {
                $logs[] = new BattleLogEntry(
                    roundNumber: $battle->getRoundNumber(),
                    type: BattleLogType::DEATH,
                    actorId: $participant->getId()
                );
            }
        }

        $battle->clearQueuedActions();
        $battle->finishResolving();

        // Reset block-penetration and max-damage counters when combat ends
        if ($battle->isFinished()) {
            $this->blockPenetrationService->resetAllCounters();
            $this->maxDamageService->resetAllCounters();

            // Restore HP to max for all participants after battle ends
            foreach ($participants as $participant) {
                $participant->restoreHp();
            }

            // Save the battle with restored HP to database
            $this->battleRepository->save($battle);
        }

        return new RoundResolutionResult($logs);
    }

    private function findOpponent(int $characterId, array $participants): ?Character
    {
        foreach ($participants as $participant) {
            if ($participant->getId() !== $characterId) {
                return $participant;
            }
        }
        return null;
    }
}
