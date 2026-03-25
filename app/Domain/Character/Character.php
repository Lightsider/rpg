<?php

declare(strict_types=1);

namespace App\Domain\Character;

use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;

use App\Domain\Battle\Rng\RandomGeneratorInterface;

/**
 * Pure PHP Domain Model for a Character.
 */
class Character implements \JsonSerializable
{
    public const int DEFAULT_MAX_AP = 3;

    public const int MAX_ATTACKS_PER_TURN = 2;

    private static ?Weapon $unarmedWeapon = null;

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
        private float $damageAccumulator = 0.0,
        private int $currencyCopper = 0,
        private readonly int $locationId = 1,
        private readonly int $maxActionPoints = self::DEFAULT_MAX_AP,
        private int $currentActionPoints = self::DEFAULT_MAX_AP,
        private int $attackPointsUsed = 0,
        private int $x = 0,
        private int $y = 0,
        private bool $isCommitted = false,
        private readonly int $blockResistRating = 0,
        private float $adArmorHead = 0.0,
        private float $adArmorChest = 0.0,
        private float $adArmorLegs = 0.0,
        private float $adArmorHands = 0.0,
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
        $baseDodge = CombatFormulas::dodgeChance($this->agility);
        return $baseDodge + $this->getArmorDodgeBonus();
    }

    public function getArmorDodgeBonus(): float
    {
        $bonus = 0.0;
        $armorSlots = [
            EquipmentSlot::HELMET,
            EquipmentSlot::CHEST,
            EquipmentSlot::LEGS,
            EquipmentSlot::GLOVES,
        ];

        foreach ($armorSlots as $slot) {
            $item = $this->equipment->getItem($slot);
            if ($item instanceof \App\Domain\Armor\Armor) {
                $bonus += $item->getDodgeBonus();
            }
        }

        return (float) round($bonus / 100, 4);
    }

    public function calculateCritChance(): float
    {
        return CombatFormulas::critChance($this->wit);
    }

    public function calculateCritMultiplier(): float
    {
        return CombatFormulas::critMultiplier($this->wit);
    }

    public function calculateStrengthBonus(): float
    {
        // 1 STR = 0.5 DMG
        return (float) round($this->getStrength() * 0.5, 4);
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
        $weapon = $this->getEquippedWeapon();
        if (!$weapon) {
            throw new \App\Domain\DomainException('Character has no valid weapon equipped in main hand.');
        }
        return $weapon;
    }

    public function getWeaponForCombat(): Weapon
    {
        return $this->getEquippedWeapon() ?? self::getUnarmedWeapon();
    }

    private function getEquippedWeapon(): ?Weapon
    {
        $weapon = $this->equipment->getItem(EquipmentSlot::MAIN_HAND);
        return $weapon instanceof Weapon ? $weapon : null;
    }

    private static function getUnarmedWeapon(): Weapon
    {
        if (self::$unarmedWeapon === null) {
            self::$unarmedWeapon = new Weapon(
                id: 0,
                name: 'Unarmed',
                minDamage: 0,
                maxDamage: 0,
                damageType: DamageType::BLUNT,
                accuracyBonus: 0.0,
                blockBreakRating: 0,
                pierceMultiplier: 0.0
            );
        }

        return self::$unarmedWeapon;
    }

    public function getDamageAccumulator(): float
    {
        return (float) $this->damageAccumulator;
    }

    public function setDamageAccumulator(float $value): void
    {
        $this->damageAccumulator = (float) round($value, 4);
    }

    public function getAdArmorForZone(string $zone): float
    {
        return match ($zone) {
            'head' => $this->adArmorHead,
            'torso', 'chest' => $this->adArmorChest,
            'legs' => $this->adArmorLegs,
            'hands', 'left_arm', 'right_arm' => $this->adArmorHands,
            default => 0.0,
        };
    }

    public function setAdArmorForZone(string $zone, float $value): void
    {
        $value = (float) round(max(0, $value), 4);
        switch ($zone) {
            case 'head':
                $this->adArmorHead = $value;
                break;
            case 'torso':
            case 'chest':
                $this->adArmorChest = $value;
                break;
            case 'legs':
                $this->adArmorLegs = $value;
                break;
            case 'hands':
            case 'left_arm':
            case 'right_arm':
                $this->adArmorHands = $value;
                break;
        }
    }

    public function initializeAdArmor(): void
    {
        $this->adArmorHead = $this->getMaxAdArmorForSlot(EquipmentSlot::HELMET);
        $this->adArmorChest = $this->getMaxAdArmorForSlot(EquipmentSlot::CHEST);
        $this->adArmorLegs = $this->getMaxAdArmorForSlot(EquipmentSlot::LEGS);
        $this->adArmorHands = $this->getMaxAdArmorForSlot(EquipmentSlot::GLOVES);
    }

    private function getMaxAdArmorForSlot(EquipmentSlot $slot): float
    {
        $item = $this->equipment->getItem($slot);
        if ($item instanceof \App\Domain\Armor\Armor) {
            return $item->getAdArmor();
        }
        return 0.0;
    }

    public function canEquip(Weapon $weapon): bool
    {
        return $this->strength >= $weapon->getRequiredStrength() &&
               $this->wit >= $weapon->getRequiredWit();
    }

    /**
     * @return array<Seal>
     */
    public function getEquippedSeals(): array
    {
        $seals = [];
        $slots = [
            EquipmentSlot::SEAL_1,
            EquipmentSlot::SEAL_2,
            EquipmentSlot::SEAL_3,
            EquipmentSlot::SEAL_4,
        ];

        foreach ($slots as $slot) {
            $item = $this->equipment->getItem($slot);
            if ($item instanceof Seal) {
                $seals[] = $item;
            }
        }

        return $seals;
    }

    public function getSealsBaseDamage(?RandomGeneratorInterface $rng = null): float
    {
        $total = 0.0;
        foreach ($this->getEquippedSeals() as $seal) {
            $total += $seal->rollBaseDamage($rng);
        }
        return (float) round($total, 4);
    }

    public function getSealsFlatCritBonus(): float
    {
        $total = 0.0;
        foreach ($this->getEquippedSeals() as $seal) {
            $total += $seal->getFlatCritBonus();
        }
        return (float) round($total, 4);
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

    public function getCurrencyCopper(): int
    {
        return $this->currencyCopper;
    }

    public function addCurrency(int $amount): void
    {
        if ($amount < 0) {
            throw new \App\Domain\DomainException('Cannot add negative currency.');
        }
        $this->currencyCopper += $amount;
    }

    public function spendCurrency(int $amount): bool
    {
        if ($amount < 0) {
            throw new \App\Domain\DomainException('Cannot spend negative currency.');
        }
        if ($this->currencyCopper < $amount) {
            return false;
        }
        $this->currencyCopper -= $amount;
        return true;
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
                'dexterity' => $this->getAgility(),
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
            'weapon' => ($this->getEquippedWeapon()?->getName()),
            'currency_copper' => $this->getCurrencyCopper(),
        ];
    }
}
