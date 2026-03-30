# MMORPG Prototype — KiloCode Context

## System Type
Deterministic MMORPG combat simulator

---

## Stack
- Laravel (PHP)
- PostgreSQL
- Vue.js
- WebSocket

---

## Architecture
- Domain (logic only)
- Application (use cases)
- Infrastructure (DB, WS)
- Presentation (UI)

---

## Combat
- synchronous turns
- grid-based
- body targeting
- deterministic pseudo-random

---

## Equipment
Slots:
- head, torso, legs, gloves
- left_hand, right_hand
- 4 seals

Rules:
- gear defines stats
- stats amplify gear

---

## Inventory
- stores items

## Equipment
- active items only

---

## Shop
- StoreItem = offer
- Item = object

---

## Chat
- location
- battle
- private

---

## Current Focus
- expand systems without breaking determinism
- keep logic centralized

---

## Next Feature
multi-unit combat (2v2+)