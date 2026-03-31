# Plan: Fix Crit Chance from Weapons and Seals

## Problem

`Character::calculateCritChance()` has two bugs that prevent weapon and seal crit bonuses from contributing to crit chance:

1. **Typo** (`Character.php:293`): `$sealingBonus` is undefined — should be `$sealsCritBonus`
2. **Wrong namespace** (`Character.php:308`): `instanceof \App\Domain\Seals\Seal` — should be `\App\Domain\Seal\Seal` (no "s")
3. **Throws on unarmed** (`Character.php:289`): `getWeapon()` throws DomainException if no weapon; should use `getWeaponForCombat()` (unarmed fallback)
4. **Unit mismatch** (`Character.php:289`): weapon `flat_crit_bonus` is in percentage points (10.0 = 10%) but added directly to decimal probability (base crit is 0.32 for wit=4). Seals are already divided by 100 in `getSealsCritBonus()`. Weapon bonus needs `/100` too.

## Design Notes

- Weapon `flat_crit_bonus` values (10.0 for Executioner, 4.0 for Balanced) are in **percentage points** for crit chance AND **flat damage** for crit hits (used in CombatResolver). Same field, dual purpose. Conversion: `weapon.getFlatCritBonus() / 100` for crit chance.
- Seal `flat_crit_bonus` values (0.8 for Executioner, 0.3 for Balanced) are already in **decimal probability**. `getSealsCritBonus()` divides by 100 again, while `getSealsFlatCritBonus()` does NOT divide. For crit chance, use `getSealsFlatCritBonus() / 100`.
- Remove the broken `getSealsCritBonus()` method entirely — `getSealsFlatCritBonus()` already works correctly.

## Changes

### 1. `app/Domain/Character/Character.php`

**Fix `calculateCritChance()`** (lines 286-294):
```php
public function calculateCritChance(): float
{
    $baseCritChance = CombatFormulas::critChance($this->wit);
    $weapon = $this->getWeaponForCombat();
    $weaponCritBonus = $weapon->getFlatCritBonus() / 100;
    $sealsCritBonus = $this->getSealsFlatCritBonus() / 100;
    return $baseCritChance + $weaponCritBonus + $sealsCritBonus;
}
```

**Remove broken `getSealsCritBonus()` method** (lines 296-314) — replaced by working `getSealsFlatCritBonus()`.

### 2. `tests/Feature/CombatBalanceStatsWithWeaponTest.php`

Add new test methods that equip crit and universal seals alongside archetype weapons, verifying crit builds with seals increase crit rate. Add:
- Seal constants (Guardian/Executioner/Balanced) matching ItemSeeder values
- `createSeal()` helper method
- Tests: crit weapon + crit seals vs crit weapon (no seals), universal weapon + universal seals vs universal (no seals)

### 3. `database/seeders/ItemSeeder.php`

No changes needed — seal `flat_crit_bonus` values are already correct:
- Guardian Seal: 0.0 (no crit)
- Executioner Seal: 0.8 (high crit)
- Balanced Seal: 0.3 (moderate crit)

### 4. CombatResolver

No changes needed — `CombatResolver::checkCritical()` already calls `$attacker->calculateCritChance()` correctly.

## Summary of crit chance formula (after fix)

```
critChance = (wit * 0.08) + (weapon.flat_crit_bonus / 100) + (sum(seal.flat_crit_bonus) / 100)
```

Example (Executioner weapon + 4 Executioner seals, wit=4):
```
= (4 * 0.08) + (10.0 / 100) + (0.8 * 4 / 100)
= 0.32 + 0.10 + 0.032
= 0.452 (45.2% crit chance)
```

Example (Balanced weapon + 4 Balanced seals, wit=2):
```
= (2 * 0.08) + (4.0 / 100) + (0.3 * 4 / 100)
= 0.16 + 0.04 + 0.012
= 0.212 (21.2% crit chance)
```
