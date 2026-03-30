# Fix FullArchetypeBalanceTest — align with database seeder

## Context
Test weapon/armor values diverge from `database/seeders/ItemSeeder.php`. The test also conflates "Tank attack" (tank weapon archetype) with "Tank defend" (tank armor build) in defense tests — both fighters share the attacker's weapon instead of using archetype-appropriate gear.

## Changes

### 1. Fix weapon values in `createTankGear()`, `createCritGear()`, `createUniGear()`

Match `ItemSeeder` exactly:
- All swords: `accuracyBonus: 0.0` (was 0.1)
- All weapons: `pierceMultiplier: 0.0` (was 0.5/0.65 — seeder doesn't set it, DB default is 0)
- Guardian Axe: `blockBreakRating: 60` (was 70)
- Executioner Sword: `blockBreakRating: 20` (was 10)
- Executioner Axe: `blockBreakRating: 60` (was 40)
- Balanced Sword: `blockBreakRating: 20` (was 30)

### 2. Fix armor constructors in `createTankArmor()`, `createDodgeArmor()`, `createUniArmor()`

Armor constructor params are: `requiredStrength`, `requiredWit`, `requiredDexterity`, `requiredConstitution`

- Guardian: `requiredStrength: 0, requiredConstitution: 10` (was str:10 con:0)
- Shadow: `requiredDexterity: 5, requiredConstitution: 5` (was str:0 con:10)
- Balanced: `requiredDexterity: 3, requiredConstitution: 7` (was str:7 con:3)

### 3. Fix defense tests — separate "Tank attack" from "Tank defend"

Update `runDefenseFocusTests` to accept a defender weapon factory + defender stats.
Each defense matchup pairs attacker weapon vs defender weapon (both archetype-appropriate):

- Tank defender uses `createTankGear`, `getTankStats`
- Dodge defender uses `createTankGear` (no dodge weapons exist), `getDodgeStats`
- Uni defender uses `createUniGear`, `getUniStats`

Update callers:
- `test_defense_balance_vs_tank_attack`: def factory = `createTankGear`, def stats = `getTankStats`
- `test_defense_balance_vs_crit_attack`: def factory = `createCritGear`, def stats = `getCritStats`
- `test_defense_balance_vs_universal_attack`: def factory = `createUniGear`, def stats = `getUniStats`

## File
`tests/Feature/FullArchetypeBalanceTest.php`

## Verification
Run: `php artisan test --filter=FullArchetypeBalanceTest`
