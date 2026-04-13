---
name: rakez-filament-admin-backend
description: Guides Filament admin work (Filament 5.x in rakez-erp) in the Laravel ERP backend only—one panel, domain/services/policies as source of truth, no duplicated business logic or API breakage. Use when building or changing Filament resources/pages/widgets, admin authorization, audits, or when the user mentions Filament admin, internal admin console, or backend-only admin for this ERP repo.
---

# Rakez ERP — Filament Admin (Backend Only)

## Hard scope

- Work **only** inside the **backend Laravel ERP** tree (this monorepo: typically `rakez-erp/`).
- **No** changes to any separate frontend repository or SPA.
- **No** general “frontend” work (Blade views for public app, Inertia, JS bundles, etc.) unless explicitly in scope for Filament’s own UI—and prefer keeping surface area minimal.
- **Focus:** Filament as the **real internal admin operating console**, wired to existing application layers.

## Architectural non‑negotiables

1. **Exactly one** Filament Admin panel configuration (single panel app; no parallel “shadow” admin stacks).
2. **Backend** domain rules, **services**, and **policies** remain the **source of truth**. Filament calls them; it does not re‑implement business rules inline.
3. **No fake UI‑only workflows** (no “success” toasts that skip persistence, no forms that bypass validation, no dead actions).
4. **Do not** redesign or break **existing business HTTP APIs** consumed outside Filament. Add/adjust admin-specific routes or Filament-only endpoints only when necessary and keep public contracts stable.
5. **No governance-only artificial read-only** restrictions **unless** required by **safety** or because **backend support is genuinely missing**—then document which case applies (see “Limited sections”).
6. Where backend support **already exists**, **restore full admin capability** (create/update/delete, imports, jobs triggers, etc.) through services—not through duplicated logic.

## Authorization (layered — apply all relevant layers)

Enforce in order as applicable:

1. **Panel access** — who may open Filament at all.
2. **Section / navigation visibility** — hide what a role should not see.
3. **Page / resource visibility** — `canView`, `canAccess`, etc., aligned with permissions.
4. **Action permissions** — table/header actions, bulk actions, relation managers.
5. **Record / data scope** — tenant/team/ownership filters via queries and policies.
6. **Service / policy enforcement** — mutations must go through the same gates as non-Filament code paths.

Never rely on “hidden UI” alone; server-side checks must match.

## Auditability

- Sensitive or irreversible actions (permissions, money, contracts, deletes, exports, token/ads ops, governance) must leave an **audit trail** consistent with existing patterns (models/events/`GovernanceAuditLog`/domain audit ports—use what the codebase already provides).
- Prefer invoking existing **command/service** methods that already audit, rather than duplicating logging.

## When a section is still limited

In summaries or handoff notes, state **why**:

| Reason | Meaning |
|--------|---------|
| **Backend support missing** | No service API, incomplete domain, or migration gap—Filament cannot honestly do more yet. |
| **Safety** | Correctness, fraud/abuse, PII, or production risk—restriction is intentional. |
| **Implementation incomplete** | The backend exists; Filament wiring/tests/docs not finished—track as follow-up. |

## Execution wave (mandatory order)

Every delivery wave must follow this sequence **exactly**:

1. **CODE** — implement against services/policies; minimal surface.
2. **REVIEW** — self-review: scope, auth layers, duplication, API impact.
3. **TEST** — automated tests (PHPUnit/feature tests touching changed paths); run relevant suites.
4. **REVIEW** — second pass after tests (failures, edge cases, flakiness).
5. **HARDEN** — validation, authorization gaps, N+1 queries, transaction boundaries, logging.
6. **VISUAL TESTING** — exercise Filament UI for the changed pages/actions (manual or automated browser checks as available).

Do not skip or reorder steps.

## Output language

- **Prompts / user chat** may stay in **English** unless the user chooses another language.
- **Reports** from the agent (summaries, tables, review notes) default to **English** unless the user explicitly requests another language.

## Implementation checklist (Filament-specific)

- [ ] Resource/page uses **policies** and/or **authorization** helpers consistent with the rest of the app.
- [ ] Heavy work delegated to **services**, **jobs**, or **actions** already used elsewhere.
- [ ] No duplicated validation rules that diverge from `FormRequest`/domain validators—**reuse** or centralize.
- [ ] List queries **scoped**; avoid leaking records across tenants/users.
- [ ] **Transactions** where multiple writes must succeed or fail together.
- [ ] **Tests** updated or added for permissions and critical paths.

## Anti-patterns

- Copy-pasting business rules from a `Controller` into a Filament `mutateFormDataBeforeCreate`.
- New admin-only “shortcut” tables that mirror production entities without a clear sync story.
- Gating destructive actions only with `visible()` without `authorize()` / policy checks on the action.
- Changing JSON shape or routes of **public** APIs to suit Filament.

## Progressive disclosure

If this repo adds Filament conventions (base `Resource` classes, traits, shared relation managers), link or describe them in a short `reference.md` beside this skill when it grows—keep `SKILL.md` under **500 lines**.
