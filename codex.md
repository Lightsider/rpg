# MMORPG Prototype — Codex Instruction Context

You are working on a deterministic MMORPG prototype.

PHP, Laravel, Docker, PostgreSQL, Vue

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

## Chat

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