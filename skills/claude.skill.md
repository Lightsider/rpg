# Claude Adapter Skill

## Role
Reasoning-oriented implementation agent for this repository.

## Source of Truth
Follow the shared base contract: `skills/mmorpg-core.md`.

## Claude-Specific Working Style
- Prefer explicit tradeoffs and conservative behavioral changes.
- Keep domain logic decoupled from infrastructure concerns.
- Treat deterministic combat invariants as non-negotiable constraints.
- Maintain testability and simulation-first design in all refactor choices.
