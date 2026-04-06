MMORPG Prototype — Context (Gemini)

## Overview
This is a deterministic MMORPG prototype focused on:
- synchronous turn-based combat
- gear-first design
- server-authoritative architecture
- simulation-based balancing

---

## Tech Stack

Backend:
- PHP (Laravel)
- PostgreSQL
- docker

Frontend:
- Vue.js SPA

Real-time:
- WebSocket (primary communication)

---

## Architecture

Layered:

- Domain: pure business logic
- Application: use cases
- Infrastructure: DB, WebSocket, Laravel
- Presentation: UI

Server is the single source of truth.

---

## Combat System

- synchronous turns (players act simultaneously)
- grid-based battlefield
- body-part targeting (head, torso, arms, legs)

### Round resolution:
1. validation
2. movement
3. attacks:
   - range check
   - dodge
   - block
   - penetration
   - damage
   - crit
4. HP update
5. death check

---

## Determinism

- no raw RNG
- all randomness is pseudo-deterministic
- system is simulation-friendly

---

## Stats

- Strength → damage
- Dexterity → dodge
- Wit → crit
- Constitution → HP

---

## Equipment System

### Slots

- head, torso, legs
- left_hand, right_hand
- 4 seals

### Rules

- gear defines base stats
- stats amplify gear

---

## Combat Archetypes

Attack:
- stable)  
- Crit (burst)
- Hybrid

Defense:
- Tank (armor)
- Dodge
- Universal

Meta follows rock-paper-scissors.

---

## Systems Implemented

- combat (1v1)
- inventory
- equipment
- shop (MVP)
- WebSocket events

---

## Systems in Progress

- chat
- equipment improvements

---

## Important

Prefer:
- explicit logic
- simple structures
- no hidden side effects

Avoid:
- magic
- implicit behavior

Не используем магические значения никогда
Никогда не затирай данные в базе данных без дополнительного подтверждения
Вся критичная логика должна обрабатываться на бекенде