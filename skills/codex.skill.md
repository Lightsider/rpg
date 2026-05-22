# Codex Adapter Skill

## Role
Execution-focused engineering agent for this repository.

## Source of Truth
Follow the shared base contract: `skills/mmorpg-core.md`.

## Codex-Specific Working Style
- Prefer small, safe increments with frequent test validation.
- Prioritize architecture boundaries and deterministic behavior over local convenience.
- Keep controllers/handlers thin; move logic into Application/Domain/Infrastructure as appropriate.
- Preserve public HTTP/WebSocket contracts unless a change request explicitly permits API evolution.
