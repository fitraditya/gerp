# Documentation.md — GM ERP Documentation Structure

> This file specifies which docs must stay in sync as code changes.

## Doc files and their scope

| File | Purpose | Update trigger | Owner |
|---|---|---|---|
| `docs/PRD.md` | Product requirements, user stories, acceptance criteria | New features or user-facing workflows | Product |
| `docs/RFC.md` | Technical design, API sequences, permission matrix, architecture decisions | Architecture changes, new services, schema changes | Tech Lead |
| `docs/RBAC.md` | Role definitions, permission scoping rules, policy examples | Permission changes, new roles, scope changes | Tech Lead |
| `AGENTS.md` | AI agent instructions, naming conventions, CI commands | Naming pattern changes, new tools, process changes | Tech Lead |
| `README.md` | Local setup, environment, running Filament | Environment changes, new dependencies | DevOps |

## When to update docs

### Adding a new Filament resource (e.g., ProductResource)
- Update `docs/RFC.md` §2 Module Breakdown table with the new resource
- Update `docs/RBAC.md` with relevant permission scope
- Update `AGENTS.md` Naming Conventions table if resource follows a new pattern

### Adding a new domain service (e.g., RemittanceService)
- Update `docs/RFC.md` §2 Architecture with the service's responsibility
- Update `docs/RFC.md` sequence diagrams if it involves multiple actors
- Add test class reference to `AGENTS.md` Testing table

### Adding a new API endpoint or changing permission logic
- Update `docs/RFC.md` Role & Permission Matrix if roles or gates change
- Update `docs/RBAC.md` with new permission names and scoping rules
- Ensure `AGENTS.md` Cold-start ritual still holds

### Changing the warehouse scope logic or adding new global scopes
- Update `docs/RBAC.md` Scope rules section
- Update `AGENTS.md` Testing table with new scope test patterns

### Schema changes (new migration or altered table)
- Update `docs/RFC.md` relevant entity diagram (if present)
- If soft-deletes or timestamps change, note in `AGENTS.md` Naming Conventions

## How agents should use these docs

1. **Before starting any task:** Read `docs/PRD.md` for user story scope, `docs/RFC.md` for technical design, `docs/RBAC.md` for permission rules.
2. **When adding a feature:** Check the relevant section in `docs/RFC.md`; ensure your implementation matches the documented sequence/architecture.
3. **When adding tests:** Refer to `AGENTS.md` Testing table for isolation patterns and triggers.
4. **When deploying:** Verify no doc-code gaps exist by running `make docs:check` (future command).

## Docs integrity check

Before committing a code change that touches models, services, migrations, or permissions, verify:
- [ ] Does `docs/RFC.md` match the new architecture component?
- [ ] Does `docs/RBAC.md` match the new permission scope?
- [ ] Does `AGENTS.md` naming/pattern still apply?
- [ ] Do acceptance criteria in `docs/PRD.md` still hold?
