# Codex Entrypoint (Compatibility)

Primary skill file:
- `skills/codex.skill.md`

Shared base contract:
- `skills/mmorpg-core.md`

## Fallback (if only this file is read)
- Keep logic out of controllers and websocket handlers.
- Domain is pure and must not depend on Laravel/DB/websocket.
- Application orchestrates use-cases.
- Infrastructure owns DB/framework/integration code.
- Preserve deterministic combat behavior and server-authoritative state.
- Respect inventory/equipment invariants and architecture tests.
