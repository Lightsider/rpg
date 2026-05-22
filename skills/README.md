# Skills Maintenance Checklist

## Update Order
1. Update `skills/mmorpg-core.md` first for any architecture/business rule change.
2. Update agent adapter files only for agent-specific style/tooling differences.
3. Keep root entrypoints (`/codex.md`, `/gemini.md`, `/claude.md`) aligned and minimal.

## Rules
- Do not duplicate full architecture policy in adapter files.
- Do not introduce contradictory terminology across adapter files.
- Use base file as the single source of truth for domain/application/infrastructure contracts.

## Review Checklist
- Base includes deterministic, layering, server-authoritative and invariant rules.
- Each adapter references base and contains only tool/agent-specific behavior.
- Root files remain compatible entrypoints and point to `skills/*`.
- Architecture tests still pass after instruction updates.
