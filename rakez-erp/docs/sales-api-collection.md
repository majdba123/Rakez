# Sales Module - Complete API Collection

**Base URL:** `/api/sales`
**Auth:** All requests require `Authorization: Bearer {token}` header (Sanctum)
**Content-Type:** `application/json`

---

## PART 1: SALES STAFF APIs (role: sales, sales_leader, admin)

---

### 1.1 Dashboard

**GET** `/api/sales/dashboard`
Permission: `sales.dashboard.view`

Query params:

- `scope` -- `me` | `team` | `all` (default: `me`)
- `from` -- date (e.g. `2026-01-01`)
- `to` -- date (e.g. `2026-03-01`)

Sample response:

```json
{
  "success": true,
  "data": {
    "kpi_version": "v2",
    "definitions": {
      "projects_under_marketing": "Contracts with ready/approved status and all units priced",
      "percent_confirmed": "confirmed_count / (confirmed_count + negotiation_count) * 100",
      "total_received_projects_value": "Sum of unit prices for projects with confirmed reservations in selected scope"
    },
    "reserved_units": 12,
    "available_units": 38,
    "projects_under_marketing": 5,
    "confirmed_count": 8,
    "negotiation_count": 4,
    "percent_confirmed": 66.67,
    "total_reservations": 12,
    "negotiation_ratio": 33.33,
    "sold_units_count": 3,
    "total_received_deposits": 150000.00,
    "total_refunded_deposits": 10000.00,
    "total_received_projects_value": 8500000.00,
    "total_revenue": 4500000.00
  }
}
```

---

### 1.2 Projects - List

**GET** `/api/sales/projects`
Permission: `sales.projects.view`

Query params:

- `status` -- `available` | `pending`
- `q` -- search by project name
- `city` -- filter by city
- `district` -- filter by district
- `scope` -- `me` | `team` | `all`. **Default:** `me` for sales; **`all`** for sales_leader (مدير مبيعات يرى كل المشاريع في القائمة).
- `per_page` -- int (default: 15)

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "contract_id": 1,
      "project_name": "برج ركيز السكني 1",
      "team_name": "فريق أحمد",
      "project_description": "وصف المشروع",
      "project_image_url": "https://example.com/storage/projects/image.jpg",
      "location": "الرياض, حي النرجس",
      "city": "الرياض",
      "district": "حي النرجس",
      "contract_status": "completed",
      "is_ready": true,
      "sales_status": "available",
      "project_status_label_ar": "جاهز - متاح للبيع",
      "status_badge_ar": "متاح",
      "total_units": 50,
      "available_units": 30,
      "reserved_units": 12,
      "sold_units": 8,
      "sold_units_percent": 16,
      "sold_units_label_ar": "وحدة مباعة",
      "price_min": 500000.00,
      "price_max": 1200000.00,
      "area_min_m2": 85.5,
      "area_max_m2": 220.0,
      "unit_type_label_ar": "شقق",
      "ad_code": "12345",
      "remaining_days": 45,
      "created_at": "2026-01-15T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

**Card fields:** `status_badge_ar` ("متاح" | "غير متاح"), `price_min` / `price_max` (Saudi Riyal), `area_min_m2` / `area_max_m2`, `unit_type_label_ar` (e.g. "شقق", "فيلا"), and `ad_code` (from advertiser section — كود الإعلان) are derived from contract units and second-party data. If units or second-party data are missing, these are `null`. **Bedrooms and bathrooms are not in the system** and can be added later if the `contract_units` schema is extended.

---

### 1.3 Projects - Show Detail

**GET** `/api/sales/projects/{contractId}`
Permission: `sales.projects.view`

Sample response:

```json
{
  "success": true,
  "data": {
    "contract_id": 1,
    "project_name": "برج ركيز السكني 1",
    "developer_name": "شركة ركيز",
    "developer_number": "0500000000",
    "city": "الرياض",
    "district": "حي النرجس",
    "location": "الرياض, حي النرجس",
    "project_description": "وصف المشروع",
    "project_image_url": "https://example.com/storage/projects/image.jpg",
    "contract_status": "completed",
    "is_ready": true,
    "sales_status": "available",
    "project_status_label_ar": "جاهز - متاح للبيع",
    "status_badge_ar": "متاح",
    "team_name": "فريق أحمد",
    "emergency_contact_number": "0501111111",
    "security_guard_number": "0502222222",
    "total_units": 50,
    "available_units": 30,
    "reserved_units": 12,
    "sold_units": 8,
    "sold_units_percent": 16,
    "sold_units_label_ar": "وحدة مباعة",
    "price_min": 500000.00,
    "price_max": 1200000.00,
    "area_min_m2": 85.5,
    "area_max_m2": 220.0,
    "unit_type_label_ar": "شقق",
    "ad_code": "12345",
    "montage_data": {
      "image_url": "https://example.com/storage/montage/img.jpg",
      "video_url": "/storage/montage/video.mp4",
      "description": "..."
    },
    "created_at": "2026-01-15T10:00:00+00:00"
  }
}
```

**Card fields:** Same as list (1.2): `status_badge_ar`, `price_min` / `price_max`, `area_min_m2` / `area_max_m2`, `unit_type_label_ar`, `ad_code`. Bedrooms and bathrooms are not in the system.

---

### 1.4 Projects - Units List

**GET** `/api/sales/projects/{contractId}/units`
Permission: `sales.projects.view`

Query params:

- `status` -- `available` | `reserved` | `sold` | `pending`
- `floor` -- int
- `min_price` -- numeric
- `max_price` -- numeric
- `per_page` -- int (default: 15)

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "unit_id": 101,
      "unit_number": "A-101",
      "unit_type": "شقة",
      "type": "شقة",
      "area_m2": 120.5,
      "floor": 3,
      "price": 850000.00,
      "unit_status": "available",
      "computed_availability": "available",
      "can_reserve": true,
      "active_reservation": null
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 15, "total": 20 }
}
```

---

### 1.5 Reservation Context (pre-fill form)

**GET** `/api/sales/units/{unitId}/reservation-context`
Permission: `sales.reservations.create`

Sample response:

```json
{
  "success": true,
  "data": {
    "project": { "project_name": "برج ركيز", "city": "الرياض", "district": "النرجس" },
    "unit": { "unit_id": 101, "unit_number": "A-101", "unit_type": "شقة", "area_m2": 120.5, "floor": 3, "price": 850000.00 },
    "marketing_employee": { "id": 5, "name": "أحمد القحطاني", "team": "فريق أ" },
    "readonly_project_unit_snapshot": {
      "project_name": "برج ركيز", "unit_number": "A-101", "unit_type": "شقة",
      "district": "النرجس", "location": "الرياض, النرجس",
      "area_m2": 120.5, "total_unit_price": 850000.00,
      "marketing_employee_name": "أحمد القحطاني", "marketing_team": "فريق أ"
    },
    "flags": { "is_off_plan": false, "can_create_payment_plan": false, "requires_separate_title_transfer_date": false },
    "lookups": {
      "reservation_types": [
        { "value": "confirmed_reservation", "label": "Confirmed Reservation" },
        { "value": "negotiation", "label": "Reservation for Negotiation" }
      ],
      "payment_methods": [
        { "value": "bank_transfer", "label": "Bank Transfer" },
        { "value": "cash", "label": "Cash" },
        { "value": "bank_financing", "label": "Bank Financing" }
      ],
      "down_payment_statuses": [
        { "value": "refundable", "label": "Refundable" },
        { "value": "non_refundable", "label": "Non-refundable" }
      ],
      "purchase_mechanisms": [
        { "value": "cash", "label": "Cash" },
        { "value": "supported_bank", "label": "Supported Bank" },
        { "value": "unsupported_bank", "label": "Unsupported Bank" }
      ],
      "nationalities": ["Saudi","Egyptian","Syrian","Jordanian","Lebanese","Palestinian","Iraqi","Yemeni","Kuwaiti","Emirati","Bahraini","Qatari","Omani","Other"]
    }
  }
}
```

---

### 1.6 Create Reservation

**POST** `/api/sales/reservations`  
Permission: `sales.reservations.create`

**Headers:**
- `Authorization: Bearer {token}` (required)
- `Content-Type: application/json`
- `Accept: application/json`

**Request body (full specification)**

| Field | Type | Required | Accepted values / notes |
|-------|------|----------|-------------------------|
| `contract_id` | integer | Yes | Must exist in `contracts.id` |
| `contract_unit_id` | integer | Yes | Must exist in `contract_units.id` and belong to `contract_id` |
| `contract_date` | string (date) | Yes | `YYYY-MM-DD`. Default: today if omitted. |
| `reservation_type` | string | Yes | `confirmed_reservation` or `negotiation`. **Form labels accepted:** `حجز بغرض التفاوض` → negotiation; `عقد` / حجز بعقد → confirmed_reservation. Aliases: `عقد`, `contract`, `confirmed` → confirmed_reservation; `تفاوض`, `negotiation` → negotiation. |
| `client_name` | string | Yes | Max 255 |
| `client_mobile` | string | Yes | Max 50. Aliases: `phone`, `mobile` → mapped to `client_mobile`. |
| `client_nationality` | string | Yes | Max 100. Default: `غير محدد` if omitted. |
| `client_iban` | string | Yes | Max 100. **Default: `-` if omitted or empty** (نموذج حجز الوحدة). Alias: `clientIban`. |
| `payment_method` | string | Yes | `bank_transfer` \| `cash` \| `bank_financing`. **Form labels:** تحويل بنكي → bank_transfer; كاش/نقد → cash; تمويل بنكي → bank_financing. Default: `cash` if omitted. Alias: `paymentMethod`. |
| `down_payment_amount` | number | Yes | ≥ 0. Alias: `downPaymentAmount`. |
| `down_payment_status` | string | Yes | `refundable` \| `non_refundable`. **Form:** عربون مسترد → refundable; غير مسترد → non_refundable. Default: `refundable`. Alias: `downPaymentStatus`. |
| `purchase_mechanism` | string | Yes | `cash` \| `supported_bank` \| `unsupported_bank`. **Form labels:** بنك غير مدعوم → unsupported_bank; بنك مدعوم → supported_bank; كاش → cash. Default: `cash`. Alias: `purchaseMechanism`. |
| `evacuation_date` | string (date) | No | `YYYY-MM-DD`, must be ≥ today. Required for off-plan + non_refundable deposit. |
| `negotiation_notes` | string | If type=negotiation | Required when `reservation_type` is `negotiation`. |
| `negotiation_reason` | string | If type=negotiation | Max 255. Required when `reservation_type` is `negotiation`. |
| `proposed_price` | number | If type=negotiation | Required when `reservation_type` is `negotiation`; must be &lt; unit price. |

**Minimal request (عقد / confirmed):** the API applies defaults for omitted fields.

```json
{
  "contract_id": 52,
  "contract_unit_id": 201,
  "contract_date": "2026-02-28",
  "reservation_type": "عقد",
  "client_name": "أحمد محمد",
  "client_mobile": "0512345678",
  "down_payment_amount": 50000
}
```

Or using API values and camelCase aliases:

```json
{
  "contract_id": 52,
  "contract_unit_id": 201,
  "reservationType": "confirmed_reservation",
  "client_name": "أحمد محمد",
  "phone": "0512345678",
  "downPaymentAmount": 50000
}
```

**Full request (all fields, عقد):**

```json
{
  "contract_id": 52,
  "contract_unit_id": 201,
  "contract_date": "2026-02-28",
  "reservation_type": "confirmed_reservation",
  "client_name": "عبدالله المنصور",
  "client_mobile": "0512345678",
  "client_nationality": "Saudi",
  "client_iban": "SA1234567890123456789012",
  "payment_method": "bank_transfer",
  "down_payment_amount": 50000.00,
  "down_payment_status": "non_refundable",
  "purchase_mechanism": "supported_bank",
  "evacuation_date": "2026-06-01"
}
```

**Full request (تفاوض / negotiation):** add:

```json
{
  "reservation_type": "negotiation",
  "negotiation_notes": "العميل يرغب بسعر أقل",
  "negotiation_reason": "price",
  "proposed_price": 800000.00
}
```

**Copy-paste full request (confirmed_reservation):**

```json
{
  "contract_id": 52,
  "contract_unit_id": 201,
  "contract_date": "2026-02-28",
  "reservation_type": "confirmed_reservation",
  "client_name": "عبدالله المنصور",
  "client_mobile": "0512345678",
  "client_nationality": "Saudi",
  "client_iban": "SA1234567890123456789012",
  "payment_method": "cash",
  "down_payment_amount": 50000,
  "down_payment_status": "refundable",
  "purchase_mechanism": "cash"
}
```

Replace `52` with your project (contract) id and `201` with the unit id. Optional: add `"evacuation_date": "2026-06-01"` for off-plan.

Sample response (201):

```json
{
  "success": true,
  "message": "Reservation created successfully",
  "data": {
    "reservation_id": 55,
    "project_name": "برج ركيز السكني 1",
    "unit_number": "A-101",
    "client_name": "عبدالله المنصور",
    "status": "confirmed",
    "reservation_type": "confirmed_reservation",
    "marketing_employee_id": 5,
    "marketing_employee_name": "أحمد القحطاني",
    "down_payment_amount": 50000.00,
    "contract_date": "2026-02-26",
    "created_at": "2026-02-26T10:30:00+00:00",
    "confirmed_at": "2026-02-26T10:30:00+00:00",
    "cancelled_at": null,
    "voucher_url": "/api/sales/reservations/55/voucher"
  }
}
```

---

### 1.6b Get Reservation Details (for detail modal)

**GET** `/api/sales/reservations/{id}`  
Permission: `sales.reservations.view`

Returns full reservation details including: `client_mobile`, `client_nationality`, `payment_method`, `purchase_mechanism`, `down_payment_status`, `project_name`, `unit_number`, `negotiation_notes`, `negotiation_reason`, `proposed_price`, `evacuation_date`, etc. Use this when the UI opens "تفاصيل الحجز" so all fields are populated from the API.

Response: `{ "success": true, "data": { ... } }` — `data` includes all fields needed for the detail modal (الجوال، الجنسية، طريقة الدفع، آلية الشراء، حالة العربون، etc.).

---

### 1.7 List Reservations

**GET** `/api/sales/reservations`  
Permission: `sales.reservations.view`

Query params:

- `mine` -- boolean (only my reservations)
- `include_cancelled` -- boolean
- `contract_id` -- int
- `status` -- `under_negotiation` | `confirmed` | `cancelled`
- `from` -- date
- `to` -- date
- `per_page` -- int (default: 15)

Each list item includes: `client_mobile`, `client_nationality`, `payment_method`, `down_payment_status`, `purchase_mechanism` so the detail view can show them without a separate call. For full details (e.g. negotiation_notes, proposed_price), use **GET** `/api/sales/reservations/{id}` (1.6b).

Sample response: paginated array; each `data[]` item has the same core fields as 1.6 plus the client/financial fields above.

---

### 1.8 Confirm Reservation

**POST** `/api/sales/reservations/{id}/confirm`
Permission: `sales.reservations.confirm`

No request body needed.

Sample response:

```json
{
  "success": true,
  "message": "Reservation confirmed successfully",
  "data": { "reservation_id": 55, "status": "confirmed", "confirmed_at": "2026-02-26T11:00:00+00:00" }
}
```

---

### 1.9 Cancel Reservation

**POST** `/api/sales/reservations/{id}/cancel`
Permission: `sales.reservations.cancel`

Request body:

```json
{
  "cancellation_reason": "العميل لم يسدد الدفعة"
}
```

---

### 1.10 Log Reservation Action

**POST** `/api/sales/reservations/{id}/actions`
Permission: `sales.reservations.view`

Request body:

```json
{
  "action_type": "lead_acquisition",
  "notes": "تم التواصل مع العميل"
}
```

`action_type` values: `lead_acquisition` | `persuasion` | `closing`

---

### 1.11 Download Voucher

**GET** `/api/sales/reservations/{id}/voucher`
Permission: `sales.reservations.view`

Response: PDF file download.

---

### 1.12 My Targets

**GET** `/api/sales/targets/my`
Permission: `sales.targets.view`

Query params: `from`, `to`, `status`, `per_page`

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "target_id": 10,
      "project_name": "برج ركيز السكني 1",
      "unit_number": "A-101",
      "target_type": "reservation",
      "target_type_label_ar": "حجز",
      "start_date": "2026-02-01",
      "end_date": "2026-02-28",
      "status": "in_progress",
      "status_label_ar": "قيد التنفيذ",
      "leader_notes": "يرجى التركيز على هذا الهدف",
      "assigned_by": "أحمد القحطاني"
    }
  ]
}
```

---

### 1.13 Update Target Status

**PATCH** `/api/sales/targets/{id}`
Permission: `sales.targets.update`

Request body:

```json
{ "status": "completed" }
```

Values: `new` | `in_progress` | `completed`

---

### 1.14 My Attendance

**GET** `/api/sales/attendance/my`
Permission: `sales.attendance.view`

Query params: `from`, `to`

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "schedule_id": 1,
      "user_name": "محمد الفياض",
      "schedule_date": "2026-02-26",
      "day_of_week": "Thursday",
      "start_time": "08:00:00",
      "end_time": "17:00:00",
      "project_name": "برج ركيز السكني 1",
      "project_location": "الرياض, النرجس"
    }
  ]
}
```

---

### 1.15 Waiting List - List

**GET** `/api/sales/waiting-list`
Permission: `sales.waiting_list.create`

Query params: `status`, `sales_staff_id`, `contract_id`, `contract_unit_id`, `active_only`, `per_page`

---

### 1.16 Waiting List - By Unit

**GET** `/api/sales/waiting-list/unit/{unitId}`
Permission: `sales.waiting_list.create`

---

### 1.17 Waiting List - Create

**POST** `/api/sales/waiting-list`
Permission: `sales.waiting_list.create`

Request body:

```json
{
  "contract_id": 1,
  "contract_unit_id": 101,
  "client_name": "سعد الدوسري",
  "client_mobile": "0509876543",
  "client_email": "saad@email.com",
  "priority": 1,
  "notes": "عميل مهتم جداً"
}
```

---

### 1.18 Waiting List - Cancel

**DELETE** `/api/sales/waiting-list/{id}`
Permission: `sales.waiting_list.create`

---

### 1.19 Sold Units

**GET** `/api/sales/sold-units`
Permission: `sales.dashboard.view`

Query params: `from`, `to`, `per_page`

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "unit_id": 101,
      "project_name": "برج ركيز",
      "unit_number": "A-101",
      "unit_type": "شقة",
      "final_selling_price": 850000.00,
      "commission_source": "seller",
      "commission_percentage": 2.5,
      "team_responsible": "فريق أحمد",
      "marketing_employee_name": "أحمد القحطاني",
      "status": "sold",
      "confirmed_at": "2026-02-20T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 5 }
}
```

---

### 1.20 Commission Summary for Unit

**GET** `/api/sales/sold-units/{unitId}/commission-summary`
Permission: `sales.dashboard.view`

---

### 1.21 Deposits Management

**GET** `/api/sales/deposits/management`
Permission: `sales.dashboard.view`

Query params: `status`, `from`, `to`, `per_page`

---

### 1.22 Deposits Follow-up

**GET** `/api/sales/deposits/follow-up`
Permission: `sales.dashboard.view`

Query params: `from`, `to`, `per_page`

---

### 1.23 Analytics - Dashboard

**GET** `/api/sales/analytics/dashboard`
Permission: `sales.dashboard.view`

Query params: `from`, `to`

---

### 1.24 Analytics - Sold Units

**GET** `/api/sales/analytics/sold-units`
Permission: `sales.dashboard.view`

Query params: `from`, `to`, `per_page` (1-100)

---

### 1.25 Analytics - Deposit Stats by Project

**GET** `/api/sales/analytics/deposits/stats/project/{contractId}`
Permission: `sales.dashboard.view`

---

### 1.26 Analytics - Commission Stats by Employee

**GET** `/api/sales/analytics/commissions/stats/employee/{userId}`
Permission: `sales.dashboard.view`

Query params: `from`, `to`

---

### 1.27 Analytics - Monthly Commission Report

**GET** `/api/sales/analytics/commissions/monthly-report`
Permission: `sales.dashboard.view`

Query params (required):

- `year` -- int (2020-2100)
- `month` -- int (1-12)

---

### 1.28 Notifications

**GET** `/api/notifications`
Auth: any authenticated user

Query params: `per_page`

Sample response:

```json
{
  "success": true,
  "data": [
    { "id": 1, "message": "You are assigned to برج ركيز on 2026-02-26 from 08:00:00 to 17:00:00.", "status": "pending", "event_type": "schedule_assigned", "created_at": "..." }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 }
}
```

**PATCH** `/api/notifications/mark-all-read` -- Mark all as read
**PATCH** `/api/notifications/{id}/read` -- Mark single as read

---
---

## PART 2: SALES LEADER-ONLY APIs (requires permission: sales.team.manage)

---

### 2.1 Team Projects

**GET** `/api/sales/team/projects`

Query params: `per_page`

Sample response: Same shape as 1.2 (paginated project list), but only projects assigned to this leader.

---

### 2.2 Team Members

**GET** `/api/sales/team/members`

Query params: `with_ratings` (default: true) — when true, includes leader_rating and confirmed_reservations_count.

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "محمد الفياض",
      "email": "mohammed@rakez.com",
      "team_id": 1,
      "leader_rating": 4,
      "leader_rating_comment": null,
      "confirmed_reservations_count": 12
    }
  ]
}
```

---

### 2.2a Rate / Comment on Team Member (تقييم وتعليق عن الموظف)

**PATCH** `/api/sales/team/members/{memberId}/rating`

مدير المبيعات يمكنه تقييم العضو (1–5 نجوم) و/أو **التعليق عن هذا الموظف**. يمكن إرسال التقييم فقط، أو التعليق فقط، أو الاثنين معاً.

Request body:

```json
{
  "rating": 4,
  "comment": "تعليق مدير المبيعات عن أداء هذا الموظف (حتى 2000 حرف)"
}
```

- `rating`: اختياري، 1–5 نجوم.
- `comment`: اختياري، تعليق عن الموظف (حتى 2000 حرف).
- يجب إرسال أحدهما على الأقل (تقييم و/أو تعليق).

---

### 2.2b Remove Team Member (إخراج عضو من الفريق)

**POST** `/api/sales/team/members/{memberId}/remove`

No body. Removes the member from the leader's team (sets `team_id` to null).

---

### 2.2c Team Recommendations (ترشيح بالذكاء الاصطناعي)

**GET** `/api/sales/team/recommendations`

ترتيب أعضاء الفريق حسب نقاط الترشيح. المعايير: حجم المبيعات (حجوزات مؤكدة)، نسبة الإقفال، جودة نوع الوحدة (فيلا ثم شقة...)، الأداء في آخر 90 يوم، وتقييم المدير. كل عنصر يتضمن `recommendation_highlights` (أسباب الترشيح بالعربية).

Sample response:

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "محمد الفياض",
      "email": "mohammed@rakez.com",
      "team_id": 1,
      "leader_rating": 5,
      "leader_rating_comment": null,
      "confirmed_reservations_count": 15,
      "total_reservations": 18,
      "confirmed_percent": 83.3,
      "unit_type_avg_score": 100,
      "recommendation_score": 92.5,
      "recommendation_highlights": ["أعلى عدد حجوزات مؤكدة", "أعلى نسبة إقفال", "تقييم المدير: 5 نجوم"],
      "confirmed_recent_90": 4
    }
  ]
}
```

---

### 2.3 Update Emergency Contacts

**PATCH** `/api/sales/projects/{contractId}/emergency-contacts`

Request body:

```json
{
  "emergency_contact_number": "0501111111",
  "security_guard_number": "0502222222"
}
```

---

### 2.4 Create Target for Team Member

**POST** `/api/sales/targets`

The team leader can assign **one or multiple units** to a sales staff member. Use `contract_unit_ids` (array) for multiple units, or `contract_unit_id` (single) for one unit. All units must belong to the selected project (`contract_id`).

Request body (single unit):

```json
{
  "marketer_id": 5,
  "contract_id": 1,
  "contract_unit_id": 101,
  "target_type": "reservation",
  "start_date": "2026-03-01",
  "end_date": "2026-03-31",
  "leader_notes": "التركيز على العملاء المهتمين"
}
```

Request body (multiple units):

```json
{
  "marketer_id": 5,
  "contract_id": 1,
  "contract_unit_ids": [101, 102, 103],
  "target_type": "reservation",
  "start_date": "2026-03-01",
  "end_date": "2026-03-31",
  "leader_notes": "التركيز على الوحدات المحددة"
}
```

`target_type` values: `reservation` | `negotiation` | `closing`

Sample response: Same as 1.12 target object; includes `units` (array of `{ id, unit_number }`) and `contract_unit_ids` when multiple units are assigned.

---

### 2.5 Team Attendance

**GET** `/api/sales/attendance/team`

Query params: `from`, `to`, `contract_id`, `user_id`

Sample response: Same shape as 1.14 (array of attendance objects).

---

### 2.6 تعيين دوام موظف (اليوم + التاريخ + الساعات)

**POST** `/api/sales/attendance/schedules`

مدير المبيعات يعيّن دوام عضو الفريق **بالتاريخ والساعات** (اليوم يُستنتج من التاريخ ويُرجع في الاستجابة كـ `day_name_ar`).

Request body:

```json
{
  "contract_id": 1,
  "user_id": 5,
  "schedule_date": "2026-03-01",
  "start_time": "08:00",
  "end_time": "17:00"
}
```

- `schedule_date`: التاريخ (Y-m-d).
- `start_time` / `end_time`: الساعات — يمكن إرسال "08:00" أو "08:00:00".
- الاستجابة تتضمن `day_name_ar` (اسم اليوم بالعربية، مثل الخميس)، `schedule_date`، `start_time`، `end_time` لاستخدامها في الواجهة.

Sample response:

```json
{
  "success": true,
  "message": "Schedule created successfully",
  "data": {
    "schedule_id": 12,
    "user_id": 5,
    "user_name": "محمد الفياض",
    "schedule_date": "2026-03-01",
    "day_of_week": "Sunday",
    "day_name_ar": "الأحد",
    "start_time": "08:00:00",
    "end_time": "17:00:00",
    "project_id": 1,
    "project_name": "برج ركيز",
    "project_location": "الرياض, النرجس"
  }
}
```

---

### 2.7 Project Attendance Overview (for toggle view)

**GET** `/api/sales/attendance/project/{contractId}`

Query params: `date` (optional, defaults to server today in app timezone)

**100% date match for sales leaders:** Use `server_date` and `server_time` for "تاريخ التحديث" / "توقيت التحديث", and `day_name_ar` for the day label (e.g. الخميس) so the view matches the backend. All times use `config('app.timezone')`.

Sample response:

```json
{
  "success": true,
  "data": {
    "project": { "id": 1, "name": "برج ركيز السكني 1", "location": "الرياض, النرجس" },
    "date": "2026-02-26",
    "server_date": "2026-02-26",
    "server_time": "01:59:18",
    "day_name_ar": "الخميس",
    "members": [
      { "user_id": 5, "name": "محمد الفياض", "is_present": true, "schedule": { "schedule_id": 12, "start_time": "08:00:00", "end_time": "17:00:00" } },
      { "user_id": 7, "name": "نورة الشهري", "is_present": false, "schedule": null },
      { "user_id": 9, "name": "نواف الجوابي", "is_present": true, "schedule": { "schedule_id": 13, "start_time": "09:00:00", "end_time": "18:00:00" } }
    ]
  }
}
```

- `server_date`: Current date (Y-m-d) in app timezone — use for "تاريخ التحديث".
- `server_time`: Current time (H:i:s, 24h) in app timezone — use for "توقيت التحديث".
- `day_name_ar`: Arabic day name for the requested `date` — use for the day label next to each marketer.

---

### 2.8 Bulk Save Attendance (save all toggles)

**POST** `/api/sales/attendance/project/{contractId}/bulk`

Request body:

```json
{
  "date": "2026-02-26",
  "schedules": [
    { "user_id": 5, "present": true, "start_time": "08:00:00", "end_time": "17:00:00" },
    { "user_id": 7, "present": false },
    { "user_id": 9, "present": true, "start_time": "09:00:00", "end_time": "18:00:00" }
  ]
}
```

Sample response:

```json
{
  "success": true,
  "message": "Attendance saved. 3 schedule(s) updated.",
  "data": { "created": 1, "updated": 1, "removed": 1 }
}
```

Sends notifications to each affected team member automatically.

---

### 2.9 Marketing Tasks - List Projects

**GET** `/api/sales/tasks/projects`
Permission: `sales.tasks.manage`

---

### 2.10 Marketing Tasks - Show Project

**GET** `/api/sales/tasks/projects/{contractId}`
Permission: `sales.tasks.manage`

---

### 2.11 Create Marketing Task

**POST** `/api/sales/marketing-tasks`
Permission: `sales.tasks.manage`

Request body:

```json
{
  "contract_id": 1,
  "task_name": "تصوير الوحدات النموذجية",
  "marketer_id": 5,
  "participating_marketers_count": 3,
  "design_link": "https://figma.com/...",
  "design_number": "D-001",
  "design_description": "تصميم منشور الافتتاح"
}
```

Sample response:

```json
{
  "success": true,
  "data": {
    "task_id": 20,
    "contract_id": 1,
    "task_name": "تصوير الوحدات النموذجية",
    "marketer_name": "محمد الفياض",
    "marketer_phone": "0512345678",
    "participating_marketers_count": 3,
    "design_link": "https://figma.com/...",
    "design_number": "D-001",
    "design_description": "تصميم منشور الافتتاح",
    "status": "new",
    "status_label_ar": "جديد",
    "created_at": "2026-02-26T12:00:00+00:00"
  }
}
```

---

### 2.12 Update Marketing Task Status

**PATCH** `/api/sales/marketing-tasks/{id}`
Permission: `sales.tasks.manage`

Request body:

```json
{ "status": "in_progress" }
```

Values: `new` | `in_progress` | `completed`

---

### 2.13 Convert Waiting List to Reservation (Leader only)

**POST** `/api/sales/waiting-list/{id}/convert`
Permission: `sales.waiting_list.convert`

Request body:

```json
{
  "contract_date": "2026-03-01",
  "reservation_type": "confirmed_reservation",
  "client_nationality": "Saudi",
  "client_iban": "SA1234567890123456789012",
  "payment_method": "bank_transfer",
  "down_payment_amount": 50000.00,
  "down_payment_status": "non_refundable",
  "purchase_mechanism": "supported_bank"
}
```

For negotiation type, add `"negotiation_notes": "..."`.

---

### 2.14 Admin - Assign Project to Leader (admin only)

**POST** `/api/admin/sales/project-assignments`
Role: `admin`

Request body:

```json
{
  "leader_id": 6,
  "contract_id": 1,
  "start_date": "2026-03-01",
  "end_date": "2026-09-01"
}
```

---

## Quick Reference: All Routes Summary

**Sales Staff (28 endpoints):**

- GET dashboard, projects, projects/{id}, projects/{id}/units
- GET units/{id}/reservation-context
- POST reservations, GET reservations
- POST reservations/{id}/confirm, cancel, actions
- GET reservations/{id}/voucher
- GET targets/my, PATCH targets/{id}
- GET attendance/my
- GET/POST/DELETE waiting-list, GET waiting-list/unit/{id}
- GET sold-units, sold-units/{id}/commission-summary
- GET deposits/management, deposits/follow-up
- GET analytics/dashboard, analytics/sold-units
- GET analytics/deposits/stats/project/{id}
- GET analytics/commissions/stats/employee/{id}
- GET analytics/commissions/monthly-report
- GET /api/notifications

**Sales Leader (14 endpoints):**

- GET team/projects, team/members
- PATCH projects/{id}/emergency-contacts
- POST targets
- GET attendance/team
- POST attendance/schedules
- GET attendance/project/{id}
- POST attendance/project/{id}/bulk
- GET tasks/projects, tasks/projects/{id}
- POST marketing-tasks, PATCH marketing-tasks/{id}
- POST waiting-list/{id}/convert

**Admin (1 endpoint):**

- POST admin/sales/project-assignments
