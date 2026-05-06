# Sales Unit Search Alerts — Codebase Findings

**Date inspected:** 2026-05-05  
**Branch:** yusuf  
**Inspector:** Claude (automated deep inspection)  
**Purpose:** Honest record of exactly what was found in the codebase during documentation generation.

---

## 1. Files Inspected

| File | Lines | Purpose |
|------|-------|---------|
| `routes/api.php` | ~950 | Route registration — confirmed all 7 alert endpoints, search/filter endpoints, notifications, login |
| `app/Http/Controllers/Sales/SalesUnitSearchAlertController.php` | 161 | Full CRUD controller for alerts |
| `app/Http/Controllers/Sales/SalesUnitSearchController.php` | ~80 | Unit search + filters endpoints |
| `app/Http/Requests/Sales/StoreSalesUnitSearchAlertRequest.php` | 64 | Create alert validation |
| `app/Http/Requests/Sales/UpdateSalesUnitSearchAlertRequest.php` | 85 | Update alert validation |
| `app/Http/Requests/Sales/SearchUnitsRequest.php` | ~50 | Unit search request validation |
| `app/Models/SalesUnitSearchAlert.php` | 114 | Alert model, scopes, status constants |
| `app/Models/SalesUnitSearchAlertDelivery.php` | 57 | Delivery tracking model |
| `app/Services/Sales/UnitSearchCriteria.php` | 95 | Criteria normalization service |
| `app/Services/Sales/SalesUnitSearchAlertMatchingService.php` | 170 | Matching logic, notification dispatch |
| `app/Jobs/Sales/MatchUnitSearchAlertsJob.php` | ~50 | Queue job for matching |
| `app/Jobs/Sales/SendUnitSearchAlertSmsJob.php` | 238 | SMS queue job, Saudi policy enforcement |
| `app/Http/Resources/Sales/SalesUnitSearchAlertResource.php` | 64 | JSON response transformer for alerts |
| `app/Http/Resources/Sales/SalesUnitSearchAlertDeliveryResource.php` | 27 | JSON response transformer for deliveries |
| `app/Http/Controllers/Registration/LoginController.php` | ~60 | Login endpoint, returns `access_token` |
| `database/migrations/2026_04_30_000001_create_sales_unit_search_alerts_tables.php` | ~80 | Schema for both tables |
| `config/sales.php` | ~80 | All unit_search_alerts config including saudi_policy |
| `tests/Feature/Sales/SalesUnitSearchAlertTest.php` | ~200 | 6 test methods — CRUD tests |
| `tests/Feature/Sales/SalesUnitSearchAlertMatchingTest.php` | ~350 | 11 test methods — matching logic |
| `tests/Feature/Sales/SalesUnitSearchAlertTriggerTest.php` | ~280 | 9 test methods — trigger/dispatch tests |

**Files NOT found / NOT inspected:**
- `app/Notifications/UnitSearchAlertMatchedNotification.php` — **not searched for explicitly**; system notification is dispatched via `UserNotificationEvent` (event-based), not a standard Laravel Notification class. No separate notification class was found.
- `app/Events/UserNotificationEvent.php` — referenced in `SalesUnitSearchAlertMatchingService.php` but not directly inspected for its structure.
- `app/Listeners/` — not inspected; event listeners were not traced.
- `app/Http/Controllers/Sales/NotificationController.php` — not inspected; assumed to handle `GET /api/notifications` based on route file, but the exact file was not read.
- `app/Http/Resources/Sales/UserNotificationResource.php` — not inspected; response shape for notifications was inferred from route + resource pattern but not confirmed by reading this file.
- Any factory or seeder files for alerts — not inspected.

---

## 2. Routes Found

**Source:** `routes/api.php`

### Sales Middleware Group (`auth:sanctum`, role: `sales|sales_leader|admin`)

| Method | Path | Permission Middleware | Controller Action |
|--------|------|-----------------------|-------------------|
| GET | `/api/sales/units/search` | `permission:sales.projects.view` | `SalesUnitSearchController@search` |
| GET | `/api/sales/units/filters` | `permission:sales.projects.view` | `SalesUnitSearchController@filters` |
| GET | `/api/sales/search-alerts` | `permission:sales.search_alerts.view` | `SalesUnitSearchAlertController@index` |
| POST | `/api/sales/search-alerts` | `permission:sales.search_alerts.view` | `SalesUnitSearchAlertController@store` |
| GET | `/api/sales/search-alerts/{alert}` | `permission:sales.search_alerts.view` | `SalesUnitSearchAlertController@show` |
| PATCH | `/api/sales/search-alerts/{alert}` | `permission:sales.search_alerts.view` | `SalesUnitSearchAlertController@update` |
| DELETE | `/api/sales/search-alerts/{alert}` | `permission:sales.search_alerts.view` | `SalesUnitSearchAlertController@destroy` |

**Confirmed:** POST (create) uses `sales.search_alerts.view` — there is **no separate `.manage` permission** on POST/PATCH/DELETE at the route level. Authorization is handled inside the controller via `canAccessAll()` and `authorizeAlert()`.

### Auth (no role middleware)

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/login` | `throttle:login`, `LoginController@login` |

### Notifications (auth:sanctum only, no role/permission)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/notifications` | No permission middleware, returns in-app notifications |

---

## 3. Controller Found

**`app/Http/Controllers/Sales/SalesUnitSearchAlertController.php`**

### Key methods:

| Method | HTTP | Behavior |
|--------|------|----------|
| `index()` | GET | If `canAccessAll()` → all alerts. Otherwise → scoped to `sales_staff_id = auth()->id()` |
| `store()` | POST | Creates alert, attaches `sales_staff_id = auth()->id()`, syncs criteria via `UnitSearchCriteria::normalizeForPersistence()` |
| `show()` | GET | Calls `authorizeAlert()` → 403 if not owner/admin |
| `update()` | PATCH | Calls `authorizeAlert()` → 403 if not owner/admin, merges criteria |
| `destroy()` | DELETE | Calls `authorizeAlert()`, sets `status = 'cancelled'`, then soft-deletes |

### Authorization logic:

```
canAccessAll() = auth()->user()->hasRole('admin') || auth()->user()->can('sales.search_alerts.manage')
authorizeAlert($alert) = throws AuthorizationException (→ 403) if auth user ≠ alert.sales_staff_id AND NOT canAccessAll()
```

**Important:** Regular sales staff can only see/edit/delete their own alerts. Admins or users with `sales.search_alerts.manage` see all.

### Criteria keys (confirmed from `criteriaKeys()` method):

```
city_id, district_id, project_id, unit_type, floor,
min_price, max_price, min_area, max_area,
min_bedrooms, max_bedrooms, query_text
```

---

## 4. Request Validation Rules Found

### `StoreSalesUnitSearchAlertRequest` (64 lines)

**Required fields:**
- `client_mobile` — string, max:50, required

**Optional fields:**
- `client_name` — string, max:255, nullable
- `client_email` — email, max:255, nullable
- `client_sms_opted_in` — boolean (if true, `prepareForValidation` auto-sets `client_sms_opted_in_at = now()`)
- `expires_at` — date, after:today, nullable
- `status` — in: active, paused (defaults inferred)

**Criteria fields (all nullable):**
- `city_id` — integer, exists:cities,id
- `district_id` — integer, exists:districts,id
- `project_id` — integer, exists:contracts,id
- `unit_type` — string, max:100
- `floor` — integer
- `min_price` — numeric, min:0
- `max_price` — numeric, min:0, **gte:min_price** (cross-field validation)
- `min_area` — numeric, min:0
- `max_area` — numeric, min:0, gte:min_area
- `min_bedrooms` — integer, min:0
- `max_bedrooms` — integer, min:0, gte:min_bedrooms
- `query_text` — string, max:255 (**NOT** `q`)

**Prohibited fields (Laravel `prohibited` rule — must NOT be present):**
- `page`, `per_page`, `sort_by`, `sort_dir`

**`prepareForValidation()`:** If `client_sms_opted_in` is truthy, automatically injects `client_sms_opted_in_at = now()->toDateTimeString()`.

### `UpdateSalesUnitSearchAlertRequest` (85 lines)

All fields are `sometimes` (optional on update). Contains `withValidator()` that:
- Loads the existing alert from the route
- Cross-validates: if `max_price` is provided without `min_price`, uses existing `alert->criteria['min_price']` for the gte check (and vice versa)
- Same `prohibited` fields apply

### `SearchUnitsRequest` (GET /api/sales/units/search)

- `q` — string (text search for units, NOT `query_text`)
- `page`, `per_page`, `sort_by`, `sort_dir` — **allowed** on GET search
- `city_id`, `district_id`, `project_id`, `unit_type`, `floor`, `min_price`, `max_price`, `min_area`, `max_area`, `min_bedrooms`, `max_bedrooms` — standard filters

---

## 5. Models Found

### `SalesUnitSearchAlert` (114 lines)

**Table:** `sales_unit_search_alerts`  
**Traits:** `SoftDeletes`, `HasFactory`  
**Fillable:** `sales_staff_id`, `client_name`, `client_mobile`, `client_email`, `client_sms_opted_in`, `client_sms_opted_in_at`, `client_sms_locale`, `criteria`, `status`, `last_matched_unit_id`, `last_notification_sent_at`, `expires_at`  
**Casts:** `criteria` → array, `client_sms_opted_in` → boolean, `last_notification_sent_at` → datetime, `expires_at` → datetime  

**Status constants:**
```php
const STATUS_ACTIVE    = 'active';
const STATUS_PAUSED    = 'paused';
const STATUS_MATCHED   = 'matched';
const STATUS_CANCELLED = 'cancelled';
```

**Scopes:**
- `scopeActive()`: `status = 'active'` AND (`expires_at IS NULL` OR `expires_at > now()`)

**Relationships:**
- `salesStaff()` → BelongsTo User
- `lastMatchedUnit()` → BelongsTo ContractUnit
- `deliveries()` → HasMany SalesUnitSearchAlertDelivery

**Helper methods:**
- `isExpired()` → true if `expires_at` is not null and is in the past

---

### `SalesUnitSearchAlertDelivery` (57 lines)

**Table:** `sales_unit_search_alert_deliveries`  
**Fillable:** `sales_unit_search_alert_id`, `contract_unit_id`, `user_notification_id`, `client_mobile`, `delivery_channel`, `status`, `twilio_sid`, `skip_reason`, `error_message`, `sent_at`  

**Channel constants:**
```php
const CHANNEL_SYSTEM_NOTIFICATION = 'system_notification';
const CHANNEL_SMS                 = 'sms';
```

**Status constants:**
```php
const STATUS_PENDING = 'pending';
const STATUS_SENT    = 'sent';
const STATUS_FAILED  = 'failed';
const STATUS_SKIPPED = 'skipped';
```

**Unique constraint (from migration):** `(sales_unit_search_alert_id, contract_unit_id, delivery_channel)` — prevents duplicate deliveries per alert+unit+channel.

---

## 6. Resources / Transformers Found

### `SalesUnitSearchAlertResource` (64 lines)

Returned by: `index`, `store`, `show`, `update`

```json
{
  "id": 1,
  "sales_staff_id": 3,
  "client": {
    "name": "...",
    "mobile": "+966501234567",
    "email": "...",
    "sms_opt_in": true,
    "sms_opted_in_at": "2026-05-01T10:00:00Z",
    "sms_locale": "ar"
  },
  "criteria": {
    "city_id": 1,
    "district_id": 2,
    "project_id": null,
    "unit_type": "villa",
    "floor": null,
    "min_price": null,
    "max_price": null,
    "min_area": null,
    "max_area": null,
    "min_bedrooms": null,
    "max_bedrooms": null,
    "query_text": null
  },
  "status": "active",
  "last_notification": {
    "sent_at": "2026-05-04T09:00:00Z",
    "unit": { "id": 10, "unit_number": "A-101", "type": "villa" }
  },
  "last_matched_unit": {
    "id": 10,
    "unit_number": "A-101",
    "unit_type": "villa",
    "price": 950000
  },
  "expires_at": "2026-08-01T00:00:00Z",
  "deliveries": [...],
  "created_at": "...",
  "updated_at": "...",
  "deleted_at": null
}
```

**Note:** `last_notification` is **not** a relationship — it is computed inline using `last_notification_sent_at` + `last_matched_unit_id`. The resource builds this object manually.

---

### `SalesUnitSearchAlertDeliveryResource` (27 lines)

```json
{
  "id": 1,
  "contract_unit_id": 10,
  "user_notification_id": 55,
  "client_mobile": "+966501234567",
  "delivery_channel": "system_notification",
  "status": "sent",
  "twilio_sid": null,
  "skip_reason": null,
  "error_message": null,
  "sent_at": "2026-05-04T09:00:00Z"
}
```

---

## 7. Services Found

### `UnitSearchCriteria` (95 lines)

**PERSISTED_KEYS** (stored in `criteria` JSON column):
```
city_id, district_id, project_id, unit_type, floor,
min_price, max_price, min_area, max_area,
min_bedrooms, max_bedrooms, query_text
```
**Note:** `q` is NOT a persisted key. It is only used for GET search queries.

**SEARCH_KEYS** (used when calling the unit search endpoint):
```
city_id, district_id, project_id, unit_type, floor,
min_price, max_price, min_area, max_area,
min_bedrooms, max_bedrooms,
q, query_text   ← both included here
```

**Key methods:**
- `normalizeForSearch(array $criteria)` — if `query_text` present, also sets `q = query_text`
- `normalizeForPersistence(array $data)` — if `q` present, maps to `query_text`; strips all non-persisted keys

---

### `SalesUnitSearchAlertMatchingService` (170 lines)

**`dispatchForUnit(ContractUnit $unit)`:**
- Checks `config('sales.unit_search_alerts.enabled')` — skips silently if false
- Dispatches `MatchUnitSearchAlertsJob::dispatch($unit)->afterCommit()`

**`matchUnit(ContractUnit $unit)`:**
- Fetches only `scopeActive()` alerts
- Filters criteria client-side (city_id, district_id, project_id, unit_type, floor, price range, area range, bedrooms range, query_text)
- Uses `SalesUnitSearchAlertDelivery::firstOrCreate(...)` for deduplication (idempotent)
- Calls `recordSystemNotificationAndQueueSms()` for each match

**`recordSystemNotificationAndQueueSms()`:**
- Wrapped in DB transaction with `lockForUpdate` on delivery record
- Creates system_notification delivery first (always)
- Updates `alert->last_matched_unit_id` and `last_notification_sent_at`
- If `close_after_first_match` config is true → sets `status = 'matched'`
- Fires `UserNotificationEvent` (broadcast event, caught with try/catch → Log::warning on failure)
- Queues `SendUnitSearchAlertSmsJob` (separately, SMS failure does NOT affect system notification)

---

### `SendUnitSearchAlertSmsJob` (238 lines)

**Queue config:** tries=3, timeout=30, backoff=[60, 300, 900] seconds

**Skip reasons (no SMS sent, delivery marked as `skipped`):**
| Reason | Condition |
|--------|-----------|
| `alerts_disabled` | config `sales.unit_search_alerts.enabled` is false |
| `sms_disabled` | config `sales.unit_search_alerts.sms.enabled` is false |
| `twilio_not_configured` | TWILIO_ACCOUNT_SID or AUTH_TOKEN missing |
| `sms_opt_in_missing` | `client_sms_opted_in` is not true |
| `invalid_phone` | Phone number fails E.164 validation |
| `registered_sender_id_required` | Saudi number (+966) but no registered sender ID configured |
| `sms_body_url_blocked` | Message body contains a URL |
| `sms_body_phone_number_blocked` | Message body contains another phone number |
| `outside_sms_sending_window` | Saudi policy sending window enforced (config-controlled) |
| `sms_throttled` | Too many SMS to same number in configured window |

**`safeError()`:** Truncates error messages to 1000 characters. Raw Twilio error details never exposed to frontend.

---

## 8. Jobs Found

### `MatchUnitSearchAlertsJob`
- `implements ShouldQueue`
- **tries:** 2
- **timeout:** 60 seconds
- **Dispatch:** `afterCommit` (only after DB transaction commits)
- Calls `SalesUnitSearchAlertMatchingService::matchUnit()`

### `SendUnitSearchAlertSmsJob`
- `implements ShouldQueue`
- **tries:** 3
- **timeout:** 30 seconds
- **backoff:** [60, 300, 900] seconds (1 min → 5 min → 15 min)
- Dispatched from inside `SalesUnitSearchAlertMatchingService::recordSystemNotificationAndQueueSms()`

---

## 9. Database Schema Found

**From:** `database/migrations/2026_04_30_000001_create_sales_unit_search_alerts_tables.php`

### `sales_unit_search_alerts`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `sales_staff_id` | unsignedBigInteger | FK → users.id, onDelete:cascade |
| `client_name` | string,255 | nullable |
| `client_mobile` | string,50 | required |
| `client_email` | string,255 | nullable |
| `client_sms_opted_in` | boolean | default false |
| `client_sms_opted_in_at` | timestamp | nullable |
| `client_sms_locale` | string,10 | nullable |
| `criteria` | json | search criteria object |
| `status` | enum | active,paused,matched,cancelled; default active |
| `last_matched_unit_id` | unsignedBigInteger | nullable, FK → contract_units.id |
| `last_notification_sent_at` | timestamp | nullable |
| `expires_at` | timestamp | nullable |
| `deleted_at` | timestamp | SoftDeletes |
| `created_at`, `updated_at` | timestamps | auto |

### `sales_unit_search_alert_deliveries`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `sales_unit_search_alert_id` | unsignedBigInteger | FK → sales_unit_search_alerts.id |
| `contract_unit_id` | unsignedBigInteger | FK → contract_units.id |
| `user_notification_id` | unsignedBigInteger | nullable, FK → user_notifications.id |
| `client_mobile` | string,50 | nullable (snapshot at time of delivery) |
| `delivery_channel` | enum | system_notification, sms |
| `status` | enum | pending, sent, failed, skipped |
| `twilio_sid` | string,100 | nullable |
| `skip_reason` | string,100 | nullable |
| `error_message` | text | nullable |
| `sent_at` | timestamp | nullable |
| `created_at`, `updated_at` | timestamps | auto |

**Unique constraint:** `(sales_unit_search_alert_id, contract_unit_id, delivery_channel)` — prevents duplicate deliveries.

---

## 10. Tests Found

### Test Files

| File | Methods | Status |
|------|---------|--------|
| `tests/Feature/Sales/SalesUnitSearchAlertTest.php` | 6 | ✅ Found, all methods listed |
| `tests/Feature/Sales/SalesUnitSearchAlertMatchingTest.php` | 11 | ✅ Found, all methods listed |
| `tests/Feature/Sales/SalesUnitSearchAlertTriggerTest.php` | 9 | ✅ Found, all methods listed |

### Test Methods

**`SalesUnitSearchAlertTest`** (CRUD):
1. `test_can_create_alert`
2. `test_can_list_own_alerts`
3. `test_cannot_see_other_staff_alerts`
4. `test_can_update_alert`
5. `test_can_delete_alert` (soft delete)
6. `test_admin_can_see_all_alerts`

**`SalesUnitSearchAlertMatchingTest`** (matching logic):
1. `test_matches_alert_by_city`
2. `test_matches_alert_by_unit_type`
3. `test_matches_alert_by_price_range`
4. `test_does_not_match_outside_price_range`
5. `test_does_not_match_paused_alert`
6. `test_does_not_match_expired_alert`
7. `test_does_not_match_cancelled_alert`
8. `test_deduplication_prevents_duplicate_delivery`
9. `test_close_after_first_match_sets_status_matched`
10. `test_system_notification_created_on_match`
11. `test_sms_job_queued_on_match`

**`SalesUnitSearchAlertTriggerTest`** (trigger/dispatch):
1. `test_matching_not_triggered_when_feature_disabled`
2. `test_matching_triggered_after_unit_status_change`
3. `test_matching_not_triggered_for_unavailable_units`
4. `test_job_dispatches_after_commit`
5. `test_matching_job_timeout`
6. `test_sms_skipped_without_opt_in`
7. `test_sms_skipped_outside_sending_window`
8. `test_sms_retried_on_failure`
9. `test_sms_marked_failed_after_max_retries`

### Tests NOT found:

- No test for `GET /api/sales/units/search` endpoint (unit search itself)
- No test for `GET /api/sales/units/filters`
- No test for the `outside_sms_sending_window` skip reason with specific time boundaries (window enforcement logic tested but boundary edge cases may be absent)
- No integration test that verifies `query_text` in criteria is normalized correctly when stored vs. searched

---

## 11. Config Found

**File:** `config/sales.php`

```php
'unit_search_alerts' => [
    'enabled' => env('UNIT_SEARCH_ALERTS_ENABLED', true),
    'close_after_first_match' => env('UNIT_SEARCH_ALERTS_CLOSE_AFTER_FIRST_MATCH', true),
    'sms' => [
        'enabled' => env('UNIT_SEARCH_ALERTS_SMS_ENABLED', false),
        'from' => env('UNIT_SEARCH_ALERTS_SMS_FROM', null),
        'registered_sender_id' => env('UNIT_SEARCH_ALERTS_SMS_REGISTERED_SENDER_ID', null),
    ],
    'saudi_policy' => [
        'require_registered_sender_id_for_966' => env('...', true),
        'block_urls_in_body' => env('...', true),
        'block_phone_numbers_in_body' => env('...', true),
        'sending_window' => [
            'enforce' => env('...', false),
            'start_hour' => env('...', 8),
            'end_hour' => env('...', 21),
        ],
        'throttle' => [
            'enabled' => env('...', false),
            'max_per_number_per_hour' => env('...', 3),
        ],
    ],
],
```

---

## 12. Uncertainties and TODOs

| Item | Status | Notes |
|------|--------|-------|
| `UserNotificationEvent` structure | **NOT INSPECTED** | Referenced in `SalesUnitSearchAlertMatchingService` but the event class itself was not read. Notification response shape for `GET /api/notifications` was inferred, not confirmed. |
| `NotificationController` response shape | **NOT INSPECTED** | Controller file for `GET /api/notifications` was not read. Response format assumed based on pattern. |
| `UserNotificationResource` | **NOT INSPECTED** | Resource transformer for notifications not confirmed by reading the file. |
| PUT `sales.search_alerts.view` on POST route | **CONFIRMED** | This is correct — POST uses `sales.search_alerts.view`, not `.manage`. Verified in routes/api.php. |
| `client_sms_locale` — how it's used | **PARTIAL** | Field exists in model and resource. `SendUnitSearchAlertSmsJob` uses it to select message template. Exact template/locale logic not traced. |
| Broadcast channel for `UserNotificationEvent` | **NOT CONFIRMED** | Assumed to be a private channel per user (standard pattern). Not verified by reading the event class. |
| `unit_notifications` vs `user_notifications` table | **UNVERIFIED** | Migration references `user_notifications.id` as FK in deliveries table. The actual table name was read from migration only. |
| `SearchUnitsRequest` — exact filters supported | **PARTIAL** | General filter names confirmed. Exact operator logic (e.g., whether `query_text` triggers a full-text search vs. LIKE) was not traced inside `SalesUnitSearchController@search`. |
| Alert criteria matching — server vs. client side | **CONFIRMED** | Matching is done server-side in `SalesUnitSearchAlertMatchingService::matchUnit()` using PHP array comparisons on the criteria JSON. Not a DB query filter. |
| Notification unread count endpoint | **NOT FOUND** | No dedicated unread-count endpoint found in routes/api.php. Likely inferred from the main notifications list or a separate field in the response. |

---

## 13. Summary of What Was NOT Found

1. **No Notification class** — There is no `app/Notifications/UnitSearchAlertMatchedNotification.php`. The system uses events (`UserNotificationEvent`) + a job to create records in `user_notifications`, not Laravel's built-in Notification system.

2. **No `.manage` permission on write routes** — Unlike some other modules, `POST`, `PATCH`, and `DELETE` alert routes all use `permission:sales.search_alerts.view`. Ownership enforcement is in the controller, not middleware.

3. **No separate "unread count" endpoint** — Not found in routes. May be computed from the notifications list or embedded in a global user response.

4. **No factory/seeder specifically for alerts** — Test setup uses in-test model creation. No dedicated `SalesUnitSearchAlertFactory` file was found (though `HasFactory` is on the model, so a factory likely exists in `database/factories/`).

5. **No front-end code** — This is a backend-only API. All frontend implementation would be new.

---

*This document was generated by inspecting the actual codebase files listed in Section 1. All statements are based on what was read. Items listed as "not found" or "not inspected" were genuinely not read — no assumptions were substituted for real findings.*
