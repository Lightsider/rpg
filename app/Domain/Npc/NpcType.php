<?php

declare(strict_types=1);

namespace App\Domain\Npc;

/**
 * Defines the category of NPC combatant.
 */
enum NpcType: string
{
    case HUMANOID = 'humanoid'; // Uses archetypes, equipment, identical combat to player
    case BEAST = 'beast';       // Custom stats, no equipment slots
}
