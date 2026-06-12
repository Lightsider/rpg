<?php

declare(strict_types=1);

namespace Tests\Balance;

use App\Domain\Battle\BlockPenetration\BlockPenetrationConfig;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
use App\Domain\Shield\Shield;
use App\Domain\Armor\Armor;
use App\Domain\Armor\ArmorSubtype;
use Tests\TestCase;

class BlockPenetrationComparisonTest extends TestCase
{
    private const int HITS_TO_SIMULATE = 1000;
    private const int BASE_DAMAGE = 100;

    public function test_compare_penetration_with_defensive_foundation(): void
    {
        // Standard Balance Config
        $config = new BlockPenetrationConfig(
            k: 120,
            maxFinalChance: 0.95,
        );
        $service = new BlockPenetrationService($config);

        $sword = new Weapon(1, 'Sword', 9.0, 11.0, DamageType::SLASHING, 0.0, 20, 0.50, 75, WeaponArchetype::STABLE);
        $axe = new Weapon(2, 'Axe', 9.0, 11.0, DamageType::CHOPPING, 0.0, 70, 0.65, 0, WeaponArchetype::STABLE);
        
        $twoHandedAxe = new Weapon(
            id: 5,
            name: 'Great Axe',
            minDamage: 11.7,
            maxDamage: 14.3,
            damageType: DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: 120,
            pierceMultiplier: 0.75,
            maxDamageRating: 75,
            archetype: WeaponArchetype::STABLE,
            isTwoHanded: true
        );

        $shield = new Shield(3, 'Buckler', blockResistRating: 40, pierceDamageReduction: 0.10);

        // --- SCENARIOS ---
        
        $results = [];

        // 1. Standard weapons
        $results[] = $this->simulateScenario($service, $sword, [], 'Sword vs None', 100);
        $results[] = $this->simulateScenario($service, $sword, [$shield], 'Sword vs Shield', 100);
        $results[] = $this->simulateScenario($service, $axe, [], 'Axe vs None', 100);
        $results[] = $this->simulateScenario($service, $axe, [$shield], 'Axe vs Shield', 100);

        // 2. Two-Handed Axe
        $results[] = $this->simulateScenario($service, $twoHandedAxe, [], '2H Axe vs None', 150);
        $results[] = $this->simulateScenario($service, $twoHandedAxe, [$shield], '2H Axe vs Shield', 150);

        $this->printFullComparison($results);
        
        $this->assertTrue(true);
    }

    private function simulateScenario(BlockPenetrationService $service, Weapon $weapon, array $defensiveItems, string $label, int $baseDamage): array
    {
        $attacker = $this->createDummyCharacter('Attacker', $weapon);
        $defender = $this->createDummyCharacter('Defender', null, $defensiveItems);

        $breaks = 0;
        $totalDamage = 0;

        for ($i = 0; $i < self::HITS_TO_SIMULATE; $i++) {
            $result = $service->checkBlockBreak($attacker, $defender, $baseDamage);
            if ($result->penetrated) {
                $breaks++;
                $totalDamage += $result->damage;
            }
        }

        return [
            'label' => $label,
            'attacker_rating' => $weapon->getBlockBreakRating(),
            'defender_rating' => $defender->getBlockResistRating(),
            'multiplier' => $weapon->getPierceMultiplier(),
            'reduction' => $defender->getPierceDamageReduction(),
            'breaks' => $breaks,
            'totalDamage' => $totalDamage,
            'avgDamage' => $totalDamage / self::HITS_TO_SIMULATE,
            'rate' => ($breaks / self::HITS_TO_SIMULATE) * 100,
        ];
    }

    private function createDummyCharacter(string $name, ?Weapon $weapon, array $items = []): Character
    {
        $equipment = new Equipment();
        if ($weapon) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);
        }
        
        $slots = [EquipmentSlot::OFF_HAND, EquipmentSlot::CHEST, EquipmentSlot::HELMET, EquipmentSlot::GLOVES];
        foreach ($items as $index => $item) {
            if (isset($slots[$index])) {
                $equipment->setItem($slots[$index], $item);
            }
        }

        return new Character(
            id: mt_rand(1, 1000),
            userId: mt_rand(1, 1000),
            name: $name,
            strength: 10,
            agility: 0,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            blockResistRating: 0
        );
    }

    private function printFullComparison(array $results): void
    {
        $output = "\n================================================================================\n";
        $output .= "  BLOCK PENETRATION PERFORMANCE COMPARISON (1000 HITS)\n";
        $output .= "================================================================================\n";
        $output .= sprintf("%-20s | %-6s | %-6s | %-8s | %-8s | %-8s\n", "Scenario", "Rate", "Breaks", "DmgAvg", "A.Rating", "D.Rating");
        $output .= "--------------------------------------------------------------------------------\n";

        foreach ($results as $r) {
            $output .= sprintf(
                "%-20s | %d%%   | %-6d | %-8.2f | %-8d | %-8d\n",
                $r['label'],
                (int)$r['rate'],
                $r['breaks'],
                $r['avgDamage'],
                $r['attacker_rating'],
                $r['defender_rating']
            );
        }
        $output .= "================================================================================\n\n";

        fwrite(STDOUT, $output);
    }
}
