# Gemini Entrypoint (Compatibility)

Primary skill file:
- `skills/gemini.skill.md`

Shared base contract:
- `skills/mmorpg-core.md`

## Fallback (if only this file is read)
- Use layered architecture: Domain / Application / Infrastructure / Presentation.
- Keep business logic outside controllers and websocket handlers.
- Preserve deterministic pseudo-random combat semantics.
- Keep server as the single source of truth.
- Follow inventory/equipment/store invariants and architecture guardrails.
