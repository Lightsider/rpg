<?php

declare(strict_types=1);

namespace App\Domain\Npc;

/**
 * Value object defining an NPC blueprint (template) from which
 * NpcCombatant instances are created when they join a battle.
 */
class NpcTemplate
{
    /**
     * @param array<string, int>|null $equipmentItemIds Item IDs for humanoid NPCs keyed by slot name
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly NpcType $type,
        public readonly int $strength,
        public readonly int $agility,
        public readonly int $constitution,
        public readonly int $wit,
        public readonly int $level,
        public readonly string $behaviorModel,
        public readonly ?array $equipmentItemIds = null,
    ) {
    }
}
