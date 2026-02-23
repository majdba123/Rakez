# Full API Missing Analysis & Scenario Catalog

Date: 2026-02-01
Sources reviewed: `routes/api.php`, `API_PERMISSIONS_MAPPING.md`, FormRequest classes, controller validations, and existing docs in `docs/`.

---

## 1) Inventory Summary (routes/api.php)

- Auth & Session: 3 endpoints (+ broadcasting auth).
- Contracts (user + admin/PM): 8 endpoints.
- Project Management (second party, units, departments, PM dashboard): 19 endpoints.
- Editor: 5 endpoints.
- Sales (core + leader + waiting list): 28 endpoints.
- Marketing: 29 endpoints.
- Sales Analytics + Commissions + Deposits: 36 endpoints.
- Admin (employees, contracts, notifications, sales assignment): 15 endpoints.
- User Notifications: 4 endpoints.
- Exclusive Projects: 7 endpoints.
- AI Assistant: 5 endpoints.
- Storage file access: 1 endpoint.

Total: ~160 API routes (including broadcasting auth and storage).

---

## 2) Documentation & Postman Gaps (current repo state)

### Missing coverage in existing Postman collections
- Current collections cover Sales/Marketing/AI only and do not include:
  - Contracts & contract info
  - Project management (second party data, units, boards/photography)
  - Editor montage routes
  - Admin employee management and admin notifications
  - Exclusive projects
  - Sales analytics, commissions, deposits (finance)
  - User notifications
  - Broadcasting auth and storage endpoint

### Route mismatches / outdated docs
- `POSTMAN_EXAMPLES.md` uses:
  - `PUT /api/contracts/{id}/update` but actual route is `PUT /api/contracts/update/{id}`.
  - `POST /api/contracts/{id}/store-info` but actual route is `POST /api/contracts/store/info/{id}`.
- `SECOND_PARTY_DATA_API.md` uses `/api/contracts/{id}/second-party-data` and `/api/second-party-data/{id}/units` which do not exist in `routes/api.php`.
- `API_EXAMPLES_SALES.md` shows target update using `current_value` but `UpdateTargetRequest` only accepts `status`.
- Postman sales attendance uses `date/shift`, while `StoreAttendanceScheduleRequest` uses `schedule_date`, `start_time`, `end_time`.
- Reservation cancel request body in Postman/docs uses `reason`, while controller expects `cancellation_reason`.
- Sales reservation action `action_type` allowed values are `lead_acquisition/persuasion/closing` (plus Arabic terms); sample uses `call`.
- Deposit update route uses inline validation (amount/payment_method/client_name/payment_date/notes) while `UpdateDepositRequest` includes `commission_source`; mismatch should be clarified.

### Response envelope inconsistencies to note in frontend
- Many endpoints return `{ success, data, message }`, but some return only `data` or `message` (e.g., RegisterController resources, NotificationController).
- File download endpoints (`/sales/reservations/{id}/voucher`, `/exclusive-projects/{id}/export`, `/storage/{path}`) return binary responses, not JSON.

---

## 3) Missing Scenario Coverage (by module)

### Contracts
- Create contract with duplicate `developer_number`.
- Update contract with invalid/missing units array.
- Store contract info when status is not `approved`.
- Store contract info when info already exists.

### Project Management
- Second party data create when already exists.
- Second party data update when contract has no second party record.
- Contract units CSV upload with invalid file or duplicate upload.
- Contract units CRUD with invalid unit status.
- Boards/Photography/Montage update with invalid URLs.

### Editor
- Editor access to contracts outside permissions.
- Montage update with invalid data.

### Sales
- Reservation double-booking prevention.
- Reservation cancel with missing/invalid reason.
- Voucher download when file path missing or file not found.
- Reservation action types outside allowed list.
- Attendance schedule overlap validation.
- Waiting list convert when unit already reserved.
- Emergency contacts update by non-leader.

### Marketing
- Expected sales with invalid conversion rate.
- Task update vs status update paths (PUT vs PATCH) inconsistencies.
- Lead update with invalid status transitions.
- Report endpoints with empty datasets.

### Sales Analytics / Commissions / Deposits
- Commission distributions sum != 100%.
- Commission approval flow without approved distributions.
- External marketer missing bank account.
- Mark commission paid when not approved.
- Deposit refund for buyer-source deposits (should be blocked).
- Bulk confirm with mixed valid/invalid IDs.
- Can-refund false reasons surfaced to UI.

### Admin / Notifications
- Add employee with invalid `type` or role.
- Update employee with duplicate email/identity.
- Send notification to invalid user.
- User marking notification not owned by them.

### Exclusive Projects
- Reject without reason (required by request).
- Complete contract without prior approval.
- Export when PDF not generated.

### AI Assistant
- Rate limit and daily budget exceeded responses.
- Disabled assistant (`ai_assistant.enabled=false`).
- Deleting another user’s session.

---

## 4) Scenario Catalog (Full)

Use these as baseline E2E scenarios for frontend QA and API regression:

1. Contract lifecycle: create -> admin approve -> store contract info -> status becomes completed.
2. Second party data lifecycle: store -> show -> update -> query by email.
3. Units lifecycle: CSV upload -> list -> update -> delete.
4. Departments lifecycle: boards/photography/montage create -> update -> show.
5. Sales reservation flow: context -> create -> confirm -> voucher download.
6. Sales cancellation flow: cancel -> deposits refund checks.
7. Waiting list flow: create -> convert -> reservation created.
8. Marketing flow: calculate budget -> developer plan -> employee plan -> tasks -> reports.
9. Commission flow: create -> add distributions -> approve distributions -> approve commission -> mark paid.
10. Deposit flow: create -> confirm receipt -> mark received -> refund -> claim file.
11. Exclusive projects: request -> approve/reject -> complete contract -> export.
12. Notifications: admin send -> user fetch -> mark read.
13. AI assistant: ask -> chat -> list conversations -> delete conversation.
14. Broadcasting: auth channel -> receive notifications.

---

## 5) Open Questions / Clarifications

- Confirm whether contract info update should be exposed in routes (controller has update method, route not present).
- Confirm whether deposit update should accept `commission_source` (request class vs controller mismatch).
- Confirm action_type enum for sales reservation actions and preferred Arabic/English values.
- Confirm expected response envelope standardization (success/data/message vs resource-only).

---

## 6) Recommendations

- Align docs and Postman with actual routes/fields.
- Add missing validation and authorization tests for PM, Admin, Exclusive Projects, and Notifications.
- Document file download endpoints and response types explicitly in frontend guide.
- Consider adding collection-level auth in Postman for easier testing.
