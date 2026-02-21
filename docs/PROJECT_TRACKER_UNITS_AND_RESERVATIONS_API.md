# Project Tracker – Units Tab & Create Reservation APIs

This document covers the APIs that power the **Units** tab and **Create New Reservation** modal on the project-tracker page (`/project-tracker/{contractId}`).

---

## 1. Units tab (project_management)

These routes are under `auth:sanctum` + role `project_management|admin`. Base path: `/api/`.

| Action | Method | Path | Permission |
|--------|--------|------|------------|
| List units for contract | GET | `contracts/units/show/{contractId}` | units.view |
| Upload CSV for units | POST | `contracts/units/upload-csv/{contractId}` | units.csv_upload |
| Create unit | POST | `contracts/units/store/{contractId}` | units.edit |
| Update unit | PUT | `contracts/units/update/{unitId}` | units.edit |
| Delete unit | DELETE | `contracts/units/delete/{unitId}` | units.edit |

- **List units:** `GET /api/contracts/units/show/32` — returns units for contract 32 (status, price, etc.).
- **Download contract (PDF):** `GET /api/project_management/contracts/32/export` — see project tracker API doc.

---

## 2. Create reservation (sales)

The **“إنشاء حجز جديد”** (Create New Reservation) modal and **“تأكيد الحجز”** (Confirm Reservation) call the **Sales** reservation API. These routes require role **sales | sales_leader | admin | project_management** and permission `sales.reservations.create`.

| Action | Method | Path | Permission |
|--------|--------|------|------------|
| Reservation context for unit | GET | `sales/units/{unitId}/reservation-context` | sales.reservations.create |
| Create reservation | POST | `sales/reservations` | sales.reservations.create |

### GET `/api/sales/units/{unitId}/reservation-context`

- Optional: call before opening the modal to get unit/project context.
- Returns unit and related contract/second-party data.

### POST `/api/sales/reservations` — Create reservation

**Request body (JSON)** — depends on `reservation_type`.

**For `reservation_type === 'preliminary'` (حجز مبدئي) — Create New Reservation modal:**

- `contract_id` (required), `contract_unit_id` (required), `reservation_type`: `"preliminary"`, `client_name` (required), `client_mobile` (required), `down_payment_amount` (required). Optional: `contract_date` (defaults to today), `notes` (ملاحظات), `client_nationality`, `client_iban`, `payment_method`, `down_payment_status`, `purchase_mechanism`.

**For `confirmed_reservation` or `negotiation`:** all of: `contract_id`, `contract_unit_id`, `contract_date`, `client_name`, `client_mobile`, `client_nationality`, `client_iban`, `payment_method`, `down_payment_amount`, `down_payment_status`, `purchase_mechanism`. If negotiation: `negotiation_notes`, `negotiation_reason`, `proposed_price`. Optional: `evacuation_date` for off-plan + non_refundable.

**Responses:**

- **201** — reservation created.
- **409** — unit already reserved.
- **422** — validation error.
- **403** — user lacks `sales.reservations.create` or role.

---

## 3. Frontend checklist (Units + Reservation)

- **Units tab:** Use `GET /api/contracts/units/show/{contractId}` with project_management (or admin) token.
- **Download contract:** Use `GET /api/project_management/contracts/{id}/export`.
- **Upload CSV:** Use `POST /api/contracts/units/upload-csv/{contractId}` with multipart/form-data CSV file.
- **Create reservation:** Use `POST /api/sales/reservations` with a user that has role sales | sales_leader | admin | project_management and permission `sales.reservations.create`. For the “Create New Reservation” modal send `reservation_type: "preliminary"` with `contract_id`, `contract_unit_id`, `client_name`, `client_mobile`, `down_payment_amount`, and optional `notes` and `contract_date`.
