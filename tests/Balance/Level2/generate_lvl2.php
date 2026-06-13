<?php

@mkdir(__DIR__, 0777, true);

function processWeaponTest() {
    $content = file_get_contents(dirname(__DIR__) . '/CombatBalanceStatsWithWeaponTest.php');
    
    // Namespace & Class name
    $content = str_replace('namespace Tests\Balance;', 'namespace Tests\Balance\Level2;', $content);
    $content = str_replace('class CombatBalanceStatsWithWeaponTest', 'class CombatBalanceStatsWithWeaponLvl2Test', $content);
    
    // Config K values
    $content = str_replace('new BlockPenetrationConfig(120, 0.95, 0.20);', 'new BlockPenetrationConfig(180, 0.95, 0.20);', $content);
    $content = str_replace('new MaxDamageConfig(300, 0.80, 0.20);', 'new MaxDamageConfig(450, 0.80, 0.20);', $content);
    
    // Stats (x1.5)
    $content = str_replace("['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0]", "['str' => 12, 'con' => 12, 'dex' => 0, 'wit' => 0]", $content); // Tank/Stable
    $content = str_replace("['str' => 4, 'con' => 8, 'dex' => 0, 'wit' => 4]", "['str' => 6, 'con' => 12, 'dex' => 0, 'wit' => 6]", $content); // Crit
    $content = str_replace("['str' => 6, 'con' => 8, 'dex' => 0, 'wit' => 2]", "['str' => 9, 'con' => 12, 'dex' => 0, 'wit' => 3]", $content); // Hybrid
    $content = str_replace("['str' => 8, 'con' => 4, 'dex' => 4, 'wit' => 0]", "['str' => 12, 'con' => 6, 'dex' => 6, 'wit' => 0]", $content); // Dodge
    $content = str_replace("['str' => 8, 'con' => 6, 'dex' => 2, 'wit' => 0]", "['str' => 12, 'con' => 9, 'dex' => 3, 'wit' => 0]", $content); // Universal
    
    // Weapons x1.5
    // Steadfast
    $content = str_replace("'minDamage' => 9.0,\n        'maxDamage' => 11.0,", "'minDamage' => 13.5,\n        'maxDamage' => 16.5,", $content);
    // Executioner
    $content = str_replace("'minDamage' => 7.0,\n        'maxDamage' => 9.0,", "'minDamage' => 10.5,\n        'maxDamage' => 13.5,", $content);
    // Versatile
    $content = str_replace("'minDamage' => 8.5,\n        'maxDamage' => 10.5,", "'minDamage' => 12.75,\n        'maxDamage' => 15.75,", $content);
    
    // Ratings
    $content = str_replace("'blockBreakRating' => 20,", "'blockBreakRating' => 30,", $content);
    $content = str_replace("'blockBreakRating' => 60,", "'blockBreakRating' => 90,", $content);
    $content = str_replace("'maxDamageRating' => 75,", "'maxDamageRating' => 113,", $content);
    $content = str_replace("'flatCritBonus' => 10.0,", "'flatCritBonus' => 15.0,", $content);
    $content = str_replace("'flatCritBonus' => 4.0,", "'flatCritBonus' => 6.0,", $content);

    // Weapon factory requiredLevel
    $content = str_replace("critChanceBonus: \$tpl['critChanceBonus'] ?? 0.0,\n        );", "critChanceBonus: \$tpl['critChanceBonus'] ?? 0.0,\n            requiredLevel: 2,\n        );", $content);
    
    // Character factory level
    $content = str_replace("blockResistRating: 0,\n        );", "blockResistRating: 0,\n            level: 2,\n        );", $content);

    file_put_contents(__DIR__ . '/CombatBalanceStatsWithWeaponLvl2Test.php', $content);
}

function processFullArchetypeTest() {
    $content = file_get_contents(dirname(__DIR__) . '/FullArchetypeBalanceTest.php');
    
    // Namespace & Class name
    $content = str_replace('namespace Tests\Balance;', 'namespace Tests\Balance\Level2;', $content);
    $content = str_replace('class FullArchetypeBalanceTest', 'class FullArchetypeBalanceLvl2Test', $content);
    
    // Config K values
    $content = str_replace('new BlockPenetrationConfig(120, 0.95, 0.20);', 'new BlockPenetrationConfig(180, 0.95, 0.20);', $content);
    $content = str_replace('new MaxDamageConfig(300, 0.80, 0.20);', 'new MaxDamageConfig(450, 0.80, 0.20);', $content);
    
    // Stats (x1.5)
    $content = str_replace("['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0]", "['str' => 12, 'con' => 12, 'dex' => 0, 'wit' => 0]", $content); // Tank/Stable
    $content = str_replace("['str' => 4, 'con' => 8, 'dex' => 0, 'wit' => 4]", "['str' => 6, 'con' => 12, 'dex' => 0, 'wit' => 6]", $content); // Crit
    $content = str_replace("['str' => 6, 'con' => 8, 'dex' => 0, 'wit' => 2]", "['str' => 9, 'con' => 12, 'dex' => 0, 'wit' => 3]", $content); // Hybrid
    $content = str_replace("['str' => 8, 'con' => 4, 'dex' => 4, 'wit' => 0]", "['str' => 12, 'con' => 6, 'dex' => 6, 'wit' => 0]", $content); // Dodge
    $content = str_replace("['str' => 8, 'con' => 6, 'dex' => 2, 'wit' => 0]", "['str' => 12, 'con' => 9, 'dex' => 3, 'wit' => 0]", $content); // Universal
    
    // Weapons x1.5 (Sword)
    $content = str_replace("'minDamage' => 9.0,\n        'maxDamage' => 11.0,", "'minDamage' => 13.5,\n        'maxDamage' => 16.5,", $content); // Steadfast
    $content = str_replace("'minDamage' => 7.0,\n        'maxDamage' => 9.0,", "'minDamage' => 10.5,\n        'maxDamage' => 13.5,", $content); // Executioner
    $content = str_replace("'minDamage' => 8.5,\n        'maxDamage' => 10.5,", "'minDamage' => 12.75,\n        'maxDamage' => 15.75,", $content); // Versatile
    $content = str_replace("'blockBreakRating' => 20,", "'blockBreakRating' => 30,", $content);
    $content = str_replace("'maxDamageRating' => 75,", "'maxDamageRating' => 113,", $content);
    $content = str_replace("'flatCritBonus' => 10.0,", "'flatCritBonus' => 15.0,", $content);
    $content = str_replace("'flatCritBonus' => 4.0,", "'flatCritBonus' => 6.0,", $content);

    // Seals x1.5 (only base damage and flat crit, NOT crit chance)
    $content = str_replace("'minDamage' => 0.675,\n        'maxDamage' => 0.825,", "'minDamage' => 1.0125,\n        'maxDamage' => 1.2375,", $content); // Steadfast
    $content = str_replace("'minDamage' => 0.5,\n        'maxDamage' => 0.65,", "'minDamage' => 0.75,\n        'maxDamage' => 0.975,", $content); // Executioner
    $content = str_replace("'minDamage' => 0.7,\n        'maxDamage' => 0.85,", "'minDamage' => 1.05,\n        'maxDamage' => 1.275,", $content); // Versatile
    $content = str_replace("'flatCritBonus' => 1.5,", "'flatCritBonus' => 2.25,", $content); // Seal flat crit
    $content = str_replace("'flatCritBonus' => 0.5,", "'flatCritBonus' => 0.75,", $content); // Seal flat crit

    // Armor x1.5 (AD ONLY, NOT DODGE)
    $content = str_replace("'adArmor' => 6.0,", "'adArmor' => 9.0,", $content); // Tank
    // Dodge armor AD is 0.0, stays 0.0
    $content = str_replace("'adArmor' => 4.0,", "'adArmor' => 6.0,", $content); // Universal

    // Update factories for requiredLevel
    $content = str_replace("critChanceBonus: \$tpl['critChanceBonus'] ?? 0.0,\n        );", "critChanceBonus: \$tpl['critChanceBonus'] ?? 0.0,\n            requiredLevel: 2,\n        );", $content);
    
    // For seals:
    $content = str_replace("requiredWit: 0\n        );", "requiredWit: 0,\n            requiredLevel: 2\n        );", $content);
    
    // For armor:
    $content = str_replace("pierceDamageReduction: 0.0\n        );", "pierceDamageReduction: 0.0,\n            requiredLevel: 2\n        );", $content);

    // Character factory level
    $content = str_replace("blockResistRating: 0,\n        );", "blockResistRating: 0,\n            level: 2,\n        );", $content);

    file_put_contents(__DIR__ . '/FullArchetypeBalanceLvl2Test.php', $content);
}

function processFullEquipmentTest() {
    $content = file_get_contents(dirname(__DIR__) . '/FullEquipmentArchetypeBalanceTest.php');
    
    // Namespace & Class name
    $content = str_replace('namespace Tests\Balance;', 'namespace Tests\Balance\Level2;', $content);
    $content = str_replace('class FullEquipmentArchetypeBalanceTest', 'class FullEquipmentArchetypeBalanceLvl2Test', $content);
    
    // Config K values
    $content = str_replace('new BlockPenetrationConfig(120, 0.95, 0.20);', 'new BlockPenetrationConfig(180, 0.95, 0.20);', $content);
    $content = str_replace('new MaxDamageConfig(300, 0.80, 0.20);', 'new MaxDamageConfig(450, 0.80, 0.20);', $content);
    
    // Stats (x1.5)
    $content = str_replace("['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0]", "['str' => 12, 'con' => 12, 'dex' => 0, 'wit' => 0]", $content); // Tank/Stable
    $content = str_replace("['str' => 4, 'con' => 8, 'dex' => 0, 'wit' => 4]", "['str' => 6, 'con' => 12, 'dex' => 0, 'wit' => 6]", $content); // Crit
    $content = str_replace("['str' => 6, 'con' => 8, 'dex' => 0, 'wit' => 2]", "['str' => 9, 'con' => 12, 'dex' => 0, 'wit' => 3]", $content); // Hybrid
    $content = str_replace("['str' => 8, 'con' => 4, 'dex' => 4, 'wit' => 0]", "['str' => 12, 'con' => 6, 'dex' => 6, 'wit' => 0]", $content); // Dodge
    $content = str_replace("['str' => 8, 'con' => 6, 'dex' => 2, 'wit' => 0]", "['str' => 12, 'con' => 9, 'dex' => 3, 'wit' => 0]", $content); // Universal
    
    // Weapons x1.5 (Sword)
    $content = str_replace("'minDamage' => 9.0,\n        'maxDamage' => 11.0,", "'minDamage' => 13.5,\n        'maxDamage' => 16.5,", $content); // Steadfast
    $content = str_replace("'minDamage' => 7.0,\n        'maxDamage' => 9.0,", "'minDamage' => 10.5,\n        'maxDamage' => 13.5,", $content); // Executioner
    $content = str_replace("'minDamage' => 8.5,\n        'maxDamage' => 10.5,", "'minDamage' => 12.75,\n        'maxDamage' => 15.75,", $content); // Versatile
    $content = str_replace("'blockBreakRating' => 20,", "'blockBreakRating' => 30,", $content);
    $content = str_replace("'maxDamageRating' => 75,", "'maxDamageRating' => 113,", $content);
    $content = str_replace("'flatCritBonus' => 10.0,", "'flatCritBonus' => 15.0,", $content);
    $content = str_replace("'flatCritBonus' => 4.0,", "'flatCritBonus' => 6.0,", $content);

    // Seals x1.5 
    $content = str_replace("'minDamage' => 0.675,\n        'maxDamage' => 0.825,", "'minDamage' => 1.0125,\n        'maxDamage' => 1.2375,", $content); // Steadfast
    $content = str_replace("'minDamage' => 0.5,\n        'maxDamage' => 0.65,", "'minDamage' => 0.75,\n        'maxDamage' => 0.975,", $content); // Executioner
    $content = str_replace("'minDamage' => 0.7,\n        'maxDamage' => 0.85,", "'minDamage' => 1.05,\n        'maxDamage' => 1.275,", $content); // Versatile
    $content = str_replace("'flatCritBonus' => 1.5,", "'flatCritBonus' => 2.25,", $content); // Seal flat crit
    $content = str_replace("'flatCritBonus' => 0.5,", "'flatCritBonus' => 0.75,", $content); // Seal flat crit

    // Armor x1.5
    $content = str_replace("'adArmor' => 6.0,", "'adArmor' => 9.0,", $content); // Tank
    $content = str_replace("'adArmor' => 4.0,", "'adArmor' => 6.0,", $content); // Universal

    // SHIELD x1.5
    $content = str_replace("'blockResistRating' => 40,", "'blockResistRating' => 60,", $content);
    $content = str_replace("'adArmor' => 2.0,", "'adArmor' => 3.0,", $content); // Tank shield AD
    $content = str_replace("'adArmor' => 1.0,", "'adArmor' => 1.5,", $content); // Universal shield AD

    // DAGGER x1.5
    $content = str_replace("'minDamage' => 3.5,\n        'maxDamage' => 4.5,", "'minDamage' => 5.25,\n        'maxDamage' => 6.75,", $content); // Executioner Dagger
    $content = str_replace("'parryRating' => 5,", "'parryRating' => 7,", $content); // integer, 7.5 -> 7
    // blockBreakRating for dagger was 20. But the str_replace for 20->30 might have already hit it if it matches perfectly.
    // wait, executioner dagger: 'blockBreakRating' => 20,. It will be replaced to 30.

    // 2H WEAPONS x1.5
    // Steadfast 2H
    $content = str_replace("'minDamage' => 11.7,\n        'maxDamage' => 14.3,", "'minDamage' => 17.55,\n        'maxDamage' => 21.45,", $content);
    $content = str_replace("'blockBreakRating' => 120,", "'blockBreakRating' => 180,", $content); // Steadfast 2H Axe
    // Executioner 2H
    $content = str_replace("'minDamage' => 9.1,\n        'maxDamage' => 11.7,", "'minDamage' => 13.65,\n        'maxDamage' => 17.55,", $content);
    $content = str_replace("'flatCritBonus' => 13.0,", "'flatCritBonus' => 19.5,", $content);
    // Versatile 2H
    $content = str_replace("'minDamage' => 11.05,\n        'maxDamage' => 13.65,", "'minDamage' => 16.575,\n        'maxDamage' => 20.475,", $content);
    $content = str_replace("'flatCritBonus' => 5.2,", "'flatCritBonus' => 7.8,", $content); // L1 had 5.2
    
    // Dagger factory requiredLevel
    $content = str_replace("parryRating: \$tpl['parryRating'],\n        );", "parryRating: \$tpl['parryRating'],\n            requiredLevel: 2,\n        );", $content);
    
    // Shield factory requiredLevel
    $content = str_replace("dodgeBonus: \$tpl['dodgeBonus']\n        );", "dodgeBonus: \$tpl['dodgeBonus'],\n            requiredLevel: 2\n        );", $content);

    // Update factories for requiredLevel
    $content = str_replace("critChanceBonus: \$tpl['critChanceBonus'] ?? 0.0,\n        );", "critChanceBonus: \$tpl['critChanceBonus'] ?? 0.0,\n            requiredLevel: 2,\n        );", $content);
    $content = str_replace("requiredWit: 0\n        );", "requiredWit: 0,\n            requiredLevel: 2\n        );", $content);
    $content = str_replace("pierceDamageReduction: 0.0\n        );", "pierceDamageReduction: 0.0,\n            requiredLevel: 2\n        );", $content);
    $content = str_replace("blockResistRating: 0,\n        );", "blockResistRating: 0,\n            level: 2,\n        );", $content);

    file_put_contents(__DIR__ . '/FullEquipmentArchetypeBalanceLvl2Test.php', $content);
}

processWeaponTest();
processFullArchetypeTest();
processFullEquipmentTest();
echo "Generated Level 2 Tests\n";
