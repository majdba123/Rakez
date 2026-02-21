# Credit API — Frontend Fetch & Request Reference

**Base URL:** `/api`  
**Auth:** All requests require `Authorization: Bearer <token>` (Laravel Sanctum).  
**Roles:** `credit` or `admin`.

**Response shape (success):** `{ success: true, message?: string, data?: T, meta?: PaginationMeta }`  
**Response shape (error):** `{ success: false, message: string }`  
**Pagination meta:** `{ total, per_page, current_page, last_page }` (or `meta.pagination` for sold-projects)

---

## 1. Dashboard

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/credit/dashboard` | Get KPIs, stage breakdown, title transfer breakdown |
| POST | `/api/credit/dashboard/refresh` | Refresh dashboard cache |

**Response data:** `data.kpis`, `data.kpis_labels_ar`, `data.stage_breakdown`, `data.stage_labels_ar`, `data.title_transfer_breakdown`, `data.title_transfer_labels_ar`.

**Frontend fetch example:**
```js
const res = await fetch('/api/credit/dashboard', {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
const json = await res.json(); // { success, message, data }

await fetch('/api/credit/dashboard/refresh', {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## 2. Notifications

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/credit/notifications` | List credit notifications (paginated) |
| POST | `/api/credit/notifications/{id}/read` | Mark one as read |
| POST | `/api/credit/notifications/read-all` | Mark all as read |

**Frontend fetch examples:**
```js
const res = await fetch(`/api/credit/notifications?per_page=20&page=1`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/notifications/${id}/read`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch('/api/credit/notifications/read-all', {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## 3. Bookings

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/credit/bookings` | **All** bookings: confirmed + negotiation + cancelled (paginated) |
| GET | `/api/credit/bookings/confirmed` | Confirmed bookings (paginated) |
| GET | `/api/credit/bookings/negotiation` | Negotiation bookings (paginated) |
| PUT or PATCH | `/api/credit/bookings/negotiation/{id}` | Update negotiation (e.g. viewed/notes) |
| GET | `/api/credit/bookings/waiting` | Waiting list (paginated) |
| GET | `/api/credit/bookings/sold` | Sold bookings (paginated) |
| GET | `/api/credit/bookings/cancelled` | Cancelled/rejected (paginated) |
| GET | `/api/credit/bookings/{id}` | Single booking detail (عرض التفاصيل) |
| GET | `/api/credit/bookings/show/{id}` | Same as above (alias for show details) |
| POST | `/api/credit/bookings/{id}/cancel` | Cancel booking |

**List item shape (all list endpoints):** Each item includes top-level `client_name`, `project_name` (always a string; `"غير محدد"` when missing), `booking_date`, `credit_status_label_ar`. Use these for the table. Detail response includes `data.id` and `data.client.name`.

**GET confirmed query (optional):**
- `credit_status` — filter by credit status
- `purchase_mechanism` — filter
- `down_payment_confirmed` — boolean
- `from_date`, `to_date` — date (on confirmed_at)
- `per_page` — 1–100 (default 15)

**GET sold / cancelled query (optional):**
- `from_date`, `to_date` — date
- `contract_id` — filter by contract
- `per_page` — 1–100

**GET negotiation / waiting:** `per_page` (optional).

**Frontend fetch examples:**
```js
const q = new URLSearchParams({ per_page: 15, page: 1 });
const res = await fetch(`/api/credit/bookings/confirmed?${q}`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

const detail = await fetch(`/api/credit/bookings/${bookingId}`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/bookings/${bookingId}/cancel`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## 4. Financing

All financing endpoints are **booking-centric** (use `bookings/{id}`; no tracker id in URL).

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/credit/bookings/{id}/financing` | Initialize financing tracker |
| POST | `/api/credit/bookings/{id}/financing/advance` | Advance to next stage (or init if none) |
| GET | `/api/credit/bookings/{id}/financing` | Get financing status (null if not started) |
| PATCH | `/api/credit/bookings/{id}/financing/stage/{stage}` | Complete a specific stage |
| POST | `/api/credit/bookings/{id}/financing/reject` | Reject financing |

**POST financing/advance body (optional):**
```json
{
  "bank_name": "string",
  "client_salary": 0,
  "employment_type": "government|private",
  "appraiser_name": "string"
}
```

**PATCH financing/stage/{stage} body (optional, stage 1 can require bank_name):**
```json
{
  "bank_name": "string",
  "client_salary": 0,
  "employment_type": "government|private",
  "appraiser_name": "string"
}
```

**POST financing/reject body:**
```json
{ "reason": "required string, max 1000" }
```

**Frontend fetch examples:**
```js
await fetch(`/api/credit/bookings/${bookingId}/financing`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/bookings/${bookingId}/financing/advance`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ bank_name: 'Bank Name' }),
});

const status = await fetch(`/api/credit/bookings/${bookingId}/financing`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/bookings/${bookingId}/financing/reject`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ reason: 'Rejection reason' }),
});
```

---

## 5. Title Transfer

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/credit/bookings/{id}/title-transfer` | Initialize title transfer |
| PATCH | `/api/credit/title-transfer/{id}/schedule` | Schedule transfer date |
| PATCH | `/api/credit/title-transfer/{id}/unschedule` | Remove scheduled date |
| POST | `/api/credit/title-transfer/{id}/complete` | Complete transfer |
| GET | `/api/credit/title-transfers/pending` | Pending title transfers |
| GET | `/api/credit/sold-projects` | Sold projects (completed transfers, paginated) |

**PATCH title-transfer/{id}/schedule body:**
```json
{
  "scheduled_date": "YYYY-MM-DD (required, >= today)",
  "notes": "optional string"
}
```

**GET sold-projects query (optional):**
- `from_date`, `to_date` — date
- `contract_id` — filter
- `per_page` — pagination

**Frontend fetch examples:**
```js
await fetch(`/api/credit/bookings/${bookingId}/title-transfer`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/title-transfer/${transferId}/schedule`, {
  method: 'PATCH',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ scheduled_date: '2025-03-01', notes: '' }),
});

await fetch(`/api/credit/title-transfer/${transferId}/unschedule`, {
  method: 'PATCH',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/title-transfer/${transferId}/complete`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

const pending = await fetch('/api/credit/title-transfers/pending', {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

const sold = await fetch(`/api/credit/sold-projects?per_page=15`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## 6. Claim Files (إصدار ملف المطالبة والإفراغات)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/credit/claim-files` | List claim files (paginated) |
| GET | `/api/credit/claim-files/candidates` | List candidates (sold reservations without claim file; for checkbox create) |
| POST | `/api/credit/claim-files/generate-bulk` | Generate claim files for multiple reservations |
| POST | `/api/credit/bookings/{id}/claim-file` | Generate claim file for one booking |
| GET | `/api/credit/claim-files/{id}` | Claim file detail |
| POST | `/api/credit/claim-files/{id}/pdf` | Generate PDF |
| GET | `/api/credit/claim-files/{id}/pdf` | Download PDF (re-download supported; use `pdf_download_path` from list when `has_pdf` is true) |

**GET claim-files** response items: `id`, `reservation_id`, `project_name`, `unit_id`, `unit_number`, `claim_amount` (مبلغ المطالبة — commission claim amount, numeric), `status` (under_processing | completed), `status_label_ar`, `file_data`, `has_pdf`, `created_at`, `pdf_download_path` (relative path for re-download when `has_pdf` is true; e.g. `credit/claim-files/1/pdf` — use with API base URL and same auth to GET the file).

**GET claim-files/candidates** response items: `reservation_id`, `project_name`, `unit_id`, `unit_number`, `claim_amount` (مبلغ المطالبة), `status`, `status_label_ar`. Use `reservation_id` with POST bookings/{id}/claim-file or POST claim-files/generate-bulk when user checks a row.

**POST claim-files/generate-bulk body:** `{ "reservation_ids": [1, 2, 3] }`. Response: `data.created` (reservation_id => claim_file_id), `data.errors` (reservation_id => message).

**GET claim-files / GET claim-files/candidates query (optional):** `per_page` (default 15, max 100).

**Frontend fetch examples:**
```js
const res = await fetch(`/api/credit/claim-files?per_page=20`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

const candidates = await fetch(`/api/credit/claim-files/candidates?per_page=20`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/claim-files/generate-bulk`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ reservation_ids: [1, 2, 3] }),
});

await fetch(`/api/credit/bookings/${bookingId}/claim-file`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

// Download PDF (blob)
const pdfRes = await fetch(`/api/credit/claim-files/${claimFileId}/pdf`, {
  headers: { Authorization: `Bearer ${token}` },
});
const blob = await pdfRes.blob();
```

---

## 7. Payment Plan (خطة دفعات — on-map projects)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/credit/bookings/{id}/payment-plan` | Get payment plan + summary |
| POST | `/api/credit/bookings/{id}/payment-plan` | Create payment plan |
| PUT | `/api/credit/payment-installments/{id}` | Update installment |
| DELETE | `/api/credit/payment-installments/{id}` | Delete installment |

**POST payment-plan body:**
```json
{
  "installments": [
    {
      "due_date": "YYYY-MM-DD (>= today)",
      "amount": 100000,
      "description": "optional"
    }
  ]
}
```

**PUT payment-installments/{id} body (all optional):**
```json
{
  "due_date": "date",
  "amount": 0.01,
  "description": "string",
  "status": "pending|paid|overdue"
}
```

**Frontend fetch examples:**
```js
const plan = await fetch(`/api/credit/bookings/${bookingId}/payment-plan`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});

await fetch(`/api/credit/bookings/${bookingId}/payment-plan`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    installments: [
      { due_date: '2025-03-01', amount: 100000, description: '' },
    ],
  }),
});

await fetch(`/api/credit/payment-installments/${installmentId}`, {
  method: 'PUT',
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ amount: 150000 }),
});

await fetch(`/api/credit/payment-installments/${installmentId}`, {
  method: 'DELETE',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
```

---

## Shared: Notifications Proxy (Role-Based)

If the app uses a single notifications endpoint that routes by role:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/notifications` | List (credit or accounting by user role) |
| POST | `/api/notifications/{id}/read` | Mark one as read |
| POST | `/api/notifications/read-all` | Mark all as read |

Use same headers: `Authorization: Bearer <token>`, `Accept: application/json`.
