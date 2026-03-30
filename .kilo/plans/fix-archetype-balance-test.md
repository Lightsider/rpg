# Fix FullArchetypeBalanceTest — align with database seeder

## Context
Test weapon/armor values diverge from `database/seeders/ItemSeeder.php`. The seeder now explicitly sets `pierce_multiplier` and `max_damage_rating`. Defense test structure is correct as-is: both fighters use the same weapon, all 3 archetypes × 2 types are covered. "Tank attack" and "Tank defend" are different concepts (weapon archetype vs armor build) sharing one name — the test correctly isolates them.

## File
`tests/Feature/FullArchetypeBalanceTest.php`

## Changes

### 1. Fix weapon values (8 fixes + add `maxDamageRating`)

`createTankGear()`, `createCritGear()`, `createUniGear()`:

| Field | Swords (all 3) | Axes (all 3) |
|-------|----------------|--------------|
| `accuracyBonus` | **0.0** (was 0.1) | 0.0 — OK |
| `blockBreakRating` | see below | see below |
| `pierceMultiplier` | 0.50 — OK | 0.65 — OK |
| `maxDamageRating` | **add: 90** (param was missing, default 0) | 0 — OK (omit or pass 0) |

Block break rating fixes:
- Guardian Axe: **60** (was 70)
- Executioner Sword: **20** (was 10)
- Executioner Axe: **60** (was 40)
- Balanced Sword: **20** (was 30)

### 2. Fix armor constructors

Armor constructor: `($id, $name, $adArmor, $dodgeBonus, $subtype, $requiredStrength=0, $requiredWit=0, $requiredDexterity=0, $requiredConstitution=0)`

| Armor | Seeder: str/dex/con | Test passes | Fix to |
|-------|---------------------|-------------|--------|
| Guardian (10-13) | 0/0/10 | str=10, con=0 | str=0, con=10 |
| Shadow (14-17) | 0/5/5 | str=0, con=10 | str=0, dex=5, con=5 |
| Balanced (18-21) | 0/3/7 | str=7, con=3 | str=0, dex=3, con=7 |

### 3. No changes to defense test structure
Both fighters share the attacker's weapon — this is correct. The test suite covers all 3 archetypes × 2 types via the callers + weapon loop.

## Verification
Run: `php artisan test --filter=FullArchetypeBalanceTest`
