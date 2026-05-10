<?php

declare(strict_types=1);

namespace App\Domain\Npc;

use App\Domain\Battle\Combatant;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Battle\TargetZone;
use App\Domain\Character\CombatFormulas;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;
use App\Domain\Armor\Armor;
use App\Domain\Armor\ArmorSubtype;
use App\Domain\Armor\Shield;
use App\Domain\Seal\Seal;

/**
 * NPC combatant — a non-player entity that participates in battles.
 * Implements the same Combatant interface as Character but without
 * player-specific concerns (userId, currency, experience, backpack).
 */
class NpcCombatant implements Combatant, \JsonSerializable
{
    public const int DEFAULT_MAX_AP = 3;
    public const int MAX_ATTACKS_PER_TURN = 2;

    private static ?Weapon $unarmedWeapon = null;

    public function __construct(
        private readonly int $id,
        private string $name,
        private readonly NpcType $type,
        private int $strength,
        private int $agility,
        private int $constitution,
        private int $wit,
        private readonly int $maxHp,
        private int $currentHp,
        public readonly Equipment $equipment,
        private readonly int $level = 1,
        private string $behaviorModelKey = 'reach_and_hit',
        private readonly int $npcTemplateId = 0,
        private float $damageAccumulator = 0.0,
        private readonly int $maxActionPoints = self::DEFAULT_MAX_AP,
        private int $currentActionPoints = self::DEFAULT_MAX_AP,
        private int $attackPointsUsed = 0,
        private int $offhandAttackPointsUsed = 0,
        private int $x = 0,
        private int $y = 0,
        private bool $isCommitted = false,
        private readonly int $blockResistRating = 0,
        private float $adArmorHead = 0.0,
        private float $adArmorChest = 0.0,
        private float $adArmorLegs = 0.0,
        private float $adArmorLeftArm = 0.0,
        private float $adArmorRightArm = 0.0,
        // PRNG streaks
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
        private float $effectiveness = 0.0,
    ) {
        $this->adArmorHead = $this->normalizeAdArmorValue($this->adArmorHead);
        $this->adArmorChest = $this->normalizeAdArmorValue($this->adArmorChest);
        $this->adArmorLegs = $this->normalizeAdArmorValue($this->adArmorLegs);
        $this->adArmorLeftArm = $this->normalizeAdArmorValue($this->adArmorLeftArm);
        $this->adArmorRightArm = $this->normalizeAdArmorValue($this->adArmorRightArm);
    }

    // --- Identity ---

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
        $this->name = $name;
    }

    public function isNpc(): bool
    {
        return true;
    }

    public function getNpcType(): NpcType
    {
        return $this->type;
    }

    public function getBehaviorModelKey(): string
    {
        return $this->behaviorModelKey;
    }

    public function setBehaviorModelKey(string $key): void
    {
        $this->behaviorModelKey = $key;
    }

    public function getNpcTemplateId(): int
    {
        return $this->npcTemplateId;
    }

    // --- Position ---

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

    // --- HP ---

    public function getCurrentHp(): int
    {
        return $this->currentHp;
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

    public function setCurrentHp(int $hp): void
    {
        $this->currentHp = $hp;
    }

    public function restoreHp(): void
    {
        $this->currentHp = $this->getMaxHp();
    }

    // --- Action Points ---

    public function getCurrentActionPoints(): int
    {
        return $this->currentActionPoints;
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

    public function getMaxAttacks(): int
    {
        return self::MAX_ATTACKS_PER_TURN;
    }

    public function canQueueAttack(): bool
    {
        return !$this->isCommitted && $this->currentActionPoints > 0 && $this->attackPointsUsed < $this->getMaxAttacks();
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

    // --- Combat Stats ---

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

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setStats(int $strength, int $dexterity, int $constitution, int $wit): void
    {
        $this->strength = $strength;
        $this->agility = $dexterity;
        $this->constitution = $constitution;
        $this->wit = $wit;
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
        return (float) round($rating / ($rating + 120), 4);
    }

    public function calculateCritChance(): float
    {
        $baseCritChance = CombatFormulas::critChance($this->wit);
        $baseCritChance += $this->getWeaponCritChanceBonus();
        $baseCritChance += $this->getSealsCritChanceBonus();
        return $baseCritChance;
    }

    public function calculateCritFlatBonus(): float
    {
        return CombatFormulas::critFlatBonus($this->wit);
    }

    public function calculateStrengthBonus(): float
    {
        return (float) round(CombatFormulas::strengthBonus($this->strength), 4);
    }

    // --- Equipment / Weapon ---

    public function getWeaponForCombat(): Weapon
    {
        $weapon = $this->equipment->getItem(EquipmentSlot::MAIN_HAND);
        if ($weapon instanceof Weapon) {
            return $weapon;
        }
        return self::getUnarmedWeapon();
    }

    public function getEquipment(): Equipment
    {
        return $this->equipment;
    }

    public function hasShield(): bool
    {
        $offhand = $this->equipment->getItem(EquipmentSlot::OFF_HAND);
        return $offhand instanceof Shield;
    }

    public function hasDagger(): bool
    {
        $offhand = $this->equipment->getItem(EquipmentSlot::OFF_HAND);
        return $offhand instanceof \App\Domain\Weapon\Dagger;
    }

    public function getBonusDefensiveAP(): int
    {
        $item = $this->equipment->getItem(EquipmentSlot::OFF_HAND);
        if ($item instanceof Shield) {
            return $item->getDefensiveAPBonus();
        }
        return 0;
    }

    public function getBonusOffhandAP(): int
    {
        $item = $this->equipment->getItem(EquipmentSlot::OFF_HAND);
        if ($item instanceof \App\Domain\Weapon\Dagger) {
            return $item->getOffHandAPBonus();
        }
        return 0;
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
            if ($item instanceof Seal) {
                $bonus += $item->getCritChanceBonus();
            }
        }
        return (float) round($bonus / 100, 4);
    }

    public function getWeaponCritChanceBonus(): float
    {
        $weapon = $this->equipment->getItem(EquipmentSlot::MAIN_HAND);
        if ($weapon instanceof Weapon) {
            return (float) round($weapon->getCritChanceBonus() / 100, 4);
        }
        return 0.0;
    }

    // --- Armor ---

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

    public function getBlockResistRating(): int
    {
        $total = $this->blockResistRating;
        foreach ($this->equipment->getAllEquipped() as $item) {
            $total += $item->getBlockResistRating();
        }
        return $total;
    }

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

    // --- Damage Accumulator ---

    public function getDamageAccumulator(): float
    {
        return (float) $this->damageAccumulator;
    }

    public function setDamageAccumulator(float $value): void
    {
        $this->damageAccumulator = (float) round($value, 4);
    }

    // --- PRNG Streaks ---

    public function getDodgeFailStreak(): int
    {
        return $this->dodgeFailStreak;
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

    // --- Effectiveness ---

    public function addEffectiveness(float $val): void
    {
        $this->effectiveness += $val;
    }

    public function getEffectiveness(): float
    {
        return $this->effectiveness;
    }

    public function resetEffectiveness(): void
    {
        $this->effectiveness = 0.0;
    }

    // --- Private helpers ---

    /**
     * @return Seal[]
     */
    private function getEquippedSeals(): array
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

    private function getArmorDodgeBonus(?TargetZone $zone = null): float
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
            if ($itemDodge <= 0) {
                continue;
            }

            if ($item instanceof Shield) {
                $bonus += $itemDodge;
                continue;
            }

            if ($zone !== null) {
                $multiplier = $this->getDodgeMultiplierForArmor($item, $zone);
                $bonus += ($itemDodge * $multiplier);
            } else {
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

    private function normalizeAdArmorValue(float $value): float
    {
        return (float) max(0, (int) round($value));
    }

    private function getMaxAdArmorForSlot(EquipmentSlot $slot): float
    {
        $item = $this->equipment->getItem($slot);
        if ($item instanceof Armor) {
            return $item->getAdArmor();
        }
        return 0.0;
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

    public function jsonSerialize(): array
    {
        $weapon = $this->equipment->getItem(EquipmentSlot::MAIN_HAND);

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'is_npc' => true,
            'npc_type' => $this->type->value,
            'level' => $this->getLevel(),
            'stats' => [
                'strength' => $this->getStrength(),
                'dexterity' => $this->getAgility(),
                'constitution' => $this->getConstitution(),
                'wit' => $this->getWit(),
            ],
            'hp' => $this->getCurrentHp(),
            'max_hp' => $this->getMaxHp(),
            'position' => [
                'x' => $this->getX(),
                'y' => $this->getY(),
            ],
            'weapon' => $weapon instanceof Weapon ? $weapon->getName() : null,
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
