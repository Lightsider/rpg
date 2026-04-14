<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use DateTimeImmutable;

/**
 * Immutable Value Object representing a single entry in the battle log.
 */
class BattleLogEntry
{
    public function __construct(
        public readonly int $roundNumber,
        public readonly BattleLogType $type,
        public readonly int $actorId,
        public readonly ?int $targetId = null,
        public readonly ?TargetZone $zone = null,
        public readonly ?int $damage = null,
        public readonly ?string $outcome = null,
        public readonly ?bool $isCrit = null,
        public readonly ?bool $isMax = null,
        public readonly ?string $weaponName = null,
        public readonly ?string $damageType = null,
        public readonly DateTimeImmutable $timestamp = new DateTimeImmutable()
    ) {
    }
}
