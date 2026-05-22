# MMORPG Core Skill (Shared Base)

## Purpose
Shared architecture and engineering contract for all coding agents in this repository.

## Product Context
- Deterministic MMORPG prototype
- Stack: PHP, Laravel, Docker, PostgreSQL, Vue
- Server-authoritative gameplay and state

## Architecture Contract
- Layers:
  - `Domain` = pure business logic
  - `Application` = use-case orchestration
  - `Infrastructure` = DB/framework/websocket integrations
  - `Presentation` = HTTP + UI + transport payloads
- Domain must not depend on Laravel, DB models, or websocket/framework concerns.
- Business logic must not be placed in controllers or websocket handlers.

## Determinism Contract
- Combat and resolution logic must remain deterministic.
- No ad-hoc randomness in core resolution paths.
- Changes must preserve simulation capability and reproducibility.

## Gameplay Invariants
- Gear-first model: items define base characteristics, stats amplify behavior.
- Inventory vs Equipment invariant:
  - Inventory = storage
  - Equipment = active state
  - The same item instance cannot be active in both at once.
- Shop invariant:
  - `StoreItem` is an offer record, not a direct `Item` identity.

## Engineering Rules
- Prefer explicit logic and simple structures.
- Avoid hidden side effects and implicit behavior.
- Avoid magic values in critical logic paths.
- Do not perform destructive DB/data actions without explicit confirmation.

## Quality Gates
- Architecture tests are part of the code contract, not optional guidance.
- Boundary rules (layering/dependencies) should be enforced and expanded incrementally.
- Refactors should preserve runtime behavior and public API contracts unless explicitly planned otherwise.

## Testing Expectations
- For architecture-affecting changes: run architecture boundary tests.
- For combat/inventory/loadout changes: run targeted feature regressions for deterministic combat and equipment/inventory invariants.
