<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Character\Character;
use Exception;

/**
 * Domain service to resolve a single round of battle.
 */
class RoundResolver implements RoundResolverInterface
{
    public function __construct(
        private readonly CombatResolver $combatResolver
    ) {
    }

    public function resolve(Battle $battle): void
    {
        $battle->startResolving();

        $queuedActions = $battle->getQueuedActions();
        $participants = $battle->getParticipants();

        // 1. Map ID to Character for quick access
        $characterMap = [];
        foreach ($participants as $participant) {
            $characterMap[$participant->getId()] = $participant;
        }

        // 2. Resolve ALL MOVE actions first
        foreach ($queuedActions as $action) {
            if ($action->getType() === ActionType::MOVE) {
                $character = $characterMap[$action->getCharacterId()] ?? null;
                if ($character) {
                    $character->setPosition($action->getToX(), $action->getToY());
                }
            }
        }

        // 3. Collect defense zones for each character
        $defenses = [];
        foreach ($queuedActions as $action) {
            if ($action->getType() === ActionType::DEFEND) {
                $characterId = $action->getCharacterId();
                if (!isset($defenses[$characterId])) {
                    $defenses[$characterId] = [];
                }
                $defenses[$characterId][] = $action->getTargetZone()->value;
            }
        }

        // 4. Resolve Attacks
        foreach ($queuedActions as $action) {
            if ($action->getType() === ActionType::ATTACK) {
                $attackerId = $action->getCharacterId();
                $attacker = $characterMap[$attackerId] ?? null;
                if (!$attacker || $attacker->getCurrentHp() <= 0) {
                    continue;
                }

                // In 1v1, find the other participant as the defender
                $defender = $this->findOpponent($attackerId, $participants);
                if (!$defender || $defender->getCurrentHp() <= 0) {
                    continue;
                }

                // Check attacker and target adjacency (post-movement)
                if (!$this->isAdjacent($attacker->getX(), $attacker->getY(), $defender->getX(), $defender->getY())) {
                    continue; // Out of range attack fails
                }

                // Check if target has DEFEND on that zone
                $isBlocked = isset($defenses[$defender->getId()])
                    && in_array($action->getTargetZone()->value, $defenses[$defender->getId()], true);

                // Resolve attack using CombatResolver
                $result = $this->combatResolver->resolveAttack($attacker, $defender, $isBlocked);

                // Apply damage
                if ($result->damage > 0) {
                    $newHp = max(0, $defender->getCurrentHp() - $result->damage);
                    $defender->setCurrentHp($newHp);
                }
            }
        }

        // 5. Finalize round state
        $battle->clearQueuedActions();
        $battle->finishResolving();
    }

    /**
     * Finds the first participant that is not the given character ID.
     */
    private function findOpponent(int $characterId, array $participants): ?Character
    {
        foreach ($participants as $participant) {
            if ($participant->getId() !== $characterId) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * Adjacency check for attack range (distance <= 1).
     */
    private function isAdjacent(int $x1, int $y1, int $x2, int $y2): bool
    {
        $dx = abs($x1 - $x2);
        $dy = abs($y1 - $y2);

        return ($dx <= 1 && $dy <= 1) && !($dx === 0 && $dy === 0);
    }
}
