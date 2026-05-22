# Claude Entrypoint (Compatibility)

Primary skill file:
- `skills/claude.skill.md`

Shared base contract:
- `skills/mmorpg-core.md`

## Fallback (if only this file is read)
- Prioritize explicit, testable, deterministic logic.
- Keep Domain decoupled from infrastructure/framework concerns.
- Keep controllers and websocket handlers thin.
- Preserve server-authoritative behavior and simulation capability.
- Treat architecture tests as a quality contract.
