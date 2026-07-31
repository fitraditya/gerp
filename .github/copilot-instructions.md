# GitHub Copilot Instructions

Please read [AGENTS.md](../AGENTS.md) in the root directory for all AI instructions, coding rules, naming conventions, database patterns, and testing requirements. It is the single source of truth for this repository.

## Key workflow

1. Start by reading `docs/PRD.md` for requirements and `docs/RFC.md` for technical design
2. Follow the Safe Commands section in AGENTS.md
3. Adhere to Naming Conventions exactly when adding models, services, or resources
4. All inventory/order mutations must wrap in `DB::transaction()` + `.lockForUpdate()`
5. Write tests using the Testing and CI patterns
6. Update docs per the Documentation.md table before committing
7. Run `php artisan test` to verify all tests pass (exit code 0)

## Quick reference

- **Models:** `app/Models/PascalCase.php` with WarehouseScope for multi-tenant filtering
- **Services:** `app/Services/PascalCase + Service.php` — all business logic here
- **Filament Resources:** `app/Filament/Resources/PascalCase + Resource.php` — call Services, not Models
- **Tests:** `tests/Feature/` (Filament, full flow) or `tests/Unit/` (Services, logic)
- **Database:** Always use row-level locks in transactions; negative stock is allowed

Refer to AGENTS.md for complete patterns, gotchas, and CI commands.
