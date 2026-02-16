# Accounting Module – Functional Requirements vs API Mapping

مطابقة المتطلبات الوظيفية مع واجهات برمجة التطبيقات (API) لوحدة المحاسبة

---

## 3.1 التبويبة الأولى: Dashboard – لوحة التحكم

### مؤشرات الأداء (KPIs)

| المتطلب | API | الحالة | ملاحظات |
|---------|-----|--------|---------|
| عدد الوحدات المباعة | `GET /api/accounting/dashboard` → `units_sold` | ✅ | `AccountingDashboardService::getUnitsSold()` |
| إجمالي العربون المستلم | `total_received_deposits` | ✅ | `getTotalReceivedDeposits()` |
| إجمالي العربون المسترد | `total_refunded_deposits` | ✅ | `getTotalRefundedDeposits()` |
| إجمالي قيمة المشاريع المستلمة | `total_projects_value` | ✅ | `getTotalProjectsValue()` |
| إجمالي قيمة المبيعات (سعر البيع النهائي) | `total_sales_value` | ✅ | `getTotalSalesValue()` – يعتمد على `proposed_price` |

**Endpoint:** `GET /api/accounting/dashboard?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD`

**Response fields:** `units_sold`, `total_received_deposits`, `total_refunded_deposits`, `total_projects_value`, `total_sales_value`, `total_commissions`, `pending_commissions`, `approved_commissions`

---

## 3.2 التبويبة الثانية: الإشعارات

### أنواع الإشعارات

| المتطلب | API Type | الحالة |
|---------|----------|--------|
| تم حجز وحدة | `unit_reserved` | ✅ |
| تم استلام عربون | `deposit_received` | ✅ |
| تم إفراغ الوحدة | `unit_vacated` | ✅ |
| تم إلغاء الحجز | `reservation_cancelled` | ✅ |
| تم تأكيد عمولة | `commission_confirmed` | ✅ |
| تم استلام عمولة من المالك | `commission_received` | ✅ |

**Endpoints:**
- `GET /api/accounting/notifications` – قائمة الإشعارات (مع فلتر `type`)
- `POST /api/accounting/notifications/{id}/read` – تعليم كمقروء
- `POST /api/accounting/notifications/read-all` – تعليم الكل كمقروء

**Query params:** `from_date`, `to_date`, `status`, `type`, `per_page`

---

## 3.3 التبويبة الثالثة: الوحدات المباعة

### 3.3.1 بيانات الوحدة

| المتطلب | API / Model | الحالة |
|---------|-------------|--------|
| اسم المشروع | `contract.project_name` | ✅ |
| رقم الوحدة | `contractUnit.unit_number` | ✅ |
| نوع الوحدة | `contractUnit.unit_type` | ✅ |
| سعر البيع النهائي | `proposed_price` / `commission.final_selling_price` | ✅ |
| السعي (من المالك / المشتري) | `commission.commission_source` (owner/buyer) | ✅ |
| نسبة السعي | `commission.commission_percentage` | ✅ |
| الفريق المسؤول عن المشروع | `commission.team_responsible` | ✅ |

**Endpoints:**
- `GET /api/accounting/sold-units` – قائمة الوحدات المباعة
- `GET /api/accounting/sold-units/{id}` – تفاصيل وحدة مع العمولات

### 3.3.2 توزيع عمولات المسوقين – عملية الجلب (Lead Generation)

| المتطلب | API | الحالة |
|---------|-----|--------|
| أسماء المسوقين | `distributions[].user.name` أو `external_name` | ✅ |
| اسم الفريق | `user.team.name` | ✅ |
| إدخال نسبة العمولة | `distributions[].percentage` | ✅ |
| احتساب قيمة العمولة | `distributions[].amount` (محسوب تلقائياً) | ✅ |

**Type:** `lead_generation`

### 3.3.3 توزيع عمولات – عملية الإقناع (Persuasion)

| المتطلب | API | الحالة |
|---------|-----|--------|
| أسماء المسوقين | `distributions[].user.name` | ✅ |
| اسم الفريق | `user.team.name` | ✅ |
| إدخال نسبة العمولة | `distributions[].percentage` | ✅ |
| اشتراك أكثر من موظف | متعدد في نفس `type` | ✅ |
| زر تأكيد أو رفض | `POST .../approve`, `POST .../reject` | ✅ |

**Type:** `persuasion`

### 3.3.4 توزيع عمولات – عملية الإقفال (Closing)

| المتطلب | API | الحالة |
|---------|-----|--------|
| أسماء المسوقين | `distributions[].user.name` | ✅ |
| الفريق التابع | `user.team.name` | ✅ |
| إدخال نسبة العمولة | `distributions[].percentage` | ✅ |
| زر تأكيد أو رفض | `POST .../approve`, `POST .../reject` | ✅ |

**Type:** `closing`

### 3.3.5 توزيع عمولات الإدارة

| المتطلب (الدور) | API Type | الحالة |
|------------------|----------|--------|
| قائد فريق | `team_leader` | ✅ |
| مدير قسم السيلز | `sales_manager` | ✅ |
| مدير إدارة المشاريع | `project_manager` | ✅ |
| مسوق خارجي | `external_marketer` | ✅ |
| أخرى (إدخال يدوي) | `other` + `external_name` | ✅ |

**Endpoint:** `PUT /api/accounting/commissions/{id}/distributions`

**Body:** `distributions[]` with `type`, `percentage`, `user_id`, `external_name`, `bank_account`, `notes`

---

## 3.4 ملخص العمولة (Tab 4)

### عند العمولة من المالك أو المشتري

| المتطلب | API Field | الحالة |
|---------|-----------|--------|
| نسبة السعي = إجمالي العمولة قبل الضريبة | `total_before_tax` | ✅ |
| ضريبة القيمة المضافة | `vat` | ✅ |
| مصاريف التسويق | `marketing_expenses` | ✅ |
| رسوم البنك | `bank_fees` | ✅ |
| الصافي النهائي للتوزيع | `net_amount` | ✅ |

### جدول توزيع العمولات

| المتطلب | API | الحالة |
|---------|-----|--------|
| نوع العمولة | `distributions[].type` | ✅ |
| اسم الموظف / المسوق | `distributions[].employee_name` | ✅ |
| رقم الحساب البنكي | `distributions[].bank_account` | ✅ |
| النسبة | `distributions[].percentage` | ✅ |
| المبلغ بالريال | `distributions[].amount` | ✅ |
| زر تأكيد | `POST .../distributions/{distId}/confirm` | ✅ |

**Endpoint:** `GET /api/accounting/commissions/{id}/summary`

**عند التأكيد:** إشعار تلقائي للموظف: "تم تأكيد استحقاقك عمولة على الوحدة رقم (...)، مشروع (...)، نوع العمولة (...)" – ✅ `UserNotification` في `confirmCommissionPayment()`

---

## 3.5 التبويبة الخامسة: إدارة العربون والمتابعة

### 3.5.1 إدارة العربون

| المتطلب | API / Model | الحالة |
|---------|-------------|--------|
| اسم المشروع | `contract.project_name` | ✅ |
| نوع الوحدة | `contractUnit.unit_type` | ✅ |
| سعر الوحدة | `contractUnit.price` | ✅ |
| سعر البيع النهائي | `proposed_price` | ✅ |
| قيمة العربون | `deposit.amount` | ✅ |
| طريقة دفع العربون | `payment_method` | ✅ |
| اسم العميل | `client_name` | ✅ |
| تاريخ الدفع | `payment_date` | ✅ |
| نسبة السعي (من المالك/المشتري) | `commission_source` | ✅ |
| زر تأكيد استلام العربون | `POST /api/accounting/deposits/{id}/confirm` | ✅ |

**Endpoint:** `GET /api/accounting/deposits/pending`

### 3.5.2 المتابعة

| المتطلب | API | الحالة |
|---------|-----|--------|
| اسم المشروع | `contract.project_name` | ✅ |
| رقم الوحدة | `contractUnit.unit_number` | ✅ |
| اسم العميل | `client_name` | ✅ |
| إجمالي قيمة البيع | `proposed_price` | ✅ |
| نسبة السعي | `commission.commission_percentage` | ✅ |

**Endpoint:** `GET /api/accounting/deposits/follow-up`

### منطق العربون

| المتطلب | API / Logic | الحالة |
|---------|------------|--------|
| نسبة السعي من المالك → زر إرجاع العربون عند إفراغ الوحدة | `Deposit::isRefundable()` → `commission_source === 'owner'` | ✅ |
| نسبة السعي من المشتري → لا يمكن إرجاع العربون | `processRefund()` يرفض إذا `!isRefundable()` | ✅ |

**Endpoint:** `POST /api/accounting/deposits/{id}/refund`

### إصدار ملف مطالبة بنسبة السعي

| المتطلب | API | الحالة |
|---------|-----|--------|
| متاح فقط إذا نسبة السعي من المالك | ⚠️ | يفضل إضافة تحقق في `generateClaimFile` |
| اسم المشروع، رقم الوحدة، نوع الوحدة، سعر البيع النهائي، نسبة السعي | `generateClaimFile()` response | ✅ |
| زر تأكيد وصول العمولة | `POST .../commissions/{id}/distributions/{distId}/confirm` | ✅ |

**Endpoint:** `POST /api/accounting/deposits/claim-file/{reservationId}`

---

## 3.6 التبويبة السادسة: الرواتب وتوزيع العمولات

| المتطلب | API | الحالة |
|---------|-----|--------|
| اسم الموظف | `data[].name` | ✅ |
| الراتب حسب العقد (من HR) | `data[].base_salary` | ✅ |
| المسمى الوظيفي | `data[].job_title` | ✅ |
| نسبة العمولة (للسيلز) | `commission_eligibility` + تفاصيل من `salaries/{userId}` | ✅ |
| المشاريع المباعة | `GET /api/accounting/salaries/{userId}?month=&year=` | ✅ |
| عدد الوحدات | من `sold_units` | ✅ |
| سعر البيع النهائي لكل وحدة | `sold_units[].final_selling_price` | ✅ |
| نسبة العمولة من كل مشروع | `sold_units[].commission_percentage` | ✅ |
| صافي عمولة المسوق الشهرية | `total_commissions` | ✅ |

**Endpoints:**
- `GET /api/accounting/salaries?month=&year=` – قائمة الموظفين مع الرواتب والعمولات
- `GET /api/accounting/salaries/{userId}?month=&year=` – تفاصيل موظف مع الوحدات المباعة
- `POST /api/accounting/salaries/{userId}/distribute` – إنشاء توزيع راتب
- `POST /api/accounting/salaries/distributions/{id}/approve` – الموافقة
- `POST /api/accounting/salaries/distributions/{id}/paid` – تحديد كمدفوع

---

## ملخص نقاط التحقق

| التبويبة | الحالة | ملاحظات |
|----------|--------|---------|
| 3.1 Dashboard | ✅ | جميع KPIs متوفرة |
| 3.2 الإشعارات | ✅ | 6 أنواع مدعومة |
| 3.3 الوحدات المباعة | ✅ | جلب، إقناع، إقفال، إدارة، مسوق خارجي |
| 3.4 ملخص العمولة | ✅ | VAT، مصاريف، رسوم، صافي، إشعار عند التأكيد |
| 3.5 إدارة العربون والمتابعة | ✅ | منطق الإرجاع حسب مصدر السعي |
| 3.6 الرواتب | ✅ | راتب، عمولات شهرية، توزيع |

### تحسين مقترح

- **ملف المطالبة:** إضافة تحقق في `AccountingDepositService::generateClaimFile()` أن `commission_source === 'owner'` قبل السماح بإنشاء الملف، تماشياً مع المتطلب "متاح فقط إذا كانت نسبة السعي من المالك".
