# Filament governance panel — operations runbook

## Access model

- Panel path: `config('governance.panel_path')` (default `admin`).
- Users need a **managed governance role** (`config('governance.managed_panel_roles')`), active account, and **`admin.panel.access`** (except `super_admin`, who bypasses permission checks inside `GovernanceAccessService`).
- **Operational** Laravel roles (`config('governance.operational_roles')`) alone do **not** grant panel access.

## Navigation and sections

- **Access Governance** and **Governance Observability** items are gated only by their resource permissions (`admin.users.*`, `admin.audit.view`, etc.).
- **Business oversight** groups (Credit, Sales, …) require **both**:
  1. Any permission listed for that group in `config('governance.filament_navigation_group_permissions')`, and  
  2. The resource’s own view permission (`canViewAny`).
- **Requests & Workflow** reference oversight uses `governance.oversight.workflow.view` (or notification permissions where applicable) so Filament visibility is not tied to operational `tasks.create` alone.

## Temporary permissions

- Stored in `governance_temporary_permissions`. Active rows have `revoked_at` null and `expires_at` in the future.
- **Enforcement:** `GovernanceAccessService` treats active temporary rows like direct grants for **Filament governance checks** and they appear in **effective access** snapshots and `User::getEffectivePermissions()`.
- **UI:** `Access Governance` → **Temporary Permissions** (`admin.temp_permissions.view` / `admin.temp_permissions.manage`).
- **Expiry:** hourly scheduler runs `governance:expire-temporary-permissions`, which sets `revoked_at` on overdue rows. Run manually after deploy if needed.

## Approvals center

- Page: **Approvals Center** under **Requests & Workflow**, permission `governance.approvals.center.view`.
- Read-only snapshot counts (tasks, notifications, exclusive requests); detailed work stays in operational ERP UIs.

## Rollout checklist

1. Run migrations (includes `governance_temporary_permissions`).
2. Run `RolesAndPermissionsSeeder` (or equivalent) so new permission names exist in Spatie.
3. Assign governance overlay roles only to intended operators; keep `super_admin` minimal.
4. Verify scheduler runs `governance:expire-temporary-permissions` in production.
5. Smoke-test: login as `erp_admin`, `workflow_admin`, and a section admin; confirm navigation matches role permissions.

## Stability notes

- Do not use Filament as the primary data-entry UI for day-to-day ERP work; keep operational flows in the existing application.
- Prefer **read-only** oversight resources unless a policy-backed action is explicitly approved.
