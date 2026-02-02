# Postman Collection Audit Report
Collection: `docs\postman\collections\Rakez ERP - Frontend API _Sales_ Marketing_ AI_.postman_collection.json`
Environment: `docs\postman\environments\Rakez ERP - Local.postman_environment.json`
Date: 2026-02-01
## Summary
- **Score:** 100/100
- **Total requests:** 161
- **Structural issues:** 0
- **URL mismatches:** 0
- **Duplicate names (per folder):** 0
- **Duplicate method+path:** 0

### Top Issues
- No critical structural issues detected.

## Variable Resolution

### Collection vs Environment collisions

- base_url
- commission_id
- contract_id
- contract_unit_id
- deposit_id
- distribution_id
- exclusive_id
- file_path
- lead_id
- marketing_project_id
- notification_id
- plan_id
- project_id
- reservation_id
- session_id
- setting_key
- target_id
- task_id
- token
- unit_id
- user_id
- waiting_id

### Variables used in requests but missing from environment

- None

### Variables used in requests but missing from both collection and environment

- None

## URL Structure Validation

- No raw/host/path mismatches detected.

## Duplicate Endpoints
- None

## Authorization & Headers
- Requests with per-request Authorization header: 160/161
- Recommendation: move to collection-level Bearer auth and override login as `noauth`.
- Endpoints using form-data (should NOT inherit JSON Content-Type):
  - Project Management / Units Upload CSV
  - Admin / Employees Add
  - Admin / Employees Update
- Accept header is missing in most requests; consider adding `Accept: application/json` at collection-level.

## Binary Endpoints (File Downloads)
- Sales / Reservation Voucher
- Exclusive Projects / Exclusive Export
- Marketing / Marketing Report Export
- Storage / Get File

Notes: use Postman 'Send and Download'. Avoid `pm.response.json()` in tests for these.

## Scripts to Add (Tests)
### Login (extract token)
```javascript
let body;
try { body = pm.response.json(); } catch (e) { body = null; }
if (!body) return;
const data = body.data ?? body;
const token = (data && (data.token || data.access_token)) || body.token || body.access_token;
if (token) {
  pm.environment.set('token', token);
  pm.environment.set('active_token', token);
}
```
### Create requests (capture id)
```javascript
let body;
try { body = pm.response.json(); } catch (e) { body = null; }
if (!body) return;
const data = body.data ?? body;
const id = (data && (data.id ?? data[envKey])) ?? body[envKey];
if (id) { pm.environment.set(envKey, id); }
```

## Known Mismatch Risks (Flagged only)
- cancellation_reason present: True
- reason present: False
- action_type present: True
- attendance fields present (schedule_date/start_time/end_time): True
- deposit update uses commission_source: False

## Recommended Folder Structure Improvements
- Split 'Sales Analytics & Finance' into separate folders: `Sales Analytics`, `Commissions`, `Deposits`.
- Consider grouping `Project Management` and `Editor` under a single `Operations` section.
- Consider adding `Contracts` subfolder for Admin within `Admin` to avoid mixing.

## Needs Confirmation
- Should collection-level auth use `token` or `active_token`? (Patch uses `active_token`).
- Any public endpoints beyond `/api/login`? (Only login assumed public).
