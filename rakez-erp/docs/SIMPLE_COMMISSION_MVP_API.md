# Simple commission MVP — API & reporting reference

Concise backend reference for **reservation commission participants**, **project commission settings**, **previews** (read-only), and **project-based unit commission generation**. All URLs are prefixed with **`/api`**. Authentication is **Sanctum** unless noted.

---

## Formulas

All amounts use **consistent money rounding (2 decimals)** in services.

### Unit / reservation commission base

**Unit price resolution order** (`UnitPriceResolver`):

1. Existing loaded commission’s `final_selling_price` if **> 0**
2. `sales_reservations.proposed_price` if **> 0**
3. `contract_units.price` if **> 0**
4. Otherwise validation/domain error

### Buyer (`commission_source = buyer`)

- **Commission amount** = `unit_price × commission_percentage ÷ 100`

### Owner (`commission_source = owner`)

- **Commission amount** = `unit_price ÷ (1 + commission_percentage ÷ 100)`
- **Important:** that value **is** the commission amount itself. Do **not** subtract it from `unit_price`; this is **not** `unit_price − (unit_price × rate)`.

### Project-level preview

Same buyer/owner formulas with **`base_amount`** substituted for `unit_price` (Stage 3–4: **`base_amount` is required** in the project preview API body).

**Formula keys (stored on generated commissions)**

- Buyer: `buyer_unit_price_times_rate`
- Owner: `owner_unit_price_divided_by_100_percent_plus_rate`

---

## Reporting fields: `source_scope` / `source_type` vs `type`

- **`commission_distributions.source_scope`** and **`commission_distributions.source_type`** reflect the **MVP allocation model** (assigned vs outside buckets, bring/convince/close, management roles). Treat these as the **canonical** fields for reporting and new UI.
- **`commission_distributions.type`** remains the **legacy enum** consumed by older accounting flows. Populated **only for compatibility**. New integrations should prefer **`source_scope` / `source_type`**.

### Legacy `type` mapping (preview → DB enum)

Preview **participant** `source_type` → legacy `type`:

| source_type (`assigned` / `outside` buckets) | `commission_distributions.type` |
|-----------------------------------------------|---------------------------------|
| `bring`                                       | `lead_generation`               |
| `convince`                                    | `persuasion`                    |
| `close`                                       | `closing`                       |

Preview **`management`** `source_type` → legacy `type`:

| Management `source_type` | Legacy `type`      | Note                          |
|-------------------------|--------------------|-------------------------------|
| `ceo`                   | `project_manager`  | No `ceo` in DB enum           |
| `sales_manager`         | `sales_manager`    |                               |
| `sales_leader`          | `project_manager`  | No `management` in DB enum    |
| `group_leader`          | `team_leader`      |                               |
| `external_marketer`    | `external_marketer`|                               |

---

## 1. Reservation commission participants (Sales API)

Base: **`POST/GET ... /sales/...`** (role: `sales` | `sales_leader` | `admin` + scoped permissions).

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/sales/reservations/eligible-participants` | Users eligible to attach as participants (includes auth user where applicable). Middleware: `permission:sales.reservations.view`. |
| `GET` | `/sales/reservations/{reservation}/participants` | List participants for a reservation. Middleware: `permission:sales.reservations.view`. |
| `PUT` | `/sales/reservations/{reservation}/participants` | Sync participants (`participants[]`: `user_id`, `did_bring`, `did_convince`, `did_close`, `weight`, `notes`). Creator auto-added when creating a reservation; sync merges with creator rules tested in Stage 1. |

**Reservation create** (`POST /sales/reservations`, `permission:sales.reservations.create`) may include optional **`participants`** array with the same per-user flags; creator is normalized per Stage 1.

---

## 2. Project commission settings (Accounting API)

Base: **`/accounting`** (middleware: **`auth:sanctum`** + **`role:accounting|admin`**).

| Method | Endpoint | Permission / notes |
|--------|----------|-------------------|
| `GET` | `/accounting/project-commission-settings` | `accounting.sold-units.view` **or** `manage` (controller `abort_unless`). |
| `POST` | `/accounting/project-commission-settings` | **`accounting.sold-units.manage`** — create setting. |
| `GET` | `/accounting/project-commission-settings/{id}` | Same view/manage as index. |
| `PUT` | `/accounting/project-commission-settings/{id}` | **`manage`** — update. |
| `POST` | `/accounting/project-commission-settings/{id}/activate` | **`manage`** — activates one setting; siblings for same **`project_id`** deactivated. |

- **`project_id`** references **`contracts.id`** (the project).

---

## 3. Project commission preview (Accounting API)

| Method | Endpoint | Auth |
|--------|----------|------|
| `POST` | `/accounting/projects/{project}/preview-commission` | `PreviewProjectCommissionRequest`: `accounting.sold-units.view` **or** `manage`. |

**Body:** `base_amount` optional in rules but **required in practice**: service rejects missing or ≤ 0 base.

**Responses:** Uses active `ProjectCommissionSetting` for `{project}`; **422** with **`project_commission_setting`** key if none active.

---

## 4. Unit commission preview (Accounting API)

| Method | Endpoint | Auth |
|--------|----------|------|
| `POST` | `/accounting/reservations/{reservation}/preview-unit-commission` | `PreviewUnitCommissionRequest`: `accounting.sold-units.view` **or** `manage`. |

**Body (optional MVP):**

- `override_unit_price`: nullable numeric ≥ 0 — if sent and > 0, preview uses this instead of resolver (not persisted).

**Behavior:** Read-only. No commission rows written. **`422`** if no active project setting for the reservation’s contract (**`project_commission_setting`**) or unresolved unit price.

**Payload highlights:** `unit_price`, `unit_commission_amount`, `distributions`, **`unresolved`**, `total_distributed`, `remaining_amount`, `formula_key`.

---

## 5. Unit commission generation (Accounting API)

| Method | Endpoint | Permission |
|--------|----------|------------|
| `POST` | `/accounting/reservations/{reservation}/generate-unit-commission` | **`accounting.sold-units.manage`** (`GenerateUnitCommissionRequest` + route middleware). |

**Behavior:**

- Builds the same allocation as **`UnitCommissionPreviewService`**, persists **`commissions`** (with `calculated_by_project_setting = true`, `project_commission_setting_id`, `calculation_formula_key`) and **`commission_distributions`** for **resolved** lines only (**unresolved** stay out of DB and are returned in JSON).
- **Does not** use `Commission::calculateTotalAmount()` for owner source; **`total_amount` / `net_amount`** come from `ProjectCommissionCalculator`.
- Wrapped in **`DB::transaction`**.
- **Legacy / manual guard:** existing commission with **`calculated_by_project_setting ≠ true`** cannot be replaced (**422**, **`commission`**). Regeneration allowed only when commission is **`pending`** and **all** distributions are **`pending`**.
- **`422`** when preview rules fail (e.g. missing active setting, unresolved price, allocations invalid).

Success response shape (abridged): `success`, `message`, **`data.commission`**, **`data.distributions`** (includes `source_scope`, `source_type`), **`data.unresolved`**, **`data.total_distributed`**, **`data.remaining_amount`**.

---

## 6. Manual commission (unchanged legacy API)

For comparison only — not part of the project-based generator:

- **`POST /accounting/sold-units/{id}/commission`** with `accounting.sold-units.manage` — creates commissions **without** `calculated_by_project_setting`/`project_commission_setting_id` semantics (migration default keeps them legacy-safe).

---

## QA checklist (handoff)

| Check | Expected |
|-------|----------|
| No active project setting | **422** — `project_commission_setting` |
| Preview / generate | **`unresolved` array present** when a configured bucket has no matching participant (money not silently dropped from response semantics) |
| Legacy commission on reservation | **Generate** refuses with **`commission`**; DB row unchanged |
| Happy path | Reservation + participants → active setting → unit preview → generate → **`commissions`** + **`commission_distributions`** with **`source_*`** populated |

---

## Related tests

Feature coverage is split across `SalesReservationParticipantsStage1Test`, `ProjectCommissionSettingStage2Test`, `ProjectCommissionPreviewStage3Test`, `UnitCommissionGenerationStage4Test`, and **`SimpleCommissionMvpStage5Test`** (end-to-end smoke + QA asserts).
