# Marketing API — Postman collection

This folder contains a **repo-grounded** Postman collection for the Laravel routes under `Route::prefix('marketing')` in `routes/api.php`, plus related **`ads`** routes (marketing role) and **`sales`** routes used for cross-module marketing tasks. The default API base is **`https://rakez.com.sa/api`** (configurable via `baseUrl`).

## Files

| File | Purpose |
|------|---------|
| `collections/Rakez-Marketing-API.postman_collection.json` | Collection v2.1 (strict JSON, UTF-8) |
| `../../postman/Rakez-Marketing-API.json` | **Same collection** — short path, `.json` only (easiest import) |
| `environments/Rakez-Marketing-Production.postman_environment.json` | Variables for production base URL and IDs |
| `_generate_marketing_collection.py` | Regenerates both JSON files after route changes |

## Import

1. Postman → **Import** → **file** → choose either:
   - `rakez-erp/postman/Rakez-Marketing-API.json`, or  
   - `rakez-erp/docs/postman/collections/Rakez-Marketing-API.postman_collection.json`  
   Both are identical **JSON** (Collection v2.1.0).
2. Optional: import `environments/Rakez-Marketing-Production.postman_environment.json` and select it as the active environment.
3. Set `userEmail` / `userPassword` (or paste a Sanctum token into `token`). Run **Auth → Login**; the test script saves `access_token` into the collection variable `token` when the response is `200`.

## Authentication

- **Login:** `POST /api/login` — `LoginController@login`; body `email`, `password` (see `LoginRequest`). Response includes `access_token` (Sanctum).
- **Marketing routes:** `auth:sanctum`, `role:marketing|admin`, plus per-route `permission:...` middleware (see each request description).
- **Logout:** `POST /api/logout` (authenticated) — revokes tokens.

Bearer header: `Authorization: Bearer {{token}}`.

## Pricing / response contract (2026)

- **`POST marketing/projects/calculate-budget`:** JSON includes `pricing_basis` (`source`, `total_unit_price`, `commission_base_amount`, unit counts, averages, `avg_property_value_stored`, `override_applied`). Money fields in `data` are numeric. Commission base priority: `total_unit_price_override` / legacy `unit_price` → sum of **available** unit prices → stored `avg_property_value`.
- **`GET marketing/developer-plans/{contractId}`:** `contract` includes `pricing_basis` and `total_unit_price`; `plan` is a **serialized** object (duplicate `raw_plan` removed). `total_budget` is numeric; `total_budget_display` is formatted text. `expected_impressions` / `expected_clicks` are integers; human-readable strings use `*_display_*` keys. `platforms` is always an array.

Production will match only **after** this backend revision is deployed; compare responses to these rules when verifying.

## Live verification (automated probe)

From this environment, `curl` to **`https://rakez.com.sa/api/login`** returned **nginx 404** (HTML), while **`https://rakez.com.sa/`** returned **200** (SPA). The public site CSP references **`https://api.rakez.com.sa`** for API calls — your production API host may differ from the apex `/api` path. **Set `baseUrl` accordingly** (e.g. if the API is served from `api.rakez.com.sa` with path `/api`).

Because of that, **no marketing endpoint was live-verified with a 200 JSON** in this pass. All requests are documented as **code-verified** from `routes/api.php` and controllers/FormRequests, with mutating calls **not executed** against production.

## Safety

- **GET** (and read-only exports): safe to call in production **only** with a legitimate marketing user and only when you accept logging/traffic impact.
- **POST/PUT/PATCH** and GETs that **write** (e.g. `GET marketing/expected-sales/{projectId}` triggers `createOrUpdateExpectedBookings`): use **staging** or **approved test data**; example bodies in the collection are **illustrative** (`0` placeholders).

## Route drift / not in `api.php`

- `MarketingBudgetDistributionController` exists in code but has **no registered routes** in `routes/api.php` on the branch used for this collection.
- `MarketingReportController::exportDeveloperPlan` exists but is **not wired** in `routes/api.php` (only `exportPlan` for employee plans is listed).
- `tests/Feature/Marketing/ExpectedSalesRoutesTest.php` references `POST /api/marketing/expected-sales` and collection GETs; **current** `routes/api.php` only registers `GET marketing/expected-sales/{projectId}` and `PUT marketing/settings/conversion-rate`. Treat tests as potentially out of sync until routes are reconciled.

## Regenerating the collection

```bash
cd rakez-erp
python docs/postman/_generate_marketing_collection.py
```
