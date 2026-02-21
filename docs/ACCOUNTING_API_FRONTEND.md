# Accounting API — Frontend Fetch & Request Reference

**Base URL:** `/api`  
**Auth:** All requests require `Authorization: Bearer <token>` (Laravel Sanctum).  
**Roles:** `accounting` or `admin`.

**Response shape (success):** `{ success: true, message: string, data?: T, meta?: PaginationMeta }`  
**Response shape (error):** `{ success: false, message: string, errors?: Record<string, string[]> }`  
**Pagination meta:** `{ total, per_page, current_page, last_page }`

---

## 1. Dashboard

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/accounting/dashboard` | Get dashboard metrics |

**Query (optional):**
- `from_date` — date (YYYY-MM-DD)
- `to_date` — date, must be >= from_date

**Frontend fetch example:**
```js
const params = new URLSearchParams({ from_date: '2025-01-01', to_date: '2025-02-17' });
const res = await fetch(`/api/accounting/dashboard?${params}`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
const json = await res.json(); // { success, message, data }
```

---

## 2. Notifications

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/accounting/notifications` | List notifications (paginated) |
| POST | `/api/accounting/notifications/{id}/read` | Mark one as read |
| POST | `/api/accounting/notifications/read-all` | Mark all as read |

**GET query (optional):**
- `from_date` — date
- `to_date` — date
- `status` — `pending` \| `read`
- `type` — `unit_reserved` \| `deposit_received` \| `unit_vacated` \| `reservation_cancelled` \| `commission_confirmed` \| `commission_received`
- `per_page` — 1–100 (default from backend)

**Frontend fetch examples:**
```js
// List
const res = await fetch(`/api/accounting/notifications?per_page=20&page=1`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

// Mark read
await fetch(`/api/accounting/notifications/${id}/read`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

// Read all
await fetch('/api/accounting/notifications/read-all', {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## 3. Sold Units & Commissions

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/accounting/marketers` | List marketers for commission dropdown |
| GET | `/api/accounting/sold-units` | List sold units with commission info (paginated) |
| GET | `/api/accounting/sold-units/{id}` | Single unit + commission breakdown + available_marketers |
| POST | `/api/accounting/sold-units/{id}/commission` | Create manual commission |
| PUT | `/api/accounting/commissions/{id}/distributions` | Update commission distributions |
| POST | `/api/accounting/commissions/{id}/distributions/{distId}/approve` | Approve distribution |
| POST | `/api/accounting/commissions/{id}/distributions/{distId}/reject` | Reject distribution |
| GET | `/api/accounting/commissions/{id}/summary` | Commission summary |
| POST | `/api/accounting/commissions/{id}/distributions/{distId}/confirm` | Confirm payment |

**GET sold-units query (optional):**
- `project_id` — exists:contracts,id
- `from_date`, `to_date` — date
- `commission_source` — `owner` \| `buyer`
- `commission_status` — `pending` \| `approved` \| `paid`
- `per_page` — 1–100

**POST sold-units/{id}/commission body:**
```json
{
  "contract_unit_id": 1,
  "final_selling_price": 500000,
  "commission_percentage": 3,
  "commission_source": "owner",
  "team_responsible": "optional string",
  "marketing_expenses": 0,
  "bank_fees": 0
}
```

**PUT commissions/{id}/distributions body:**
```json
{
  "distributions": [
    {
      "type": "lead_generation|persuasion|closing|team_leader|sales_manager|project_manager|external_marketer|other",
      "percentage": 50,
      "user_id": 1,
      "external_name": "optional",
      "bank_account": "optional",
      "notes": "optional"
    }
  ]
}
```

**Frontend fetch examples:**
```js
// List sold units
const q = new URLSearchParams({ commission_status: 'pending', per_page: 15 });
const res = await fetch(`/api/accounting/sold-units?${q}`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

// Create commission
await fetch(`/api/accounting/sold-units/${reservationId}/commission`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    contract_unit_id,
    final_selling_price,
    commission_percentage,
    commission_source: 'owner',
  }),
});

// Update distributions
await fetch(`/api/accounting/commissions/${commissionId}/distributions`, {
  method: 'PUT',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ distributions: [...] }),
});
```

---

## 4. Deposits

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/accounting/deposits/pending` | Pending deposits (paginated) |
| POST | `/api/accounting/deposits/{id}/confirm` | Confirm deposit receipt |
| GET | `/api/accounting/deposits/follow-up` | Follow-up list (paginated) |
| POST | `/api/accounting/deposits/{id}/refund` | Process refund |
| POST | `/api/accounting/deposits/claim-file/{reservationId}` | Generate claim file for reservation |

**GET pending / follow-up query (optional):**
- `project_id` — exists:contracts,id
- `from_date`, `to_date` — date
- `payment_method` — string (pending only)
- `commission_source` — `owner` \| `buyer` (both)
- `per_page` — 1–100

**Frontend fetch examples:**
```js
const res = await fetch(`/api/accounting/deposits/pending?per_page=20`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/accounting/deposits/${depositId}/confirm`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## 5. Salaries

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/accounting/salaries` | Salaries with commissions for period (requires month, year) |
| GET | `/api/accounting/salaries/{userId}` | Employee detail + sold units for period |
| POST | `/api/accounting/salaries/{userId}/distribute` | Create salary distribution |
| POST | `/api/accounting/salaries/distributions/{distributionId}/approve` | Approve distribution |
| POST | `/api/accounting/salaries/distributions/{distributionId}/paid` | Mark as paid |

**GET salaries query (required for index):**
- `month` — 1–12 (required)
- `year` — 2020–2100 (required)
- `type` — optional string
- `team_id` — exists:teams,id
- `commission_eligible` — boolean

**GET salaries/{userId} query (required):**
- `month` — 1–12
- `year` — 2020–2100

**POST salaries/{userId}/distribute body:**
```json
{ "month": 2, "year": 2025 }
```

**Frontend fetch examples:**
```js
const res = await fetch(`/api/accounting/salaries?month=2&year=2025`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/accounting/salaries/${userId}/distribute`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ month: 2, year: 2025 }),
});
```

---

## 6. Down Payment Confirmations (Legacy)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/accounting/pending-confirmations` | Pending confirmations list |
| POST | `/api/accounting/confirm/{reservationId}` | Confirm reservation |
| GET | `/api/accounting/confirmations/history` | Confirmation history |

**Frontend fetch examples:**
```js
const res = await fetch('/api/accounting/pending-confirmations', {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/accounting/confirm/${reservationId}`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## Shared: Notifications Proxy (Role-Based)

If the app uses a single notifications endpoint that routes by role (credit vs accounting):

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/notifications` | List notifications (dispatches to credit or accounting by user role) |
| POST | `/api/notifications/{id}/read` | Mark one as read |
| POST | `/api/notifications/read-all` | Mark all as read |

Use same headers: `Authorization: Bearer <token>`, `Accept: application/json`.
