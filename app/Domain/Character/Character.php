<?php

declare(strict_types=1);

namespace App\Domain\Character;

use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\Weapon;

/**
 * Pure PHP Domain Model for a Character.
 */
class Character implements \JsonSerializable
{
    public const int DEFAULT_MAX_AP = 3;

    public const int MAX_ATTACKS_PER_TURN = 2;

    private const float DODGE_CHANCE_PER_AGILITY = 0.045;
    private const float CRIT_CHANCE_PER_WIT = 0.05;
    private const float BASE_CRIT_MULTIPLIER = 1.5;
    private const float CRIT_MULTIPLIER_PER_WIT = 0.05;
    private const float STRENGTH_BONUS_MULTIPLIER = 0.5;

    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly string $name,
        private readonly int $strength,
        private readonly int $agility,
        private readonly int $constitution,
        private readonly int $wit,
        private readonly int $maxHp,
        private int $currentHp,
        public readonly \App\Domain\Equipment\Equipment $equipment,
        private readonly int $locationId = 1,
        private readonly int $maxActionPoints = self::DEFAULT_MAX_AP,
        private int $currentActionPoints = self::DEFAULT_MAX_AP,
        private int $attackPointsUsed = 0,
        private int $x = 0,
        private int $y = 0,
        private bool $isCommitted = false,
        private readonly int $blockResistRating = 0,
        // PRNG failure streaks
        private int $dodgeFailStreak = 0,
        private int $critFailStreak = 0,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getLocationId(): int
    {
        return $this->locationId;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function setPosition(int $x, int $y): void
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function canQueueAttack(): bool
    {
        return !$this->isCommitted && $this->currentActionPoints > 0 && $this->attackPointsUsed < self::MAX_ATTACKS_PER_TURN;
    }

    public function canQueueDefense(): bool
    {
        return !$this->isCommitted && $this->currentActionPoints > 0;
    }

    public function canSpendAP(int $cost): bool
    {
        return !$this->isCommitted && $this->currentActionPoints >= $cost;
    }

    public function spendAP(int $cost): void
    {
        if ($this->isCommitted) {
            throw new \App\Domain\DomainException('Cannot spend AP after commitment.');
        }

        if ($cost < 0) {
            throw new \App\Domain\DomainException('Cannot spend negative AP.');
        }

        if ($this->currentActionPoints < $cost) {
            throw new \App\Domain\DomainException('Not enough Action Points.');
        }

        $this->currentActionPoints -= $cost;
    }

    public function registerAttackUsage(): void
    {
        if ($this->isCommitted) {
            throw new \App\Domain\DomainException('Cannot register attack after commitment.');
        }

        if ($this->attackPointsUsed >= self::MAX_ATTACKS_PER_TURN) {
            throw new \App\Domain\DomainException('Maximum attacks per round reached.');
        }

        $this->attackPointsUsed++;
    }

    public function commit(): void
    {
        $this->isCommitted = true;
    }

    public function isCommitted(): bool
    {
        return $this->isCommitted;
    }

    public function resetRoundState(): void
    {
        $this->currentActionPoints = $this->maxActionPoints;
        $this->attackPointsUsed = 0;
        $this->isCommitted = false;
    }

    // -------------------------------------------------------------------------
    // PRNG Failure Streak Management
    // -------------------------------------------------------------------------

    public function getDodgeFailStreak(): int
    {
        return $this->dodgeFailStreak;
    }

    public function resetDodgeFailStreak(): void
    {
        $this->dodgeFailStreak = 0;
    }

    public function incrementDodgeFailStreak(): void
    {
        $this->dodgeFailStreak++;
    }

    public function getCritFailStreak(): int
    {
        return $this->critFailStreak;
    }

    public function resetCritFailStreak(): void
    {
        $this->critFailStreak = 0;
    }

    public function incrementCritFailStreak(): void
    {
        $this->critFailStreak++;
    }

    /**
     * Reset all PRNG failure streaks. Call this when combat ends.
     */
    public function resetAllFailStreaks(): void
    {
        $this->dodgeFailStreak = 0;
        $this->critFailStreak = 0;
    }

    public function calculateDodgeChance(): float
    {
        return $this->agility * self::DODGE_CHANCE_PER_AGILITY;
    }

    public function calculateCritChance(): float
    {
        return $this->wit * self::CRIT_CHANCE_PER_WIT;
    }

    public function calculateCritMultiplier(): float
    {
        return self::BASE_CRIT_MULTIPLIER + ($this->wit * self::CRIT_MULTIPLIER_PER_WIT);
    }

    public function calculateStrengthBonus(): float
    {
        return $this->strength * self::STRENGTH_BONUS_MULTIPLIER;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStrength(): int
    {
        return $this->strength;
    }

    public function getAgility(): int
    {
        return $this->agility;
    }

    public function getConstitution(): int
    {
        return $this->constitution;
    }

    public function getWit(): int
    {
        return $this->wit;
    }

    public function getMaxHp(): int
    {
        return $this->maxHp;
    }

    public function getCurrentHp(): int
    {
        return $this->currentHp;
    }

    public function getWeapon(): Weapon
    {
        $weapon = $this->equipment->getItem(EquipmentSlot::MAIN_HAND);
        if (!$weapon instanceof Weapon) {
            throw new \App\Domain\DomainException('Character has no valid weapon equipped in main hand.');
        }
        return $weapon;
    }

    public function getEquipment(): Equipment
    {
        return $this->equipment;
    }

    public function getMaxActionPoints(): int
    {
        return $this->maxActionPoints;
    }

    public function getCurrentActionPoints(): int
    {
        return $this->currentActionPoints;
    }

    public function setCurrentHp(int $hp): void
    {
        $this->currentHp = $hp;
    }

    /**
     * Restore HP to maximum value. Called after battle ends.
     */
    public function restoreHp(): void
    {
        $this->currentHp = $this->maxHp;
    }

    /**
     * Block resistance rating from equipped shield (0 until shields are implemented).
     * Armor deliberately does NOT contribute to this value.
     */
    public function getBlockResistRating(): int
    {
        return $this->blockResistRating;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'name' => $this->getName(),
            'stats' => [
                'strength' => $this->getStrength(),
                'agility' => $this->getAgility(),
                'constitution' => $this->getConstitution(),
                'wit' => $this->getWit(),
            ],
            'hp' => $this->getCurrentHp(),
            'max_hp' => $this->getMaxHp(),
            'location_id' => $this->getLocationId(),
            'position' => [
                'x' => $this->getX(),
                'y' => $this->getY(),
            ],
            'weapon' => $this->getWeapon()->getName(),
        ];
    }
}
