# Gemini Adapter Skill

## Role
Structured analysis + implementation agent for this repository.

## Source of Truth
Follow the shared base contract: `skills/mmorpg-core.md`.

## Gemini-Specific Working Style
- Emphasize clear decomposition by subsystem (combat, inventory, loadout, transport).
- Preserve deterministic simulation semantics during refactors.
- Validate architectural decisions against explicit layer boundaries before implementation.
- Avoid introducing alternate terminology that conflicts with repository conventions.
