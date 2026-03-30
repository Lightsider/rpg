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
- Intelligence → crit
- Endurance → HP

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
- Tank (stable)
- Crit (burst)
- Universal

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

## Next Step

Add multi-unit combat (2v2, N vs M) before progression systems.

---

## Key Principle

Deterministic simulation-first design.
2. CODEX_PROMPT.md

👉 Codex лучше работает с инструкциями и четкими правилами

# MMORPG Prototype — Codex Instruction Context

You are working on a deterministic MMORPG prototype.

## Core Rules

- DO NOT place logic in controllers or WebSocket handlers
- ALL business logic must be in Domain or Application layers
- Domain must NOT depend on Laravel, DB, or WebSocket
- Server is authoritative

---

## Architecture

- Domain: pure logic
- Application: use cases
- Infrastructure: DB + WebSocket
- Presentation: Vue

---

## Combat

- synchronous turn-based
- all actions resolved simultaneously
- deterministic pseudo-random system

---

## Equipment

- gear-first system
- items define base stats
- stats amplify them

Slots:
- head, torso, legs
- left_hand, right_hand
- 4 seals

---

## Inventory vs Equipment

- Inventory = storage
- Equipment = active state

Item cannot exist in both at the same time

---

## Shop

- StoreItem ≠ Item
- StoreItem = offer
- Items are added to inventory after purchase

---

## Chat (in progress)

- location chat
- battle chat
- private chat

---

## Current Goal

Implement new features without breaking:
- determinism
- simulation capability
- layered architecture

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