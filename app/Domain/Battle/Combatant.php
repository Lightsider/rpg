<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Equipment\Equipment;
use App\Domain\Weapon\Weapon;

/**
 * Interface for any entity that can participate in battle combat.
 * Both player Characters and NPC combatants implement this.
 */
interface Combatant
{
    // --- Identity ---
    public function getId(): int;
    public function getName(): string;
    public function isNpc(): bool;

    // --- Position ---
    public function getX(): int;
    public function getY(): int;
    public function setPosition(int $x, int $y): void;

    // --- HP ---
    public function getCurrentHp(): int;
    public function getMaxHp(): int;
    public function setCurrentHp(int $hp): void;
    public function restoreHp(): void;

    // --- Action Points ---
    public function getCurrentActionPoints(): int;
    public function getMaxActionPoints(): int;
    public function getMaxAttacks(): int;
    public function canQueueAttack(): bool;
    public function canQueueDefense(): bool;
    public function canQueueOffhandAttack(): bool;
    public function canSpendAP(int $cost): bool;
    public function spendAP(int $cost): void;
    public function registerAttackUsage(): void;
    public function registerOffhandAttackUsage(): void;
    public function commit(): void;
    public function isCommitted(): bool;
    public function resetRoundState(): void;

    // --- Combat Stats ---
    public function getStrength(): int;
    public function getAgility(): int;
    public function getConstitution(): int;
    public function getWit(): int;
    public function getLevel(): int;
    public function calculateDodgeChance(?TargetZone $zone = null): float;
    public function calculateParryChance(): float;
    public function calculateCritChance(): float;
    public function calculateCritFlatBonus(): float;
    public function calculateStrengthBonus(): float;

    // --- Equipment / Weapon surface ---
    public function getWeaponForCombat(): Weapon;
    public function getEquipment(): Equipment;
    public function hasShield(): bool;
    public function hasDagger(): bool;
    public function getBonusDefensiveAP(): int;
    public function getBonusOffhandAP(): int;
    public function getSealsBaseDamage(?RandomGeneratorInterface $rng = null): float;
    public function getSealsFlatCritBonus(): float;
    public function getSealsCritChanceBonus(): float;
    public function getWeaponCritChanceBonus(): float;

    // --- Armor ---
    public function getAdArmorForZone(string $zone): float;
    public function setAdArmorForZone(string $zone, float $value): void;
    public function initializeAdArmor(): void;
    public function getArmorArchetype(TargetZone $zone): string;
    public function getBlockResistRating(): int;
    public function getPierceDamageReduction(): float;

    // --- Damage Accumulator ---
    public function getDamageAccumulator(): float;
    public function setDamageAccumulator(float $value): void;

    // --- PRNG Streaks ---
    public function getDodgeFailStreak(): int;
    public function getDodgeSuccessStreak(): int;
    public function recordDodgeSuccess(): void;
    public function recordDodgeFailure(): void;
    public function getCritFailStreak(): int;
    public function getCritSuccessStreak(): int;
    public function recordCritSuccess(): void;
    public function recordCritFailure(): void;
    public function getMaxDamageFailStreak(): int;
    public function getMaxDamageSuccessStreak(): int;
    public function recordMaxDamageSuccess(): void;
    public function recordMaxDamageFailure(): void;
    public function getPenetrationFailStreak(): int;
    public function getPenetrationSuccessStreak(): int;
    public function recordPenetrationSuccess(): void;
    public function recordPenetrationFailure(): void;
    public function getParryRating(): int;
    public function getParryFailStreak(): int;
    public function getParrySuccessStreak(): int;
    public function incrementParryFailStreak(): void;
    public function incrementParrySuccessStreak(): void;
    public function resetParryStreaks(): void;
    public function resetAllStreaks(): void;

    // --- Effectiveness ---
    public function addEffectiveness(float $val): void;
    public function getEffectiveness(): float;
    public function resetEffectiveness(): void;
}
