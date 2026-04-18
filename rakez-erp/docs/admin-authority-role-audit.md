# Admin Authority Naming Audit

## Decision Context
- Internal highest-authority role slug remains `super_admin` for DB/security compatibility.
- Business-facing/admin-facing name is presented as `admin` through aliasing.
- Legacy operational `admin` keeps its historical meaning and is presented as `legacy_admin` in governance-facing labels.

## Classified Occurrences (Repository-Grounded)

| File | Occurrence Type | Classification | Decision |
|---|---|---|---|
| `config/governance.php` (`panel_authority_roles`) | AUTHORITY CHECK | PANEL AUTHORITY | Restrict Filament panel entry to top authority only (`super_admin`). |
| `config/governance.php` (`managed_governance_roles`) | ROLE SLUG / DB TRUTH | API/SERVICE-LEVEL AUTHORITY ONLY | Keep section governance roles for service/API governance without panel entry authority. |
| `config/governance.php` (`super_admin_role`) | ROLE SLUG / DB TRUTH | MUST REMAIN INTERNAL | Keep canonical slug `super_admin`.
| `app/Services/Governance/GovernanceAccessService.php` | AUTHORITY CHECK | PROTECTED-ACTOR SECURITY LOGIC | Keep internal slug checks; panel remains top-authority only.
| `app/Services/Governance/UserGovernanceService.php` | PROTECTED-ACTOR SECURITY LOGIC | MUST REMAIN INTERNAL | Keep slug-based protection; update business wording in exception text.
| `app/Services/Governance/DirectPermissionGovernanceService.php` | PROTECTED-ACTOR SECURITY LOGIC | MUST REMAIN INTERNAL | Keep slug-based protection; update business wording in exception text.
| `app/Services/Governance/RoleGovernanceService.php` | PROTECTED-ACTOR SECURITY LOGIC | MUST REMAIN INTERNAL | Keep slug-based protection; update business wording in exception text.
| `database/seeders/AdminUserSeeder.php` | SEED / BOOTSTRAP | LEGACY COMPATIBILITY | Keep dual-role seed (`super_admin` + `admin`) for current route middleware compatibility.
| `database/seeders/RolesAndPermissionsSeeder.php` | SEED / BOOTSTRAP | LEGACY COMPATIBILITY | Keep preserving `super_admin` assignment while syncing legacy `admin` role.
| `app/Providers/AppServiceProvider.php` (`Gate::before` legacy admin bypass) | LEGACY COMPATIBILITY | MUST BE ALIASED | Do not collapse with top authority role; preserve separate operational `admin` behavior.
| `app/Services/Auth/AuthenticatedUserPayloadService.php` | AUTH CONTRACT | MUST BE ALIASED | Keep raw `roles`; add additive `roles_display` alias normalization.
| `app/Http/Controllers/Registration/LoginController.php` | AUTH CONTRACT | MUST BE ALIASED | Forward additive `roles_display` without removing existing fields.
| `app/Filament/Admin/Resources/*` role displays | DISPLAY LABEL | SAFE TO RENAME | Replace raw role-name rendering with alias-aware labels.
| `lang/en/filament-admin.php`, `lang/ar/filament-admin.php` | DISPLAY LABEL | SAFE TO RENAME | User-facing wording now refers to top authority as `admin`.
| `tests/Feature/Governance/*` and `tests/Feature/Auth/*` | TEST EXPECTATION | MUST BE ALIASED | Keep slug-based security tests and add display-alias assertions.

## Alias Rules
- Internal role slug: `super_admin` (security/DB checks)
- Display slug for `super_admin`: `admin`
- Display slug for legacy `admin`: `legacy_admin`
- Raw API field: `roles` (unchanged)
- Additive API field: `roles_display` (alias-normalized)
