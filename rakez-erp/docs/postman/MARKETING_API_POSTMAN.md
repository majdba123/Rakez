# Marketing API — Postman collection

This folder contains a **repo-grounded** Postman collection for the Laravel routes under `Route::prefix('marketing')` in `routes/api.php`, plus related **`ads`** routes (marketing role) and **`sales`** routes used for cross-module marketing tasks. The default API base is **`https://rakez.com.sa/api`** (configurable via `baseUrl`).

## Files

| File | Purpose |
|------|---------|
| `collections/Rakez-Marketing-API.postman_collection.json` | Collection v2.1 |
| `environments/Rakez-Marketing-Production.postman_environment.json` | Variables for production base URL and IDs |
| `_generate_marketing_collection.py` | Regenerates the collection JSON after route changes |

## Import

1. Postman → **Import** → select `collections/Rakez-Marketing-API.postman_collection.json`.
2. Optional: import `environments/Rakez-Marketing-Production.postman_environment.json` and select it as the active environment.
3. Set `userEmail` / `userPassword` (or paste a Sanctum token into `token`). Run **Auth → Login**; the test script saves `access_token` into the collection variable `token` when the response is `200`.

## Authentication

- **Login:** `POST /api/login` — `LoginController@login`; body `email`, `password` (see `LoginRequest`). Response includes `access_token` (Sanctum).
- **Marketing routes:** `auth:sanctum`, `role:marketing|admin`, plus per-route `permission:...` middleware (see each request description).
- **Logout:** `POST /api/logout` (authenticated) — revokes tokens.

Bearer header: `Authorization: Bearer {{token}}`.

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
