<?php

declare(strict_types=1);

namespace App\Domain\Character;

use App\Domain\Battle\Combatant;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;
use App\Domain\Battle\TargetZone;
use App\Domain\Armor\ArmorSubtype;
use App\Domain\Armor\Shield;
use App\Domain\Armor\Armor;
use App\Domain\Item\Item;
use App\Domain\Item\ItemType;

use App\Domain\Battle\Rng\RandomGeneratorInterface;

/**
 * Pure PHP Domain Model for a Character.
 */
class Character implements Combatant, \JsonSerializable
{
    public const int DEFAULT_MAX_AP = 3;

    public const int MAX_ATTACKS_PER_TURN = 2;

    private static ?Weapon $unarmedWeapon = null;
    private ?int $xpNextLevel = null;

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
        private float $adArmorLeftArm = 0.0,
        private float $adArmorRightArm = 0.0,
        // PRNG streaks (fail/success)
        private int $dodgeFailStreak = 0,
        private int $dodgeSuccessStreak = 0,
        private int $critFailStreak = 0,
        private int $critSuccessStreak = 0,
        private int $maxDamageFailStreak = 0,
        private int $maxDamageSuccessStreak = 0,
        private int $penetrationFailStreak = 0,
        private int $penetrationSuccessStreak = 0,
        private int $parryFailStreak = 0,
        private int $parrySuccessStreak = 0,
        private int $offhandAttackPointsUsed = 0,
        private int $level = 1,
        private int $experience = 0,
        private int $sublevelIndex = 0,
        private int $unallocatedStats = 0,
        private float $effectiveness = 0.0,
    ) {
        $this->adArmorHead = $this->normalizeAdArmorValue($this->adArmorHead);
        $this->adArmorChest = $this->normalizeAdArmorValue($this->adArmorChest);
        $this->adArmorLegs = $this->normalizeAdArmorValue($this->adArmorLegs);
        $this->adArmorLeftArm = $this->normalizeAdArmorValue($this->adArmorLeftArm);
        $this->adArmorRightArm = $this->normalizeAdArmorValue($this->adArmorRightArm);
    }

    public function isNpc(): bool
    {
        return false;
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
        return !$this->isCommitted && $this->currentActionPoints > 0 && $this->attackPointsUsed < $this->getMaxAttacks();
    }

    public function getMaxAttacks(): int
    {
        return self::MAX_ATTACKS_PER_TURN;
    }


    public function canQueueDefense(): bool
    {
        return !$this->isCommitted && $this->currentActionPoints > 0;
    }

    public function canQueueOffhandAttack(): bool
    {
        if ($this->isCommitted || $this->currentActionPoints <= 0) {
            return false;
        }

        if (!$this->hasDagger()) {
            return false;
        }

        return $this->offhandAttackPointsUsed < 1;
    }

    public function hasShield(): bool
    {
        $offhand = $this->equipment->getItem(\App\Domain\Equipment\EquipmentSlot::OFF_HAND);
        return $offhand instanceof \App\Domain\Armor\Shield;
    }

    public function hasDagger(): bool
    {
        $offhand = $this->equipment->getItem(\App\Domain\Equipment\EquipmentSlot::OFF_HAND);
        return $offhand instanceof \App\Domain\Weapon\Dagger;
    }

    public function getBonusDefensiveAP(): int
    {
        $item = $this->equipment->getItem(\App\Domain\Equipment\EquipmentSlot::OFF_HAND);
        if ($item instanceof \App\Domain\Armor\Shield) {
            return $item->getDefensiveAPBonus();
        }
        return 0;
    }

    public function getBonusOffhandAP(): int
    {
        $item = $this->equipment->getItem(\App\Domain\Equipment\EquipmentSlot::OFF_HAND);
        if ($item instanceof \App\Domain\Weapon\Dagger) {
            return $item->getOffHandAPBonus();
        }
        return 0;
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

    public function registerOffhandAttackUsage(): void
    {
        if ($this->isCommitted) {
            throw new \App\Domain\DomainException('Cannot register offhand attack after commitment.');
        }

        if ($this->offhandAttackPointsUsed >= 1) {
            throw new \App\Domain\DomainException('Maximum offhand attacks per round reached.');
        }

        $this->offhandAttackPointsUsed++;
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
        $this->isCommitted = false;
        $this->attackPointsUsed = 0;
        $this->offhandAttackPointsUsed = 0;

        $totalBonusAP = 0;
        foreach ($this->equipment->getAllEquipped() as $item) {
            if (method_exists($item, 'getDefensiveAPBonus')) {
                $totalBonusAP += $item->getDefensiveAPBonus();
            }
            if (method_exists($item, 'getOffHandAPBonus')) {
                $totalBonusAP += $item->getOffHandAPBonus();
            }
        }

        $this->currentActionPoints = self::DEFAULT_MAX_AP + $totalBonusAP;
    }

    public function getDodgeFailStreak(): int
    {
        return $this->dodgeFailStreak;
    }

    public function addEffectiveness(float $val): void
    {
        $this->effectiveness += $val;
    }

    public function calculateCoinContribution(int $base, array $multipliers): int
    {
        $total = 0;
        $items = $this->equipment->getAllEquipped();

        foreach ($items as $slot => $item) {
            $itemMultiplier = 1;

            if ($item instanceof Armor && $item->getSubtype() === ArmorSubtype::BODY) {
                $itemMultiplier = $multipliers['body'] ?? 1;
            } elseif ($item instanceof Weapon && $item->getItemType() === ItemType::WEAPON) {
                if ($item->isTwoHanded()) {
                    $itemMultiplier = $multipliers['weapon_2h'] ?? 1;
                } else {
                    $itemMultiplier = $multipliers['weapon_1h'] ?? 1;
                }
            }

            $total += $base * $itemMultiplier;
        }

        return $total;
    }

    public function resetEffectiveness(): void
    {
        $this->effectiveness = 0.0;
    }

    public function getEffectiveness(): float
    {
        return $this->effectiveness;
    }

    public function getArmorArchetype(TargetZone $zone): string
    {
        $slot = match ($zone) {
            TargetZone::HEAD => EquipmentSlot::HELMET,
            TargetZone::TORSO => EquipmentSlot::CHEST,
            TargetZone::LEGS => EquipmentSlot::LEGS,
            TargetZone::LEFT_ARM, TargetZone::RIGHT_ARM => EquipmentSlot::GLOVES,
        };

        $item = $this->equipment->getItem($slot);
        if ($item instanceof Armor) {
            return $item->getArchetype();
        }

        return 'non_armor';
    }

    public function getDodgeSuccessStreak(): int
    {
        return $this->dodgeSuccessStreak;
    }

    public function recordDodgeSuccess(): void
    {
        $this->dodgeSuccessStreak++;
        $this->dodgeFailStreak = 0;
    }

    public function recordDodgeFailure(): void
    {
        $this->dodgeFailStreak++;
        $this->dodgeSuccessStreak = 0;
    }

    public function getCritFailStreak(): int
    {
        return $this->critFailStreak;
    }

    public function getCritSuccessStreak(): int
    {
        return $this->critSuccessStreak;
    }

    public function recordCritSuccess(): void
    {
        $this->critSuccessStreak++;
        $this->critFailStreak = 0;
    }

    public function recordCritFailure(): void
    {
        $this->critFailStreak++;
        $this->critSuccessStreak = 0;
    }

    public function getMaxDamageFailStreak(): int
    {
        return $this->maxDamageFailStreak;
    }

    public function getMaxDamageSuccessStreak(): int
    {
        return $this->maxDamageSuccessStreak;
    }

    public function recordMaxDamageSuccess(): void
    {
        $this->maxDamageSuccessStreak++;
        $this->maxDamageFailStreak = 0;
    }

    public function recordMaxDamageFailure(): void
    {
        $this->maxDamageFailStreak++;
        $this->maxDamageSuccessStreak = 0;
    }

    public function getPenetrationFailStreak(): int
    {
        return $this->penetrationFailStreak;
    }

    public function getPenetrationSuccessStreak(): int
    {
        return $this->penetrationSuccessStreak;
    }

    public function getParryRating(): int
    {
        $rating = 0;
        foreach ($this->equipment->getAllEquipped() as $item) {
            if (method_exists($item, 'getParryRating')) {
                $rating += $item->getParryRating();
            }
        }
        return $rating;
    }

    public function getParryFailStreak(): int
    {
        return $this->parryFailStreak;
    }

    public function getParrySuccessStreak(): int
    {
        return $this->parrySuccessStreak;
    }

    public function incrementParryFailStreak(): void
    {
        $this->parryFailStreak++;
        $this->parrySuccessStreak = 0;
    }

    public function incrementParrySuccessStreak(): void
    {
        $this->parrySuccessStreak++;
        $this->parryFailStreak = 0;
    }

    public function resetParryStreaks(): void
    {
        $this->parryFailStreak = 0;
        $this->parrySuccessStreak = 0;
    }

    public function recordPenetrationSuccess(): void
    {
        $this->penetrationSuccessStreak++;
        $this->penetrationFailStreak = 0;
    }

    public function recordPenetrationFailure(): void
    {
        $this->penetrationFailStreak++;
        $this->penetrationSuccessStreak = 0;
    }

    /**
     * Reset all PRNG streaks. Call this when combat ends.
     */
    public function resetAllStreaks(): void
    {
        $this->dodgeFailStreak = 0;
        $this->dodgeSuccessStreak = 0;
        $this->critFailStreak = 0;
        $this->critSuccessStreak = 0;
        $this->maxDamageFailStreak = 0;
        $this->maxDamageSuccessStreak = 0;
        $this->penetrationFailStreak = 0;
        $this->penetrationSuccessStreak = 0;
    }

    public function calculateDodgeChance(?TargetZone $zone = null): float
    {
        $baseDodge = CombatFormulas::dodgeChance($this->agility);
        return $baseDodge + $this->getArmorDodgeBonus($zone);
    }

    public function calculateParryChance(): float
    {
        $rating = $this->getParryRating();
        if ($rating <= 0) {
            return 0.0;
        }

        // Standard rating-to-chance formula (rating / (rating + K))
        // 22 rating with K=120 gives ~15.4%
        return (float) round($rating / ($rating + 120), 4);
    }

    public function getArmorDodgeBonus(?TargetZone $zone = null): float
    {
        $bonus = 0.0;
        $armorSlots = [
            EquipmentSlot::HELMET,
            EquipmentSlot::CHEST,
            EquipmentSlot::LEGS,
            EquipmentSlot::GLOVES,
            EquipmentSlot::OFF_HAND,
        ];

        foreach ($armorSlots as $slot) {
            $item = $this->equipment->getItem($slot);
            if (!($item instanceof Armor)) {
                continue;
            }

            $itemDodge = $item->getDodgeBonus();
            if ($itemDodge <= 0) continue;

            // Shields are global
            if ($item instanceof Shield) {
                $bonus += $itemDodge;
                continue;
            }

            // Other armor is zone-specific
            if ($zone !== null) {
                $multiplier = $this->getDodgeMultiplierForArmor($item, $zone);
                $bonus += ($itemDodge * $multiplier);
            } else {
                // Return total potential dodge if no zone specified (for UI/base calcs)
                $bonus += $itemDodge;
            }
        }

        return (float) round($bonus / 100, 4);
    }

    private function getDodgeMultiplierForArmor(Armor $armor, TargetZone $zone): float
    {
        $subtype = $armor->getSubtype();
        
        return match ($zone) {
            TargetZone::HEAD => ($subtype === ArmorSubtype::HELMET) ? 1.0 : 0.0,
            
            TargetZone::TORSO => ($subtype === ArmorSubtype::BODY) ? 1.0 : 0.0,
            
            TargetZone::LEFT_ARM, TargetZone::RIGHT_ARM => match ($subtype) {
                ArmorSubtype::BODY => 0.5,
                ArmorSubtype::GLOVES => 0.5,
                default => 0.0
            },
            
            TargetZone::LEGS => ($subtype === ArmorSubtype::BOOTS) ? 1.0 : 0.0,
            
            default => 0.0
        };
    }

    public function calculateCritChance(): float
    {
        $baseCritChance = CombatFormulas::critChance($this->wit);
        $baseCritChance += $this->getWeaponCritChanceBonus();
        $baseCritChance += $this->getSealsCritChanceBonus();

        return $baseCritChance;
    }

    public function getWeaponCritChanceBonus(): float
    {
        $weapon = $this->getEquippedWeapon();
        if ($weapon) {
            return (float) round($weapon->getCritChanceBonus() / 100, 4);
        }
        return 0.0;
    }

    public function getSealsCritChanceBonus(): float
    {
        $bonus = 0.0;
        $sealSlots = [
            EquipmentSlot::SEAL_1,
            EquipmentSlot::SEAL_2,
            EquipmentSlot::SEAL_3,
            EquipmentSlot::SEAL_4,
        ];

        foreach ($sealSlots as $slot) {
            $item = $this->equipment->getItem($slot);
            if ($item instanceof \App\Domain\Seal\Seal) {
                $bonus += $item->getCritChanceBonus();
            }
        }

        return (float) round($bonus / 100, 4);
    }

    public function getSealsCritBonus(): float
    {
        $bonus = 0.0;
        $sealSlots = [
            EquipmentSlot::SEAL_1,
            EquipmentSlot::SEAL_2,
            EquipmentSlot::SEAL_3,
            EquipmentSlot::SEAL_4,
        ];

        foreach ($sealSlots as $slot) {
            $item = $this->equipment->getItem($slot);
            if ($item instanceof \App\Domain\Seals\Seal) {
                $bonus += $item->getFlatCritBonus();
            }
        }

        return (float) round($bonus / 100, 4);
    }

    /**
     * Unused while using flat bonus
     */
    public function calculateCritMultiplier(): float
    {
        return CombatFormulas::critMultiplier($this->wit);
    }

    public function calculateCritFlatBonus(): float
    {
        return CombatFormulas::critFlatBonus($this->wit);
    }

    public function calculateStrengthBonus(): float
    {
        return (float) round(CombatFormulas::strengthBonus($this->strength), 4);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        // Player names are immutable in battle
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
        $multiplier = 0.0;
        foreach ($this->equipment->getAllEquipped() as $item) {
            if (method_exists($item, 'getMaxHpMultiplier')) {
                $multiplier += $item->getMaxHpMultiplier();
            }
        }
        return (int) ceil($this->maxHp * (1.0 + $multiplier));
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
            'left_arm' => $this->adArmorLeftArm,
            'right_arm' => $this->adArmorRightArm,
            'hands' => max($this->adArmorLeftArm, $this->adArmorRightArm),
            default => 0.0,
        };
    }

    public function setAdArmorForZone(string $zone, float $value): void
    {
        $value = $this->normalizeAdArmorValue($value);
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
                $this->adArmorLeftArm = $value;
                $this->adArmorRightArm = $value;
                break;
            case 'left_arm':
                $this->adArmorLeftArm = $value;
                break;
            case 'right_arm':
                $this->adArmorRightArm = $value;
                break;
        }
    }

    public function initializeAdArmor(): void
    {
        $shieldArmor = $this->getMaxAdArmorForSlot(EquipmentSlot::OFF_HAND);

        $this->adArmorHead = $this->normalizeAdArmorValue($this->getMaxAdArmorForSlot(EquipmentSlot::HELMET) + $shieldArmor);
        $this->adArmorChest = $this->normalizeAdArmorValue($this->getMaxAdArmorForSlot(EquipmentSlot::CHEST) + $shieldArmor);
        $this->adArmorLegs = $this->normalizeAdArmorValue($this->getMaxAdArmorForSlot(EquipmentSlot::LEGS) + $shieldArmor);
        
        $chestArmorBase = $this->getMaxAdArmorForSlot(EquipmentSlot::CHEST);
        $glovesArmor = $this->getMaxAdArmorForSlot(EquipmentSlot::GLOVES);
        
        $armArmor = ($chestArmorBase * 0.5) + ($glovesArmor * 0.5) + $shieldArmor;
        $armArmor = $this->normalizeAdArmorValue($armArmor);
        
        $this->adArmorLeftArm = $armArmor;
        $this->adArmorRightArm = $armArmor;
    }

    private function normalizeAdArmorValue(float $value): float
    {
        return (float) max(0, (int) round($value));
    }

    private function getMaxAdArmorForSlot(EquipmentSlot $slot): float
    {
        $item = $this->equipment->getItem($slot);
        if ($item instanceof \App\Domain\Armor\Armor) {
            return $item->getAdArmor();
        }
        return 0.0;
    }

    public function canEquip(Item $item): bool
    {
        if ($this->level < $item->getRequiredLevel()) {
            return false;
        }

        if ($item instanceof Weapon) {
            return $this->strength >= $item->getRequiredStrength() &&
                   $this->wit >= $item->getRequiredWit();
        }

        if ($item instanceof Armor) {
            return $this->strength >= $item->getRequiredStrength() &&
                   $this->wit >= $item->getRequiredWit() &&
                   $this->agility >= $item->getRequiredDexterity() &&
                   $this->constitution >= $item->getRequiredConstitution();
        }

        if ($item instanceof Seal) {
            return $this->strength >= $item->getRequiredStrength() &&
                   $this->wit >= $item->getRequiredWit();
        }

        return true;
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
        $total = $this->maxActionPoints;
        foreach ($this->equipment->getAllEquipped() as $item) {
            if (method_exists($item, 'getDefensiveAPBonus')) {
                $total += $item->getDefensiveAPBonus();
            }
        }
        return $total;
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

    public function addCurrencyCopper(int $amount): void
    {
        if ($amount < 0) {
            throw new \App\Domain\DomainException('Cannot add negative copper.');
        }
        $this->currencyCopper += $amount;
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
        $this->currentHp = $this->getMaxHp();
    }

    /**
     * Aggregated block resistance rating from all equipped items.
     */
    public function getBlockResistRating(): int
    {
        $total = $this->blockResistRating;
        foreach ($this->equipment->getAllEquipped() as $item) {
            $total += $item->getBlockResistRating();
        }
        return $total;
    }

    /**
     * Aggregated fractional pierce damage reduction from all equipped items.
     * Uses multiplicative scaling to ensure the total reduction never reaches 100%.
     */
    public function getPierceDamageReduction(): float
    {
        $multiplier = 1.0;
        foreach ($this->equipment->getAllEquipped() as $item) {
            $reduction = $item->getPierceDamageReduction();
            if ($reduction > 0) {
                $multiplier *= (1.0 - $reduction);
            }
        }
        return (float) round(1.0 - $multiplier, 4);
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getExperience(): int
    {
        return $this->experience;
    }

    public function getUnallocatedStats(): int
    {
        return $this->unallocatedStats;
    }

    public function addUnallocatedStats(int $points): void
    {
        $this->unallocatedStats += $points;
    }

    public function allocateStat(string $statName): void
    {
        if ($this->unallocatedStats <= 0) {
            throw new \DomainException("No unallocated stats available.");
        }

        switch ($statName) {
            case 'strength':
                $this->strength++;
                break;
            case 'dexterity':
                $this->agility++; // stored as agility in domain
                break;
            case 'constitution':
                $this->constitution++;
                break;
            case 'wit':
                $this->wit++;
                break;
            default:
                throw new \DomainException("Invalid stat: $statName");
        }

        $this->unallocatedStats--;
    }

    public function getSublevelIndex(): int
    {
        return $this->sublevelIndex;
    }

    /**
     * @param array<int, array{xp_threshold: int, reward_copper: int}>|array<int, int> $thresholds
     */
    public function addExperience(int $amount, array $thresholds): bool
    {
        if ($amount < 0) {
            throw new \App\Domain\DomainException('Cannot add negative experience.');
        }

        $this->experience += $amount;
        if ($this->isLegacyLevelThresholds($thresholds)) {
            return $this->applyLegacyLevelThresholds($thresholds);
        }

        return $this->applySublevelThresholds($thresholds);
    }

    /**
     * @param array<int, array{xp_threshold: int, reward_copper: int}> $sublevelThresholds
     */
    private function applySublevelThresholds(array $sublevelThresholds): bool
    {
        $leveledUp = false;
        $s = count($sublevelThresholds);
        $statsPerSublevel = $s > 0 ? (int) floor(4 / $s) : 0;

        while (true) {
            $nextSublevel = $this->sublevelIndex + 1;
            
            if (isset($sublevelThresholds[$nextSublevel]) && $this->experience >= $sublevelThresholds[$nextSublevel]['xp_threshold']) {
                $this->sublevelIndex++;
                $this->addCurrencyCopper($sublevelThresholds[$this->sublevelIndex]['reward_copper']);
                $this->addUnallocatedStats($statsPerSublevel);
                
                // Note: Level up happens when the LAST sublevel of the level is reached.
                if (!isset($sublevelThresholds[$this->sublevelIndex + 1])) {
                    $this->level++;
                    $this->sublevelIndex = 0;
                    $leveledUp = true;
                    
                    // Grant remaining stats for the level up
                    $remainingStats = 8 - ($statsPerSublevel * $s);
                    if ($remainingStats > 0) {
                        $this->addUnallocatedStats($remainingStats);
                    }
                    break;
                }
            } else {
                break;
            }
        }

        return $leveledUp;
    }

    /**
     * @param array<int, int> $xpRequirements
     */
    private function applyLegacyLevelThresholds(array $xpRequirements): bool
    {
        $leveledUp = false;

        while (true) {
            $nextLevel = $this->level + 1;
            $requiredXp = $xpRequirements[$nextLevel] ?? null;
            if ($requiredXp === null || $this->experience < $requiredXp) {
                break;
            }

            $this->level++;
            $this->sublevelIndex = 0;
            $leveledUp = true;
        }

        return $leveledUp;
    }

    /**
     * @param array<int, mixed> $thresholds
     */
    private function isLegacyLevelThresholds(array $thresholds): bool
    {
        if ($thresholds === []) {
            return false;
        }

        $first = reset($thresholds);
        return is_int($first);
    }

    public function getXpForNextLevel(array $xpRequirements): ?int
    {
        return $xpRequirements[$this->level + 1] ?? null;
    }

    public function setXpNextLevel(?int $xpNextLevel): void
    {
        $this->xpNextLevel = $xpNextLevel;
    }

    public function getXpNextLevel(): ?int
    {
        return $this->xpNextLevel;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'name' => $this->getName(),
            'level' => $this->getLevel(),
            'experience' => $this->getExperience(),
            'xp_next_level' => $this->xpNextLevel,
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
            'sublevel_index' => $this->getSublevelIndex(),
            'unallocated_stats' => $this->getUnallocatedStats(),
            'sublevels_count' => $this->level + 2, // Default fallback
            'additional_armor' => [
                'head' => $this->getAdArmorForZone('head'),
                'chest' => $this->getAdArmorForZone('chest'),
                'legs' => $this->getAdArmorForZone('legs'),
                'left_arm' => $this->getAdArmorForZone('left_arm'),
                'right_arm' => $this->getAdArmorForZone('right_arm'),
            ],
        ];
    }
}
