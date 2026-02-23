# API Verification: Developers & Accounting

## Developers API

**Endpoint:** `GET /api/developers`

**Auth:** `auth:sanctum` + role `project_management|admin|accounting`. Policy: `viewAny(Contract)` (requires `contracts.view` or `contracts.view_all`). The accounting role has `contracts.view_all` so accountants can access the developers list.

**Query params (optional):**
- `search` – filter by developer name or number (e.g. "ابحث عن مطور بالاسم أو اسم الممثل")
- `per_page` – default 15, max 100
- `page` – default 1

**Response (200):**
```json
{
  "success": true,
  "message": "تم جلب قائمة المطورين بنجاح",
  "data": [
    {
      "developer_number": "...",
      "developer_name": "...",
      "projects_count": 2,
      "projects": [{ "id", "project_name", "status", "city", "district", "units_count", "created_at" }],
      "units_count": 25,
      "teams": [{ "id", "name" }]
    }
  ],
  "meta": { "total", "per_page", "current_page", "last_page" }
}
```

**When list is empty:** `message` is `"لا يوجد مطورين مطابقين للبحث"` and `data` is `[]`.

**Why the Developers Management page might show "No matching developers":**
1. **Wrong API base URL** – Frontend (e.g. localhost:8080) must call the **backend** base URL. If the Laravel API runs on `http://localhost:8000`, the frontend must request `GET http://localhost:8000/api/developers` (or whatever the backend URL is), not `http://localhost:8080/api/developers`.
2. **User role** – Logged-in user must have role `project_management`, `admin`, or `accounting`. Otherwise the route returns **403**.
3. **Permission** – User must have `contracts.view` or `contracts.view_all`. Otherwise the controller returns **403**.
4. **No data** – If the user has no contracts (and no `contracts.view_all`), or the database has no contracts, the list is empty and the API returns 200 with `data: []` and message "لا يوجد مطورين مطابقين للبحث".
5. **Search** – If the frontend sends a `search` param that matches no developer, the same empty response is returned.

**Seeding:** Run `php artisan db:seed` (or at least `ContractsSeeder`) so that contracts with `developer_name` / `developer_number` exist. `ContractsSeeder` uses a fixed list of 8 developers so multiple projects share the same developer.

---

## Accounting API

**Base path:** `GET|POST|PUT|POST ... /api/accounting/*`

**Auth:** `auth:sanctum` + role `accounting|admin`. Individual routes use permissions (e.g. `accounting.dashboard.view`, `accounting.sold-units.view`).

**Registered routes (26):**
- **Dashboard:** `GET api/accounting/dashboard`
- **Notifications:** `GET api/accounting/notifications`, `POST .../notifications/{id}/read`, `POST .../notifications/read-all`
- **Sold units & commissions:** `GET api/accounting/marketers`, `GET api/accounting/sold-units`, `GET api/accounting/sold-units/{id}`, `POST .../sold-units/{id}/commission`, `PUT .../commissions/{id}/distributions`, approve/reject/confirm distribution endpoints, `GET .../commissions/{id}/summary`
- **Deposits:** `GET api/accounting/deposits/pending`, `POST .../deposits/{id}/confirm`, `GET .../deposits/follow-up`, `POST .../deposits/{id}/refund`, `POST .../deposits/claim-file/{reservationId}`
- **Salaries:** `GET api/accounting/salaries`, `GET api/accounting/salaries/{userId}`, `POST .../salaries/{userId}/distribute`, approve/paid distribution endpoints
- **Confirmations:** `GET api/accounting/pending-confirmations`, `POST api/accounting/confirm/{reservationId}`, `GET api/accounting/confirmations/history`

All accounting controllers return JSON with `success`, `message`, and `data` where applicable. No changes required for accounting routes; they are correctly registered and protected.

---

## Quick checks

| Check | Command / action |
|-------|-------------------|
| Developers route | `php artisan route:list --path=developers` → `GET api/developers` |
| Accounting routes | `php artisan route:list --path=accounting` → 26 routes |
| Backend URL | Ensure frontend env (e.g. `VITE_API_URL` / `REACT_APP_API_URL`) points to the Laravel API base (e.g. `http://localhost:8000`) |
| Seed data | `php artisan db:seed` then log in as user with role `project_management` or `admin` and `contracts.view_all` |
