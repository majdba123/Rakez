# Missing Scenarios Report: Sales, Marketing, AI Assistant

Generated: 2026-01-26  
Scope: Compare current implementation (routes/controllers/services) against
documentation and tests to identify missing or unverified scenarios.

## Module Inventory (Routes)

### Sales Module (`routes/api.php`)
- `GET /api/sales/dashboard`
- `GET /api/sales/projects`, `GET /api/sales/projects/{contractId}`, `GET /api/sales/projects/{contractId}/units`
- `GET /api/sales/units/{unitId}/reservation-context`
- `POST /api/sales/reservations`, `GET /api/sales/reservations`
- `POST /api/sales/reservations/{id}/confirm`, `POST /api/sales/reservations/{id}/cancel`
- `POST /api/sales/reservations/{id}/actions`, `GET /api/sales/reservations/{id}/voucher`
- `GET /api/sales/targets/my`, `PATCH /api/sales/targets/{id}`, `POST /api/sales/targets`
- `GET /api/sales/attendance/my`, `GET /api/sales/attendance/team`, `POST /api/sales/attendance/schedules`
- `GET /api/sales/team/projects`, `GET /api/sales/team/members`
- `PATCH /api/sales/projects/{contractId}/emergency-contacts`
- `GET /api/sales/tasks/projects`, `GET /api/sales/tasks/projects/{contractId}`
- `POST /api/sales/marketing-tasks`, `PATCH /api/sales/marketing-tasks/{id}`
- Admin: `POST /api/admin/sales/project-assignments`

### Marketing Module (`routes/api.php`)
- `GET /api/marketing/dashboard`
- `GET /api/marketing/projects`, `GET /api/marketing/projects/{contractId}`
- `POST /api/marketing/projects/calculate-budget`
- `GET /api/marketing/developer-plans/{contractId}`, `POST /api/marketing/developer-plans`
- `GET /api/marketing/employee-plans/project/{projectId}`, `GET /api/marketing/employee-plans/{planId}`
- `POST /api/marketing/employee-plans`, `POST /api/marketing/employee-plans/auto-generate`
- `GET /api/marketing/expected-sales/{projectId}`, `PUT /api/marketing/settings/conversion-rate`
- `GET /api/marketing/tasks`, `POST /api/marketing/tasks`
- `PUT /api/marketing/tasks/{taskId}`, `PATCH /api/marketing/tasks/{taskId}/status`
- `POST /api/marketing/projects/{projectId}/team`, `GET /api/marketing/projects/{projectId}/team`
- `GET /api/marketing/projects/{projectId}/recommend-employee`
- `GET /api/marketing/leads`, `POST /api/marketing/leads`, `PUT /api/marketing/leads/{leadId}`
- Reports: `GET /api/marketing/reports/project/{projectId}`, `GET /api/marketing/reports/budget`
- Reports: `GET /api/marketing/reports/expected-bookings`, `GET /api/marketing/reports/employee/{userId}`
- Reports: `GET /api/marketing/reports/export/{planId}`
- Settings: `GET /api/marketing/settings`, `PUT /api/marketing/settings/{key}`

### AI Assistant Module (`routes/api.php`)
- `POST /api/ai/ask`, `POST /api/ai/chat`
- `GET /api/ai/conversations`, `DELETE /api/ai/conversations/{sessionId}`
- `GET /api/ai/sections`

## Missing Scenarios

### Sales Module

Documentation gaps (P2)
- `docs/API_EXAMPLES_SALES.md` documents only dashboard, create reservation, and
  target updates; it omits projects, units, reservation context, confirm/cancel,
  actions, voucher download, attendance, team management, marketing tasks, and
  admin project assignment endpoints.
- Dashboard response example doesn’t match actual KPI fields returned by
  `app/Services/Sales/SalesDashboardService.php` (actual fields: `reserved_units`,
  `available_units`, `percent_confirmed`, etc.).

Testing gaps (P1)
- Reservation list filters for `include_cancelled`, `contract_id`, and date range
  (`from`/`to`) are not covered (`app/Services/Sales/SalesReservationService.php`).
- Schedule overlap validation is not tested (`app/Services/Sales/SalesAttendanceService.php`).
- Project list scope behavior (`scope=me|team|all`) isn’t tested
  (`app/Services/Sales/SalesProjectService.php`).
- Emergency contacts update is not tested for the “leader not assigned” path
  (`app/Services/Sales/SalesProjectService.php`).
- Voucher download error paths (missing `voucher_pdf_path` or missing file) are
  untested (`app/Http/Controllers/Sales/SalesReservationController.php`).

Behavioral/authorization scenarios to clarify (P2)
- `logAction()` currently allows any user with `sales.reservations.view` to log
  actions on others’ reservations; there is no test to confirm intended behavior
  (`app/Services/Sales/SalesReservationService.php`).

### Marketing Module

Documentation gaps (P1)
- No `API_EXAMPLES_MARKETING.md` exists; marketing features are only partially
  documented in `docs/POSTMAN_MARKETING_COLLECTION.json`.
- Postman collection lacks endpoints for expected sales, team management,
  reports, settings, and several CRUD paths (e.g., employee plan `show/store`,
  tasks update via `PUT`, leads update).

Testing gaps (P1)
- No feature tests for:
  - Expected sales calculation or conversion rate updates.
  - Team management (assign/get team, recommend employee).
  - Reports (project, budget, expected bookings, employee, export).
  - Settings list/update.
  - Employee plan `show/store` and developer plan `show`.
  - Marketing tasks list/update (PUT) and daily task filtering by date.
  - Leads update.
- Marketing auth/role checks aren’t tested (routes are guarded by the
  `marketing` middleware).

Validation/authorization gaps (P0)
- Marketing controllers accept `$request->all()` with no validation or
  authorization checks for updates (`LeadController`, `MarketingTaskController`,
  `MarketingSettingsController`, `TeamManagementController`).
- Update endpoints allow unrestricted field mutation (e.g., tasks/leads), which
  leaves error scenarios and access boundaries undefined in tests/docs.

### AI Assistant Module

Documentation gaps (P2)
- `docs/API_EXAMPLES_AI.md` omits `GET /api/ai/sections` and
  `DELETE /api/ai/conversations/{sessionId}`.
- Example responses do not show `conversation_id`, `error_code`, or
  `suggestions` which are included by the service.
- Rate limits and daily token budgets are not documented (`config/ai_assistant.php`).

Testing gaps (P1)
- No tests for `ai_assistant.enabled=false` (disabled assistant) paths.
- No tests for rate-limit/throttle behavior (`throttle:ai-assistant`).
- No tests for deleting a session that belongs to another user.

## Notes / Next Steps (Optional)
- Prioritize P0/P1 gaps where missing validation or authorization could allow
  unintended access or unstable behavior.
- Expand docs and Postman collections to match the current route inventory,
  then add tests for each missing endpoint and edge case listed above.
