# API المحاسبة – العمولات (للفرونت إند)

**Base URL:** `{{base_url}}/api/accounting`  
مثال: `https://your-domain.com/api/accounting`

**المصادقة:** جميع الطلبات تتطلب مصادقة Sanctum.

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**الأدوار:** `accounting` أو `admin`  
**الصلاحيات:** مذكورة تحت كل endpoint.

---

## 1. قائمة العمولات المُصرفة (عرض للمحاسبة)

عرض العمولات المُعتمدة/المُصرفة مع اسم الموظف، المشروع، المبلغ، وتأكيد إرسال الإشعار.

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/accounting/commissions/released` |
| **Permission** | `accounting.sold-units.view` |

### Query Parameters

| Parameter   | Type   | Required | Description                    |
|------------|--------|----------|--------------------------------|
| `from_date` | string | لا       | تاريخ البداية `Y-m-d`          |
| `to_date`   | string | لا       | تاريخ النهاية `Y-m-d` (≥ from_date) |
| `per_page`  | integer| لا       | عدد النتائج في الصفحة (1–100، افتراضي 25) |

### مثال طلب

```http
GET /api/accounting/commissions/released?from_date=2026-01-01&to_date=2026-03-31&per_page=15
Authorization: Bearer {token}
Accept: application/json
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم جلب قائمة العمولات المُصرفة بنجاح",
  "data": [
    {
      "id": 12,
      "commission_id": 5,
      "employee_id": 3,
      "employee_name": "أحمد محمد",
      "project_name": "مشروع النخيل",
      "unit_number": "A-101",
      "type": "lead_generation",
      "type_label": "عمولة الجلب",
      "amount": 1250.50,
      "percentage": 25.00,
      "status": "paid",
      "approved_at": "2026-03-01T10:00:00.000000Z",
      "paid_at": "2026-03-05T14:30:00.000000Z",
      "notification_sent": true
    }
  ],
  "meta": {
    "total": 42,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3
  }
}
```

### أمثلة أخطاء

- **422** – من تواريخ غير صحيحة:
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "to_date": ["The to date must be a date after or equal to from date."]
  }
}
```

- **500** – خطأ داخلي:
```json
{
  "success": false,
  "message": "Error message from server."
}
```

---

## 2. قائمة الوحدات المباعة (مع بيانات العمولة)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/accounting/sold-units` |
| **Permission** | `accounting.sold-units.view` |

### Query Parameters

| Parameter           | Type    | Required | Description |
|--------------------|---------|----------|-------------|
| `project_id`       | integer | لا       | `contracts.id` |
| `from_date`        | string  | لا       | `Y-m-d`     |
| `to_date`          | string  | لا       | `Y-m-d` (≥ from_date) |
| `commission_source`| string  | لا       | `owner` \| `buyer` |
| `commission_status`| string  | لا       | `pending` \| `approved` \| `paid` |
| `per_page`         | integer | لا       | 1–100 (افتراضي 15) |

### مثال طلب

```http
GET /api/accounting/sold-units?commission_status=approved&per_page=20
Authorization: Bearer {token}
Accept: application/json
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم جلب الوحدات المباعة بنجاح",
  "data": [
    {
      "id": 101,
      "project_name": "مشروع النخيل",
      "unit_number": "A-101",
      "unit_type": "شقة",
      "final_selling_price": 500000,
      "proposed_price": 495000,
      "unit_price": 480000,
      "commission_percentage": 2.5,
      "commission_net": 12375,
      "team_responsible": "فريق المبيعات أ",
      "team": "فريق المبيعات أ",
      "commission_status": "approved",
      "commission_source": "owner",
      "commission_id": 5,
      "contract_id": 1,
      "contract_unit_id": 45,
      "confirmed_at": "2026-02-15 12:00:00",
      "client_name": "عميل تجريبي",
      "contract": { "id": 1, "project_name": "مشروع النخيل" },
      "contract_unit": { "id": 45, "unit_number": "A-101", "unit_type": "شقة", "price": 480000 },
      "commission": { "id": 5, "final_selling_price": 500000, "commission_percentage": 2.5, "net_amount": 12375, "status": "approved", "team_responsible": "فريق المبيعات أ" }
    }
  ],
  "meta": {
    "total": 50,
    "per_page": 20,
    "current_page": 1,
    "last_page": 3
  }
}
```

---

## 3. أنواع توزيع العمولة (للـ dropdown)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/accounting/commission-distribution-types` |
| **Permission** | `accounting.sold-units.view` |

لا يوجد query أو body.

### مثال طلب

```http
GET /api/accounting/commission-distribution-types
Authorization: Bearer {token}
Accept: application/json
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "data": {
    "types": [
      "lead_generation",
      "persuasion",
      "closing",
      "team_leader",
      "assistant_pm",
      "project_manager",
      "owner",
      "sales_manager",
      "projects_department",
      "management",
      "ceo",
      "external_marketer",
      "other"
    ],
    "type_labels": {
      "lead_generation": "عمولة الجلب",
      "persuasion": "عمولة الإقناع",
      "closing": "عمولة الإقفال",
      "team_leader": "قائد الفريق",
      "assistant_pm": "مساعد مدير مشروع",
      "project_manager": "مدير مشروع",
      "owner": "المالك",
      "sales_manager": "مدير المبيعات",
      "projects_department": "قسم المشاريع",
      "management": "الإدارة",
      "ceo": "CEO",
      "external_marketer": "مسوق خارجي",
      "other": "أخرى"
    }
  }
}
```

---

## 4. قائمة المسوقين/الموظفين (للـ dropdown التوزيع)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/accounting/marketers` |
| **Permission** | `accounting.sold-units.view` |

### مثال طلب

```http
GET /api/accounting/marketers
Authorization: Bearer {token}
Accept: application/json
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم جلب قائمة المسوقين بنجاح",
  "data": [
    { "id": 2, "name": "أحمد محمد" },
    { "id": 3, "name": "سارة علي" },
    { "id": 5, "name": "خالد إبراهيم" }
  ]
}
```

---

## 5. تفاصيل وحدة مباعة (لصفحة التوزيع)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/accounting/sold-units/{id}` |
| **Permission** | `accounting.sold-units.view` |

`{id}` = `sales_reservation_id` (الوحدة المؤكدة).

### مثال طلب

```http
GET /api/accounting/sold-units/101
Authorization: Bearer {token}
Accept: application/json
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم جلب بيانات الوحدة بنجاح",
  "data": {
    "id": 101,
    "project_name": "مشروع النخيل",
    "unit_number": "A-101",
    "unit_type": "شقة",
    "client_name": "عميل تجريبي",
    "contract_id": 1,
    "contract_unit_id": 45,
    "final_selling_price": 500000,
    "proposed_price": 495000,
    "unit_price": 480000,
    "commission_source": "owner",
    "commission_percentage": 2.5,
    "team_responsible": "فريق المبيعات أ",
    "commission_id": 5,
    "commission_status": "approved",
    "total_before_tax": 12500,
    "vat": 1875,
    "marketing_expenses": 0,
    "bank_fees": 125,
    "net_amount": 10500,
    "distributions": [
      {
        "id": 10,
        "type": "lead_generation",
        "type_label": "عمولة الجلب",
        "user_id": 2,
        "employee_name": "أحمد محمد",
        "external_name": null,
        "percentage": 40,
        "amount": 4200,
        "bank_account": null,
        "status": "pending",
        "notes": null
      },
      {
        "id": 11,
        "type": "persuasion",
        "type_label": "عمولة الإقناع",
        "user_id": 3,
        "employee_name": "سارة علي",
        "external_name": null,
        "percentage": 60,
        "amount": 6300,
        "bank_account": null,
        "status": "pending",
        "notes": null
      }
    ],
    "has_external_broker": false,
    "available_marketers": [
      { "id": 2, "name": "أحمد محمد" },
      { "id": 3, "name": "سارة علي" }
    ]
  }
}
```

---

## 6. إنشاء عمولة يدوياً

| | |
|---|---|
| **Method** | `POST` |
| **Path** | `/api/accounting/sold-units/{id}/commission` |
| **Permission** | `accounting.sold-units.manage` |

`{id}` = `sales_reservation_id`.

### Body (JSON)

| Field                 | Type    | Required | Description |
|-----------------------|---------|----------|-------------|
| `contract_unit_id`    | integer | نعم      | `contract_units.id` |
| `final_selling_price` | number  | نعم      | ≥ 0 |
| `commission_percentage` | number | نعم      | 0–100 |
| `commission_source`   | string  | نعم      | `owner` \| `buyer` |
| `team_responsible`    | string  | لا       | max 255 |
| `marketing_expenses`  | number  | لا       | ≥ 0 |
| `bank_fees`           | number  | لا       | ≥ 0 |

### مثال طلب

```http
POST /api/accounting/sold-units/101/commission
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

{
  "contract_unit_id": 45,
  "final_selling_price": 500000,
  "commission_percentage": 2.5,
  "commission_source": "owner",
  "team_responsible": "فريق المبيعات أ",
  "marketing_expenses": 0,
  "bank_fees": 125
}
```

### مثال رد ناجح (201)

```json
{
  "success": true,
  "message": "تم إنشاء العمولة بنجاح",
  "data": {
    "id": 6,
    "contract_unit_id": 45,
    "sales_reservation_id": 101,
    "final_selling_price": 500000,
    "commission_percentage": 2.5,
    "commission_source": "owner",
    "team_responsible": "فريق المبيعات أ",
    "status": "pending",
    "total_amount": 12500,
    "vat": 1875,
    "marketing_expenses": 0,
    "bank_fees": 125,
    "net_amount": 10500
  }
}
```

---

## 7. تحديث توزيعات العمولة

| | |
|---|---|
| **Method** | `PUT` |
| **Path** | `/api/accounting/commissions/{id}/distributions` |
| **Permission** | `accounting.sold-units.manage` |

`{id}` = `commission_id`. العمولة يجب أن تكون `status: pending`.

### Body (JSON)

| Field | Type  | Required | Description |
|-------|-------|----------|-------------|
| `distributions` | array | نعم | مصفوفة توزيعات واحدة على الأقل |
| `distributions[].type` | string | نعم | أحد أنواع `commission-distribution-types` |
| `distributions[].percentage` | number | نعم | 0–100؛ المجموع = 100 |
| `distributions[].user_id` | integer | لا | `users.id` (للموظف الداخلي) |
| `distributions[].external_name` | string | لا | للخارجي (مسوق خارجي) |
| `distributions[].bank_account` | string | لا | max 255 |
| `distributions[].notes` | string | لا | |

### مثال طلب

```http
PUT /api/accounting/commissions/5/distributions
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

{
  "distributions": [
    { "type": "lead_generation", "percentage": 40, "user_id": 2 },
    { "type": "persuasion", "percentage": 60, "user_id": 3 }
  ]
}
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم تحديث توزيعات العمولة بنجاح",
  "data": {
    "id": 5,
    "distributions": [
      { "id": 10, "type": "lead_generation", "user_id": 2, "percentage": 40, "amount": 4200, "status": "pending" },
      { "id": 11, "type": "persuasion", "user_id": 3, "percentage": 60, "amount": 6300, "status": "pending" }
    ]
  }
}
```

### 422 – مجموع النسب ≠ 100

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "distributions.0.percentage": ["..."],
    "distributions.1.type": ["The selected type is invalid."]
  }
}
```

---

## 8. الموافقة على توزيع عمولة (نزول العمولة + إشعار الموظف)

| | |
|---|---|
| **Method** | `POST` |
| **Path** | `/api/accounting/commissions/{id}/distributions/{distId}/approve` |
| **Permission** | `accounting.commissions.approve` |

عند النجاح يُرسل للموظف إشعار: "تم إرسال عمولة لك بمبلغ X - المشروع: Y - الوحدة: Z..."

لا يوجد body مطلوب.

### مثال طلب

```http
POST /api/accounting/commissions/5/distributions/10/approve
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

{}
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم الموافقة على توزيع العمولة بنجاح",
  "data": {
    "id": 10,
    "commission_id": 5,
    "user_id": 2,
    "type": "lead_generation",
    "percentage": 40,
    "amount": 4200,
    "status": "approved",
    "approved_at": "2026-03-08T12:00:00.000000Z",
    "user": { "id": 2, "name": "أحمد محمد" }
  }
}
```

### 400 – ليست pending

```json
{
  "success": false,
  "message": "Only pending distributions can be approved."
}
```

---

## 9. رفض توزيع عمولة

| | |
|---|---|
| **Method** | `POST` |
| **Path** | `/api/accounting/commissions/{id}/distributions/{distId}/reject` |
| **Permission** | `accounting.commissions.approve` |

### Body (JSON)

| Field  | Type   | Required | Description |
|--------|--------|----------|-------------|
| `notes` | string | لا       | max 1000    |

### مثال طلب

```http
POST /api/accounting/commissions/5/distributions/10/reject
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

{
  "notes": "تصحيح نسبة التوزيع"
}
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم رفض توزيع العمولة",
  "data": {
    "id": 10,
    "status": "rejected"
  }
}
```

---

## 10. ملخص العمولة (تبويب الملخص)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/accounting/commissions/{id}/summary` |
| **Permission** | `accounting.sold-units.view` |

`{id}` = `commission_id`.

### مثال طلب

```http
GET /api/accounting/commissions/5/summary
Authorization: Bearer {token}
Accept: application/json
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم جلب ملخص العمولة بنجاح",
  "data": {
    "commission_id": 5,
    "project_name": "مشروع النخيل",
    "unit_number": "A-101",
    "final_selling_price": 500000,
    "commission_percentage": 2.5,
    "commission_source": "owner",
    "team_responsible": "فريق المبيعات أ",
    "total_before_tax": 12500,
    "vat": 1875,
    "marketing_expenses": 0,
    "bank_fees": 125,
    "net_amount": 10500,
    "status": "approved",
    "distributions": [
      {
        "id": 10,
        "type": "lead_generation",
        "employee_name": "أحمد محمد",
        "bank_account": null,
        "percentage": 40,
        "amount": 4200,
        "status": "approved",
        "approved_at": "2026-03-01T10:00:00.000000Z"
      }
    ],
    "total_distributed_percentage": 100,
    "total_distributed_amount": 10500
  }
}
```

---

## 11. تأكيد دفع العمولة (صرف + إشعار الموظف)

| | |
|---|---|
| **Method** | `POST` |
| **Path** | `/api/accounting/commissions/{id}/distributions/{distId}/confirm` |
| **Permission** | `accounting.sold-units.manage` |

التوزيعة يجب أن تكون `status: approved`. عند النجاح يُرسل للموظف إشعار: "تم صرف وإرسال عمولة لك..."

لا يوجد body مطلوب.

### مثال طلب

```http
POST /api/accounting/commissions/5/distributions/10/confirm
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

{}
```

### مثال رد ناجح (200)

```json
{
  "success": true,
  "message": "تم تأكيد دفع العمولة بنجاح",
  "data": {
    "id": 10,
    "commission_id": 5,
    "user_id": 2,
    "type": "lead_generation",
    "percentage": 40,
    "amount": 4200,
    "status": "paid",
    "paid_at": "2026-03-08T12:05:00.000000Z"
  }
}
```

### 400

```json
{
  "success": false,
  "message": "Only approved distributions can be marked as paid."
}
```

أو:

```json
{
  "success": false,
  "message": "Distribution does not belong to this commission."
}
```

---

## ملخص للمطور الفرونت

| الاستخدام | الـ API |
|-----------|--------|
| **شاشة العمولات المُصرفة (المحاسبة)** | `GET /api/accounting/commissions/released` مع `from_date`, `to_date`, `per_page` |
| **قائمة الوحدات المباعة** | `GET /api/accounting/sold-units` مع فلاتر اختيارية |
| **قائمة أنواع العمولة (dropdown)** | `GET /api/accounting/commission-distribution-types` |
| **قائمة الموظفين (dropdown توزيع)** | `GET /api/accounting/marketers` |
| **تفاصيل وحدة + توزيعات** | `GET /api/accounting/sold-units/{reservationId}` |
| **إنشاء عمولة يدوياً** | `POST /api/accounting/sold-units/{reservationId}/commission` |
| **تحديث توزيعات** | `PUT /api/accounting/commissions/{commissionId}/distributions` |
| **اعتماد توزيعة (نزول عمولة + إشعار)** | `POST .../commissions/{id}/distributions/{distId}/approve` |
| **رفض توزيعة** | `POST .../commissions/{id}/distributions/{distId}/reject` |
| **ملخص العمولة** | `GET /api/accounting/commissions/{commissionId}/summary` |
| **تأكيد الصرف (صرف + إشعار)** | `POST .../commissions/{id}/distributions/{distId}/confirm` |

**توحيد الردود:**  
- النجاح: `{ "success": true, "message": "...", "data": ... }` وقد يُضاف `meta` للصفحات.  
- الخطأ: `{ "success": false, "message": "...", "errors": {...} }` عند 422.
