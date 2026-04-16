# MMORPG Prototype — Claude Context

PHP, Laravel, Docker, PostgreSQL, Vue

## Design Philosophy

This system is built as a deterministic combat simulator.

The goal is not randomness-driven gameplay, but:
- predictable mechanics
- balance through math
- simulation-driven tuning

---

## Core Gameplay Loop

1. Player acquires gear
2. Builds a loadout
3. Enters combat
4. Combat resolves deterministically
5. Results are analyzable

---

## Combat Model

- synchronous turns
- no initiative system
- all actions resolved together

This leads to:
- possible simultaneous deaths (draws)
- stable time-to-kill (~4 rounds)

---

## Key Mechanics

- dodge (binary)
- additional armor (damage absorption with depletion)
- crit (burst modifier)
- penetration (anti-armor)

---

## Balance Philosophy

Avoid:
- hard counters (extreme winrate imbalance)

Target:
- soft counters
- rock-paper-scissors relationships

---

## Equipment Model

- gear-first system
- seals act as small weapon amplifiers (~8–10%)

Armor:
- applies per body part
- does NOT stack globally

---

## Current Limitations

- no progression system

---

## Critical Constraint

All systems must remain:
- deterministic
- testable via simulation
- decoupled from infrastructure