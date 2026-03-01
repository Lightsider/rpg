<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Weapon\DamageType;

/**
 * Immutable DTO representing the result of an attack.
 */
class AttackResult
{
    public function __construct(
        public readonly int $damage,
        public readonly bool $isCritical,
        public readonly bool $isDodged,
        public readonly bool $isMiss,
        public readonly DamageType $damageType
    ) {
    }
}
