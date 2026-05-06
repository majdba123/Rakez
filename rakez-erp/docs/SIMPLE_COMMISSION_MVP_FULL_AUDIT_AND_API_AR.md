# نظام العمولات المبسط — RAKEZ ERP
**وثيقة التدقيق الكاملة ومرجع الـ API**
> مُنشأة بتاريخ 2026-05-04 | تدقيق كامل مبني على الكود الفعلي في الفرع `yusuf` (تعديلات غير مُكوَّمة)

---

## 1. الملخص التنفيذي

### ماذا تم تنفيذه؟
نظام عمولات مبسط (Simple Commission MVP) مؤلف من 5 مراحل يمتد على قاعدة الكود الموجودة:

- **المرحلة 1** — مشاركو حجز المبيعات: ربط الموظفين المشاركين في كل صفقة (من أحضر العميل، من أقنعه، من أغلق الصفقة) بالحجز مع دعم الأوزان.
- **المرحلة 2** — إعدادات عمولة المشروع: جدول مركزي يحدد النسب المئوية لكل مشروع (buyer أو owner) مع تخصيص الأدوار الإدارية.
- **المرحلة 3** — المعاينة: حساب مسبق للعمولة وتوزيعها بدون كتابة في قاعدة البيانات.
- **المرحلة 4** — التوليد: حفظ العمولة والتوزيعات في قاعدة البيانات ضمن transaction واحدة مع حماية كاملة من الكسر.
- **المرحلة 5** — التوثيق واختبارات الضمان الشامل.

### لماذا النظام إضافي (Additive)؟
تم تصميم جميع التعديلات لتكون **إضافية فقط**:
- الأعمدة الجديدة على `commissions` و`commission_distributions` تحمل قيمًا افتراضية آمنة (nullable / default false).
- لا يُمس أي حقل قديم.
- قاعدة `commission_distributions.type` القديمة (enum) لم تُعدَّل ولا تزال تعمل.

### لماذا لا يلمس القديم؟
- الحجوزات والعمولات القديمة لديها `calculated_by_project_setting = false` (القيمة الافتراضية) — لا يملكها النظام الجديد.
- خدمة التوليد (`UnitCommissionGenerationService`) ترفض صراحةً استبدال أي عمولة لا تحمل `calculated_by_project_setting = true`.
- لا يعيد النظام حساب أي عمولة قديمة أو يعدّلها.

### كيف يرتبط الحجز بالمشاركين والمشروع والمحاسبة؟
```
SalesReservation ─── participantRecords ──► SalesReservationParticipant
        │
        ├─── contract ──► Contract (project_id) ──► projectCommissionSettings
        │                                          └─► activeProjectCommissionSetting
        │
        └─── commission ──► Commission (calculated_by_project_setting=true)
                                └─── distributions ──► CommissionDistribution (source_scope, source_type)
```

---

## 2. نطاق النظام

### ماذا يفعل النظام؟
- يسمح للمبيعات بتسجيل المشاركين في كل حجز مع تحديد أدوارهم (أحضر / أقنع / أغلق).
- يسمح للمحاسبة بإنشاء إعداد عمولة مشروع محدد (buyer أو owner) بنسب مئوية مخصصة لكل دور.
- يوفر معاينة فورية للعمولة قبل حفظها.
- يولد العمولة والتوزيعات تلقائيًا بناءً على المشاركين وإعدادات المشروع.

### ماذا لا يفعل النظام؟
| ما لا يفعله | السبب |
|---|---|
| ❌ لا يعيد حساب عمولات قديمة | الحماية من التعطل |
| ❌ لا يكمّل (backfill) بيانات قديمة | تجنب التعارض |
| ❌ لا يستبدل عمولات يدوية | حماية صريحة في الكود |
| ❌ لا يحذف توزيعات معتمدة أو مدفوعة | حماية صريحة |
| ❌ لا يستخدم Filament | يعتمد REST API فقط |

---

## 3. مراحل التنفيذ

### المرحلة 1 — مشاركو الحجز (Stage 1)

**الجداول الجديدة:**
- `sales_reservation_participants`

**الملفات الأساسية:**
| الملف | الدور |
|---|---|
| `database/migrations/2026_05_05_120000_create_sales_reservation_participants_table.php` | هيكل الجدول |
| `app/Models/SalesReservationParticipant.php` | الموديل |
| `app/Models/SalesReservation.php` (معدَّل) | إضافة `participantRecords()` |
| `app/Http/Requests/Sales/SyncReservationParticipantsRequest.php` | التحقق من المزامنة |
| `app/Http/Requests/Sales/Concerns/ValidatesReservationParticipants.php` | Trait مشترك |
| `app/Http/Requests/Sales/StoreReservationRequest.php` (معدَّل) | دعم `participants[]` عند الإنشاء |
| `app/Http/Controllers/Sales/SalesReservationController.php` (معدَّل) | 3 actions جديدة |
| `app/Http/Resources/Sales/SalesReservationParticipantResource.php` | مورد الاستجابة |
| `app/Policies/SalesReservationPolicy.php` (معدَّل) | `viewParticipants`, `manageParticipants` |

**Endpoints:**
- `GET /api/sales/reservations/eligible-participants`
- `GET /api/sales/reservations/{reservation}/participants`
- `PUT /api/sales/reservations/{reservation}/participants`
- `POST /api/sales/reservations` (مع `participants[]` اختياري)

**Tests:**
- `tests/Feature/Sales/SalesReservationParticipantsStage1Test.php`

---

### المرحلة 2 — إعدادات عمولة المشروع (Stage 2)

**الجداول الجديدة:**
- `project_commission_settings`

**الملفات الأساسية:**
| الملف | الدور |
|---|---|
| `database/migrations/2026_05_06_100000_create_project_commission_settings_table.php` | هيكل الجدول |
| `app/Models/ProjectCommissionSetting.php` | الموديل |
| `app/Models/Contract.php` (معدَّل) | `projectCommissionSettings()` و`activeProjectCommissionSetting()` |
| `app/Services/Accounting/ProjectCommissionSettingService.php` | منطق الإنشاء/التعديل/التفعيل |
| `app/Http/Controllers/Accounting/ProjectCommissionSettingController.php` | الـ Controller |
| `app/Http/Requests/Accounting/StoreProjectCommissionSettingRequest.php` | التحقق عند الإنشاء |
| `app/Http/Requests/Accounting/UpdateProjectCommissionSettingRequest.php` | التحقق عند التعديل |
| `app/Http/Requests/Accounting/Concerns/ValidatesProjectCommissionSettingPayload.php` | Trait مشترك |
| `app/Http/Resources/Accounting/ProjectCommissionSettingResource.php` | مورد الاستجابة |

**Endpoints:**
- `GET /api/accounting/project-commission-settings`
- `POST /api/accounting/project-commission-settings`
- `GET /api/accounting/project-commission-settings/{projectCommissionSetting}`
- `PUT /api/accounting/project-commission-settings/{projectCommissionSetting}`
- `POST /api/accounting/project-commission-settings/{projectCommissionSetting}/activate`

**Tests:**
- `tests/Feature/Accounting/ProjectCommissionSettingStage2Test.php`

---

### المرحلة 3 — المعاينة (Stage 3)

**لا جداول جديدة — قراءة فقط.**

**الملفات الأساسية:**
| الملف | الدور |
|---|---|
| `app/Services/Accounting/ProjectCommissionCalculator.php` | المعادلات |
| `app/Services/Accounting/UnitPriceResolver.php` | حل سعر الوحدة |
| `app/Services/Accounting/UnitCommissionPreviewService.php` | معاينة الوحدة |
| `app/Services/Accounting/ProjectCommissionPreviewService.php` | معاينة المشروع |
| `app/Http/Controllers/Accounting/ProjectCommissionPreviewController.php` | الـ Controller |
| `app/Http/Requests/Accounting/PreviewProjectCommissionRequest.php` | التحقق |
| `app/Http/Requests/Accounting/PreviewUnitCommissionRequest.php` | التحقق |

**Endpoints:**
- `POST /api/accounting/projects/{project}/preview-commission`
- `POST /api/accounting/reservations/{reservation}/preview-unit-commission`

**Tests:**
- `tests/Unit/Services/ProjectCommissionCalculatorTest.php`
- `tests/Unit/Services/UnitPriceResolverTest.php`
- `tests/Feature/Accounting/ProjectCommissionPreviewStage3Test.php`

---

### المرحلة 4 — التوليد (Stage 4)

**أعمدة إضافية على جداول قائمة:**

**الملفات الأساسية:**
| الملف | الدور |
|---|---|
| `database/migrations/2026_05_12_120000_stage4_additive_commission_and_distribution_columns.php` | الأعمدة الجديدة |
| `app/Services/Accounting/UnitCommissionGenerationService.php` | منطق التوليد |
| `app/Services/Accounting/CommissionDistributionLegacyTypeMapper.php` | تعيين legacy type |
| `app/Http/Controllers/Accounting/UnitCommissionGenerationController.php` | الـ Controller |
| `app/Http/Requests/Accounting/GenerateUnitCommissionRequest.php` | التحقق والتصريح |
| `app/Models/Commission.php` (معدَّل) | أعمدة جديدة + علاقة |
| `app/Models/CommissionDistribution.php` (معدَّل) | `source_scope`, `source_type` |

**Endpoint:**
- `POST /api/accounting/reservations/{reservation}/generate-unit-commission`

**Tests:**
- `tests/Feature/Accounting/UnitCommissionGenerationStage4Test.php`

---

### المرحلة 5 — التوثيق واختبارات الضمان (Stage 5)

**الملفات الأساسية:**
| الملف | الدور |
|---|---|
| `docs/SIMPLE_COMMISSION_MVP_API.md` | مرجع API باللغة الإنجليزية |
| `tests/Feature/Accounting/SimpleCommissionMvpStage5Test.php` | اختبارات E2E ومراجعة QA |

---

## 4. المعادلات المعتمدة

### مصدر العمولة: المشتري (`commission_source = buyer`)

```
commission_amount = unit_price × commission_percentage ÷ 100
```

**مثال:** سعر الوحدة = 1,000,000 ريال، النسبة = 2.5%
```
commission_amount = 1,000,000 × 2.5 ÷ 100 = 25,000 ريال
```

**مفتاح المعادلة المحفوظ:** `buyer_unit_price_times_rate`

---

### مصدر العمولة: المالك / المطوّر (`commission_source = owner`)

```
commission_amount = unit_price ÷ (1 + commission_percentage ÷ 100)
```

**مثال:** سعر الوحدة = 1,000,000 ريال، النسبة = 2.5%
```
commission_amount = 1,000,000 ÷ (1 + 2.5 ÷ 100)
                  = 1,000,000 ÷ 1.025
                  = 975,609.76 ريال
```

> ⚠️ **تنبيه مهم:** الناتج هو مبلغ العمولة مباشرةً. **لا يُطرح** من سعر الوحدة. هذه ليست معادلة `unit_price - (unit_price × rate)`.
>
> الكود في `app/Services/Accounting/ProjectCommissionCalculator.php`:
> ```php
> $commissionAmount = round($amount / $denominator, 2);
> ```

**مفتاح المعادلة المحفوظ:** `owner_unit_price_divided_by_100_percent_plus_rate`

---

### مكتبة المعادلات في الكود

الملف: `app/Services/Accounting/ProjectCommissionCalculator.php`

| Method | النسبة | المعادلة |
|---|---|---|
| `calculateBuyer(float $amount, float $percentage)` | buyer | `round($amount * $pct / 100, 2)` |
| `calculateOwner(float $amount, float $percentage)` | owner | `round($amount / (1 + $pct/100), 2)` |
| `calculate(string $source, float $amount, float $percentage)` | الاثنان | dispatcher |

---

### أولوية حل سعر الوحدة (`UnitPriceResolver`)

الملف: `app/Services/Accounting/UnitPriceResolver.php`

| الأولوية | المصدر | الشرط |
|---|---|---|
| 1 | `commissions.final_selling_price` | `> 0` |
| 2 | `sales_reservations.proposed_price` | `> 0` |
| 3 | `contract_units.price` | `> 0` |
| 4 | خطأ `UnitPriceUnresolvedException` | إذا فشل الكل |

---

## 5. الجداول والتعديلات

### 5.1 جدول `sales_reservation_participants` (جديد)

**Migration:** `database/migrations/2026_05_05_120000_create_sales_reservation_participants_table.php`

| العمود | النوع | الغرض |
|---|---|---|
| `id` | bigint PK | مفتاح أساسي |
| `sales_reservation_id` | FK → `sales_reservations` | الحجز المرتبط (cascade delete) |
| `user_id` | FK → `users` | المستخدم المشارك |
| `did_bring` | boolean (default false) | هل أحضر العميل؟ |
| `did_convince` | boolean (default false) | هل أقنع العميل؟ |
| `did_close` | boolean (default false) | هل أغلق الصفقة؟ |
| `weight` | decimal(10,2) (default 1) | وزن المشاركة في التوزيع |
| `notes` | text nullable | ملاحظات |
| `created_by` | FK → `users` nullable (nullOnDelete) | من أضافه |
| `timestamps` | — | created_at, updated_at |

**القيود الفريدة:** `sr_participants_reservation_user_unique (sales_reservation_id, user_id)` — اسم مختصر لتجنب تجاوز حد MySQL البالغ 64 حرفًا.

---

### 5.2 جدول `project_commission_settings` (جديد)

**Migration:** `database/migrations/2026_05_06_100000_create_project_commission_settings_table.php`

| العمود | النوع | الغرض |
|---|---|---|
| `id` | bigint PK | مفتاح أساسي |
| `project_id` | FK → `contracts` (restrictOnDelete) | المشروع |
| `commission_source` | string | `buyer` أو `owner` |
| `commission_percentage` | decimal(8,2) | نسبة العمولة الإجمالية |
| `assigned_bring_percentage` | decimal(8,2) default 0 | نسبة "أحضر" لفريق المشروع |
| `assigned_convince_percentage` | decimal(8,2) default 0 | نسبة "أقنع" لفريق المشروع |
| `assigned_close_percentage` | decimal(8,2) default 0 | نسبة "أغلق" لفريق المشروع |
| `outside_bring_percentage` | decimal(8,2) default 0 | نسبة "أحضر" لخارج الفريق |
| `outside_convince_percentage` | decimal(8,2) default 0 | نسبة "أقنع" لخارج الفريق |
| `outside_close_percentage` | decimal(8,2) default 0 | نسبة "أغلق" لخارج الفريق |
| `ceo_user_id` | FK → `users` nullable | المدير التنفيذي |
| `ceo_percentage` | decimal(8,2) default 0 | نسبة المدير التنفيذي |
| `sales_manager_user_id` | FK → `users` nullable | مدير المبيعات |
| `sales_manager_percentage` | decimal(8,2) default 0 | نسبة مدير المبيعات |
| `sales_leader_user_id` | FK → `users` nullable | قائد المبيعات |
| `sales_leader_percentage` | decimal(8,2) default 0 | نسبة قائد المبيعات |
| `group_leader_user_id` | FK → `users` nullable | قائد المجموعة |
| `group_leader_percentage` | decimal(8,2) default 0 | نسبة قائد المجموعة |
| `external_marketer_user_id` | FK → `users` nullable | المسوق الخارجي |
| `external_marketer_percentage` | decimal(8,2) default 0 | نسبة المسوق الخارجي |
| `is_active` | boolean default true | هل الإعداد نشط؟ |
| `created_by` | FK → `users` nullable | من أنشأه |
| `timestamps` | — | created_at, updated_at |

**قيد الأعمدة الخارجية:** جميع أسماء FK مختصرة صراحةً (`pcs_project_id_fk`، إلخ) لتجنب حد MySQL البالغ 64 حرفًا.

**قيد:** إعداد واحد فعال لكل مشروع — يُنفَّذ برمجيًا في `ProjectCommissionSettingService::deactivateOthersOnProject()`.

---

### 5.3 أعمدة إضافية على جدول `commissions`

**Migration:** `database/migrations/2026_05_12_120000_stage4_additive_commission_and_distribution_columns.php`

| العمود الجديد | النوع | الغرض |
|---|---|---|
| `project_commission_setting_id` | FK → `project_commission_settings` nullable (nullOnDelete) | الإعداد المُستخدم في التوليد |
| `calculation_formula_key` | string nullable | `buyer_unit_price_times_rate` أو `owner_unit_price_divided_by_100_percent_plus_rate` |
| `calculated_by_project_setting` | boolean default **false** | هل وُلِّدت بواسطة النظام الجديد؟ |

> القيمة الافتراضية `false` تضمن أن العمولات القديمة ليست مملوكة للنظام الجديد.

---

### 5.4 أعمدة إضافية على جدول `commission_distributions`

| العمود الجديد | النوع | الغرض |
|---|---|---|
| `source_scope` | string nullable | `assigned_project_team` أو `outside_project_team` أو `management` |
| `source_type` | string nullable | `bring`، `convince`، `close`، `ceo`، `sales_manager`، إلخ |

> **ملاحظة:** لا يُعدَّل `commission_distributions.type` (الـ enum القديم). يبقى كما هو للتوافق مع الأنظمة القديمة.

---

## 6. العلاقات بين الموديلات

### `SalesReservation` → `participantRecords`

```php
// app/Models/SalesReservation.php
public function participantRecords(): HasMany
{
    return $this->hasMany(SalesReservationParticipant::class);
}
```
حجز ← مشاركون متعددون.

---

### `SalesReservationParticipant` → العلاقات

```php
// app/Models/SalesReservationParticipant.php
public function reservation(): BelongsTo  // → SalesReservation
public function user(): BelongsTo         // → User
public function creator(): BelongsTo      // → User (created_by)
```

---

### `Contract` → `projectCommissionSettings` / `activeProjectCommissionSetting`

```php
// app/Models/Contract.php
public function projectCommissionSettings(): HasMany
{
    return $this->hasMany(ProjectCommissionSetting::class, 'project_id');
}

public function activeProjectCommissionSetting(): HasOne
{
    return $this->hasOne(ProjectCommissionSetting::class, 'project_id')
        ->where('is_active', true);
}
```

---

### `ProjectCommissionSetting` → العلاقات

```php
// app/Models/ProjectCommissionSetting.php
public function project(): BelongsTo       // → Contract
public function creator(): BelongsTo       // → User
public function ceoUser(): BelongsTo       // → User
public function salesManagerUser(): BelongsTo
public function salesLeaderUser(): BelongsTo
public function groupLeaderUser(): BelongsTo
public function externalMarketerUser(): BelongsTo
public function commissions(): HasMany     // → Commission
```

---

### `Commission` → `projectCommissionSetting`

```php
// app/Models/Commission.php
public function projectCommissionSetting(): BelongsTo
{
    return $this->belongsTo(ProjectCommissionSetting::class, 'project_commission_setting_id');
}
```

---

### `CommissionDistribution` → `source_scope` / `source_type`

الحقلان `source_scope` و`source_type` هما الحقلان الأساسيان للتقارير في النظام الجديد — يُخزَّنان كـ string nullable في قاعدة البيانات، مُدرَجان في `$fillable`.

---

## 7. شرح الـ APIs

> جميع المسارات تبدأ بـ `/api`. المصادقة: **Sanctum Bearer Token**.

---

### 7.1 المبيعات — مشاركو الحجز

#### GET `/api/sales/reservations/eligible-participants`
**الصلاحية:** `permission:sales.reservations.view`
**الدور:** الحصول على قائمة المستخدمين المؤهلين للإضافة كمشاركين في الحجوزات.
**Controller:** `SalesReservationController@eligibleParticipants`

**طلب:** لا يوجد body. يمكن إرسال query params للتصفية.

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Ahmed Ali",
      "type": "sales",
      "team_id": 1
    }
  ]
}
```

**ملاحظة:** هذا المسار مسجَّل **قبل** `reservations/{id}` في `routes/api.php` (السطر 486 قبل 494) لتجنب التعارض في مسارات Laravel.

---

#### GET `/api/sales/reservations/{reservation}/participants`
**الصلاحية:** `permission:sales.reservations.view` + Policy `viewParticipants`
**الدور:** استرجاع قائمة المشاركين الحاليين لحجز معين.
**Controller:** `SalesReservationController@participantsIndex`

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "did_bring": true,
      "did_convince": false,
      "did_close": true,
      "weight": "1.00",
      "notes": "أحضر وأغلق الصفقة",
      "user": { "id": 2, "name": "Ahmed Ali" }
    }
  ]
}
```

**أخطاء محتملة:**
- `403` — غير مصرَّح بعرض المشاركين
- `404` — الحجز غير موجود

---

#### PUT `/api/sales/reservations/{reservation}/participants`
**الصلاحية:** Policy `manageParticipants` (يتحقق منها `SyncReservationParticipantsRequest::authorize()`)
**الدور:** مزامنة قائمة المشاركين (استبدال كاملة أو جزئية).
**Controller:** `SalesReservationController@participantsSync`
**Request:** `SyncReservationParticipantsRequest`

**Body:**
```json
{
  "participants": [
    {
      "user_id": 3,
      "did_bring": true,
      "did_convince": true,
      "did_close": false,
      "weight": 1,
      "notes": "مشارك نشط"
    }
  ]
}
```

**قواعد التحقق (من `ValidatesReservationParticipants` trait):**
- `participants.*.user_id` — مطلوب، `distinct` (رفض التكرار)، موجود في `users`
- `participants.*.did_bring/did_convince/did_close` — boolean اختياري
- `participants.*.weight` — رقمي ≥ 0
- `participants.*.notes` — نص

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "message": "Participants synced.",
  "data": [...]
}
```

**أخطاء محتملة:**
- `403` — غير مصرَّح
- `422` — user_id مكرر، أو غير موجود في قاعدة البيانات

---

#### POST `/api/sales/reservations`
**الصلاحية:** `permission:sales.reservations.create`
**الدور:** إنشاء حجز جديد مع دعم إرسال `participants[]` اختياريًا.
**Controller:** `SalesReservationController@store`
**Request:** `StoreReservationRequest`

**الحقول المطلوبة الأساسية:**
```json
{
  "contract_id": 1,
  "contract_unit_id": 1,
  "contract_date": "2025-01-25",
  "reservation_type": "confirmed_reservation",
  "client_name": "أحمد علي",
  "client_mobile": "0501234567",
  "client_nationality": "Saudi",
  "client_iban": "SA0000000000000000000000",
  "payment_method": "bank_transfer",
  "down_payment_amount": 100000,
  "down_payment_status": "refundable",
  "purchase_mechanism": "cash",
  "participants": [
    {
      "user_id": 3,
      "did_bring": true,
      "did_convince": false,
      "did_close": true,
      "weight": 1,
      "notes": "أغلق الصفقة"
    }
  ]
}
```

> **ملاحظة:** الحقول المطلوبة قد تختلف بحسب نوع المشروع. راجع `StoreReservationRequest.php` للمواصفة الكاملة.

**استجابة ناجحة (201):**
```json
{ "success": true, "data": { "id": 5, ... } }
```

---

### 7.2 المحاسبة — إعدادات عمولة المشروع

**Middleware عام:** `auth:sanctum, role:accounting|admin`

---

#### GET `/api/accounting/project-commission-settings`
**الصلاحية:** `accounting.sold-units.view` أو `accounting.sold-units.manage` (يُتحقق في Controller)
**الدور:** استرجاع قائمة الإعدادات مع دعم التصفية والصفحات.
**Controller:** `ProjectCommissionSettingController@index`

**Query Params:**
- `project_id` — (اختياري) تصفية بمعرف المشروع
- `per_page` — (اختياري، 1-100، افتراضي 15)

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 2
  }
}
```

---

#### POST `/api/accounting/project-commission-settings`
**الصلاحية:** `accounting.sold-units.manage`
**الدور:** إنشاء إعداد عمولة جديد لمشروع. عند التفعيل تلقائيًا يُعطَّل غيره.
**Controller:** `ProjectCommissionSettingController@store`
**Request:** `StoreProjectCommissionSettingRequest`

**Body (buyer):**
```json
{
  "project_id": 1,
  "commission_source": "buyer",
  "commission_percentage": 2.5,
  "assigned_bring_percentage": 10,
  "assigned_convince_percentage": 10,
  "assigned_close_percentage": 10,
  "outside_bring_percentage": 5,
  "outside_convince_percentage": 5,
  "outside_close_percentage": 5,
  "ceo_user_id": 4,
  "ceo_percentage": 2,
  "sales_manager_user_id": 5,
  "sales_manager_percentage": 3,
  "sales_leader_user_id": 6,
  "sales_leader_percentage": 2,
  "group_leader_user_id": 7,
  "group_leader_percentage": 2,
  "external_marketer_user_id": 8,
  "external_marketer_percentage": 1,
  "is_active": true
}
```

**قواعد التحقق الهامة:**
- `commission_source` — مطلوب، `in:buyer,owner` فقط
- `assigned_*` و`outside_*` — nullable، رقمي، 0-100
- جميع النسب المئوية للتوزيع مجتمعةً ≤ 100
- إذا كانت `xxx_percentage > 0` فيجب إرسال `xxx_user_id`

**استجابة ناجحة (201):**
```json
{
  "success": true,
  "message": "Project commission setting created.",
  "data": { "id": 1, "project_id": 1, "is_active": true, ... }
}
```

**أخطاء محتملة:**
- `422` — مجموع النسب يتجاوز 100، أو `user_id` مطلوب

---

#### GET `/api/accounting/project-commission-settings/{projectCommissionSetting}`
**الصلاحية:** `accounting.sold-units.view` أو `manage`
**الدور:** عرض إعداد واحد مع تفاصيل المستخدمين.
**Controller:** `ProjectCommissionSettingController@show`

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "project": { "id": 1, "project_name": "مشروع الرياض" },
    "commission_source": "buyer",
    "commission_percentage": "2.50",
    "is_active": true,
    "ceo_user": { "id": 4, "name": "محمد" },
    ...
  }
}
```

---

#### PUT `/api/accounting/project-commission-settings/{projectCommissionSetting}`
**الصلاحية:** `accounting.sold-units.manage`
**الدور:** تعديل إعداد قائم. جميع الحقول `sometimes` (تعديل جزئي مدعوم).
**Controller:** `ProjectCommissionSettingController@update`

**Body:** نفس حقول الإنشاء مع `sometimes` بدلًا من `required`.

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "message": "Project commission setting updated.",
  "data": { ... }
}
```

---

#### POST `/api/accounting/project-commission-settings/{projectCommissionSetting}/activate`
**الصلاحية:** `accounting.sold-units.manage`
**الدور:** تفعيل إعداد وتعطيل جميع إعدادات المشروع الأخرى.
**Controller:** `ProjectCommissionSettingController@activate`
**Body:** فارغ

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "message": "Project commission setting activated.",
  "data": { "is_active": true, ... }
}
```

---

### 7.3 المحاسبة — معاينة عمولة المشروع

#### POST `/api/accounting/projects/{project}/preview-commission`
**الصلاحية:** `accounting.sold-units.view` أو `manage` (في `PreviewProjectCommissionRequest::authorize()`)
**الدور:** حساب العمولة على مستوى المشروع بمبلغ افتراضي — لا يُكتب في قاعدة البيانات.
**Controller:** `ProjectCommissionPreviewController@previewProject`
**Service:** `ProjectCommissionPreviewService::previewProject()`

**Body:**
```json
{
  "base_amount": 1000000
}
```

> **⚠️ ملاحظة:** `base_amount` nullable في قواعد الـ Form Request، لكنه **مطلوب عمليًا** — الخدمة ترمي `422` إذا كان `null` أو ≤ 0.

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "data": {
    "project_id": 1,
    "base_amount": 1000000.00,
    "commission_source": "buyer",
    "commission_percentage": 2.5,
    "formula_key": "buyer_unit_price_times_rate",
    "project_commission_amount": 25000.00
  }
}
```

**أخطاء محتملة:**
- `422` — لا يوجد إعداد نشط للمشروع (`project_commission_setting`)
- `422` — `base_amount` مفقود أو ≤ 0

---

### 7.4 المحاسبة — معاينة عمولة الوحدة

#### POST `/api/accounting/reservations/{reservation}/preview-unit-commission`
**الصلاحية:** `accounting.sold-units.view` أو `manage`
**الدور:** حساب توزيع العمولة على مستوى الوحدة/الحجز — لا يُكتب في قاعدة البيانات.
**Controller:** `ProjectCommissionPreviewController@previewUnit`
**Service:** `UnitCommissionPreviewService::preview()`

**Body (اختياري):**
```json
{
  "override_unit_price": 100000
}
```

> إذا أُرسل `override_unit_price > 0` يُستخدم بدلًا من `UnitPriceResolver`. غير محفوظ.

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "data": {
    "reservation_id": 1,
    "project_id": 1,
    "unit_price": 500000.00,
    "commission_source": "buyer",
    "commission_percentage": 2.5,
    "formula_key": "buyer_unit_price_times_rate",
    "unit_commission_amount": 12500.00,
    "distributions": [
      {
        "user_id": 2,
        "user": { "id": 2, "name": "Ahmed" },
        "source_scope": "assigned_project_team",
        "source_type": "bring",
        "percentage": 10.0,
        "amount": 1250.00
      }
    ],
    "unresolved": [
      {
        "source_scope": "outside_project_team",
        "source_type": "convince",
        "percentage": 5.0,
        "amount": 625.00,
        "reason": "no_matching_participants"
      }
    ],
    "total_distributed": 8750.00,
    "remaining_amount": 3125.00
  }
}
```

**أخطاء محتملة:**
- `422` — لا إعداد نشط (`project_commission_setting`)
- `422` — لا يمكن حل سعر الوحدة (`unit_price`)

---

### 7.5 المحاسبة — توليد عمولة الوحدة

#### POST `/api/accounting/reservations/{reservation}/generate-unit-commission`
**الصلاحية:** `accounting.sold-units.manage` (في Route middleware **و** `GenerateUnitCommissionRequest::authorize()`)
**الدور:** حفظ العمولة والتوزيعات في قاعدة البيانات ضمن transaction.
**Controller:** `UnitCommissionGenerationController@generate`
**Service:** `UnitCommissionGenerationService::generate()`

**Body:** فارغ `{}`

**استجابة ناجحة (200):**
```json
{
  "success": true,
  "message": "Unit commission generated successfully.",
  "data": {
    "commission": {
      "id": 10,
      "reservation_id": 1,
      "contract_unit_id": 1,
      "final_selling_price": 500000.00,
      "commission_source": "buyer",
      "commission_percentage": 2.5,
      "total_amount": 12500.00,
      "net_amount": 12500.00,
      "calculation_formula_key": "buyer_unit_price_times_rate",
      "calculated_by_project_setting": true,
      "project_commission_setting_id": 1
    },
    "distributions": [
      {
        "user_id": 2,
        "percentage": 10.0,
        "amount": 1250.00,
        "source_scope": "assigned_project_team",
        "source_type": "bring"
      }
    ],
    "unresolved": [],
    "total_distributed": 12500.00,
    "remaining_amount": 0.00
  }
}
```

**أخطاء محتملة:**
- `422` — عمولة legacy موجودة (`commission` key في `errors`)
- `422` — عمولة project-generated غير pending (`commission` key)
- `422` — توزيعات غير pending موجودة (`commission` key)
- `422` — لا إعداد نشط أو سعر وحدة غير قابل للحل

---

### 7.6 Legacy — الـ API القديم (لا يزال يعمل)

#### POST `/api/accounting/sold-units/{id}/commission`
**الصلاحية:** `accounting.sold-units.manage`
**الدور:** إنشاء عمولة يدوية بالطريقة القديمة — بدون ربط بإعدادات المشروع.
**Controller:** `AccountingCommissionController@createManual`

**Body:**
```json
{
  "final_selling_price": 500000,
  "commission_percentage": 2.5,
  "commission_source": "buyer"
}
```

> **⚠️ مهم:** هذه العمولات لها `calculated_by_project_setting = false` (الافتراضي). لا يمكن لنظام التوليد الجديد استبدالها.

---

## 8. شرح الـ Flow الكامل

```
1. المبيعات تُنشئ حجزًا
   POST /api/sales/reservations
   (مع participants[] اختياريًا)
         │
         ▼
2. الإدارة تنشئ إعداد عمولة مشروع نشطًا
   POST /api/accounting/project-commission-settings
         │
         ▼
3. المحاسبة تعاين العمولة (اختياري)
   POST /api/accounting/reservations/{id}/preview-unit-commission
   ← لا يُكتب شيء في قاعدة البيانات
         │
         ▼
4. المحاسبة تُولِّد العمولة
   POST /api/accounting/reservations/{id}/generate-unit-commission
   ← يُكتب في commissions + commission_distributions
         │
         ▼
5. المحاسبة تتابع الاعتماد والدفع (الـ flow القديم)
   PUT /api/accounting/commissions/{id}/distributions
   POST /api/accounting/commissions/{id}/distributions/{distId}/approve
   POST /api/accounting/commissions/{id}/distributions/{distId}/confirm
```

---

## 9. `source_scope` / `source_type` مقابل الـ `type` القديم

### الحقلان الأساسيان للنظام الجديد

| الحقل | مكانه | الغرض |
|---|---|---|
| `commission_distributions.source_scope` | `commission_distributions` | نطاق المشارك: `assigned_project_team`، `outside_project_team`، `management` |
| `commission_distributions.source_type` | `commission_distributions` | نوع الدور: `bring`، `convince`، `close`، `ceo`، `sales_manager`، إلخ |

**الحقل القديم للتوافق:**

| الحقل | مكانه | الغرض |
|---|---|---|
| `commission_distributions.type` | `commission_distributions` | enum قديم للتوافق مع الـ flow الحالي |

> **التقارير والواجهة الأمامية:** استخدم `source_scope` و`source_type` للنظام الجديد. `type` للتوافق القديم فقط.

---

### جدول التعيين (Mapping)

**مشارك في bucket:**

| `source_scope` | `source_type` | `commission_distributions.type` |
|---|---|---|
| `assigned_project_team` أو `outside_project_team` | `bring` | `lead_generation` |
| `assigned_project_team` أو `outside_project_team` | `convince` | `persuasion` |
| `assigned_project_team` أو `outside_project_team` | `close` | `closing` |

**إدارة (management):**

| `source_type` | `commission_distributions.type` | ملاحظة |
|---|---|---|
| `ceo` | `project_manager` | لا يوجد `ceo` في الـ enum القديم |
| `sales_manager` | `sales_manager` | مطابق |
| `sales_leader` | `project_manager` | لا يوجد `management` في الـ enum القديم |
| `group_leader` | `team_leader` | — |
| `external_marketer` | `external_marketer` | مطابق |
| أي شيء آخر | `other` | fallback |

**كود التعيين:** `app/Services/Accounting/CommissionDistributionLegacyTypeMapper.php`

---

## 10. الحماية من كسر القديم

### الحقول الواقية

| الحقل | الموديل | الغرض |
|---|---|---|
| `calculated_by_project_setting` | `Commission` | تمييز العمولات المولَّدة تلقائيًا عن اليدوية |
| `project_commission_setting_id` | `Commission` | ربط بالإعداد المستخدم |

### الحماية من الاستبدال اليدوي

```
UnitCommissionGenerationService::assertReservationAllowsGeneration()
  ├── إذا وُجدت عمولة موجودة مع calculated_by_project_setting ≠ true → 422
  ├── إذا كانت العمولة غير pending → 422
  └── إذا كان أي توزيع غير pending → 422
```

### الحماية من حذف التوزيعات المعتمدة

```php
// يحذف فقط التوزيعات pending عند إعادة التوليد:
$commission->distributions()->where('status', 'pending')->delete();
```

التوزيعات بحالة `approved` أو `paid` **لا تُحذف أبدًا**.

### القيمة الافتراضية الآمنة

```
calculated_by_project_setting DEFAULT false
```

كل العمولات القديمة تبقى بـ `false` — محمية تلقائيًا.

---

## 11. التخصيصات غير المحلولة (Unresolved Allocations)

### ما المقصود بـ unresolved؟
عندما يُخصَّص bucket معين (مثل `assigned_project_team|bring`) بنسبة مئوية > 0 في إعداد المشروع، لكن لا يوجد مشارك مطابق في الحجز.

### متى يظهر؟
- `assigned_bring_percentage > 0` لكن لا أحد من فريق المشروع حدد `did_bring = true`
- أي bucket فيه نسبة > 0 بدون مشاركين مطابقين

### ما الذي يحدث به؟
```
preview → يُرجَع في مصفوفة unresolved (لا يُحفظ)
generate → يُرجَع في JSON response (لا يُحفظ في commission_distributions)
```

**البيانات المُرجَعة:**
```json
{
  "source_scope": "outside_project_team",
  "source_type": "convince",
  "percentage": 5.0,
  "amount": 625.00,
  "reason": "no_matching_participants"
}
```

### كيف يجب أن تعرضه الواجهة الأمامية؟
- عرض `unresolved` بوضوح للمستخدم مع التنبيه بأن هذه المبالغ **لن تُوزَّع**
- السماح للمستخدم بمراجعة قائمة المشاركين قبل التوليد
- لا تُهمِل `remaining_amount` — قد يشمل مبالغ unresolved

---

## 12. الصلاحيات

### صلاحيات المبيعات

| الصلاحية | الـ Endpoints |
|---|---|
| `sales.reservations.view` | GET eligible-participants، GET participants، GET reservations |
| `sales.reservations.create` | POST reservations |
| `sales.reservations.confirm` | POST /{id}/confirm |
| `sales.reservations.cancel` | POST /{id}/cancel |
| Policy `viewParticipants` | GET /{reservation}/participants |
| Policy `manageParticipants` | PUT /{reservation}/participants |

### صلاحيات المحاسبة

| الصلاحية | الـ Endpoints |
|---|---|
| `accounting.sold-units.view` | GET project-commission-settings، GET show، POST preview-commission، POST preview-unit-commission |
| `accounting.sold-units.manage` | POST project-commission-settings، PUT update، POST activate، POST generate-unit-commission، POST sold-units/{id}/commission |
| `accounting.commissions.approve` | POST distributions/{distId}/approve، POST reject |

> **ملاحظة:** مسارَا المعاينة (السطر 907-908 في `routes/api.php`) لا يحملان `middleware('permission:...')` على مستوى المسار — الصلاحية مُتحقَّق منها داخل Form Request `authorize()`.

---

## 13. الاختبارات

### قائمة الاختبارات

| الملف | المرحلة | ما يغطيه |
|---|---|---|
| `tests/Feature/Sales/SalesReservationParticipantsStage1Test.php` | Stage 1 | إنشاء حجز مع participants، مزامنة، عرض، distinct validation |
| `tests/Feature/Accounting/ProjectCommissionSettingStage2Test.php` | Stage 2 | إنشاء/تعديل/تفعيل الإعدادات، التحقق من النسب |
| `tests/Unit/Services/ProjectCommissionCalculatorTest.php` | Stage 3 | المعادلات buyer/owner |
| `tests/Unit/Services/UnitPriceResolverTest.php` | Stage 3 | أولوية حل سعر الوحدة |
| `tests/Feature/Accounting/ProjectCommissionPreviewStage3Test.php` | Stage 3 | معاينة المشروع والوحدة، عدم كتابة في DB |
| `tests/Feature/Accounting/UnitCommissionGenerationStage4Test.php` | Stage 4 | توليد العمولة، حماية legacy، regeneration |
| `tests/Feature/Accounting/SimpleCommissionMvpStage5Test.php` | Stage 5 | E2E، flow كامل، QA scenarios |
| `tests/Feature/Accounting/AccountingCommissionTest.php` | Regression | الـ API القديم لا يزال يعمل |
| `tests/Feature/Sales/SalesReservationTest.php` | Regression | اختبارات الحجز الأساسية |

### الأوامر للتشغيل

```bash
cd /path/to/rakez-erp

php artisan test tests/Feature/Sales/SalesReservationParticipantsStage1Test.php
php artisan test tests/Feature/Accounting/ProjectCommissionSettingStage2Test.php
php artisan test tests/Unit/Services/ProjectCommissionCalculatorTest.php
php artisan test tests/Unit/Services/UnitPriceResolverTest.php
php artisan test tests/Feature/Accounting/ProjectCommissionPreviewStage3Test.php
php artisan test tests/Feature/Accounting/UnitCommissionGenerationStage4Test.php
php artisan test tests/Feature/Accounting/SimpleCommissionMvpStage5Test.php
php artisan test tests/Feature/Accounting/AccountingCommissionTest.php
php artisan test tests/Feature/Sales/SalesReservationTest.php
```

> **ملاحظة:** نتائج الاختبارات يجب التحقق منها في البيئة المحلية — راجع تقرير التدقيق في القسم 14.

---

## 14. تقرير التدقيق النهائي

### نتائج تدقيق الكود

| منطقة التدقيق | الحالة | التفاصيل |
|---|---|---|
| **Migrations** | ✅ آمنة | 3 migrations جديدة، إضافية، مع `down()` آمن، أسماء FK مختصرة |
| **Stage 4 Migration** | ✅ لا تغيير مدمر | `commission_distributions.type` لم يُمس |
| **UnitPriceResolver** | ✅ صحيح | الأولوية: final_selling_price → proposed_price → unit.price → exception |
| **ProjectCommissionCalculator** | ✅ صحيح | buyer: `amount * pct / 100`، owner: `amount / (1 + pct/100)` |
| **Owner formula** | ✅ لا طرح | النتيجة هي مبلغ العمولة مباشرةً (لا طرح من unit_price) |
| **Preview — لا كتابة** | ✅ مؤكد | لا `->save()` أو `->create()` في preview services |
| **Generation — Transaction** | ✅ مؤكد | `DB::transaction()` يلف كل العملية |
| **Manual commission guard** | ✅ مؤكد | يرفض `calculated_by_project_setting !== true` |
| **Regeneration policy** | ✅ مؤكد | يُعيد التوليد فقط إذا كانت العمولة وجميع توزيعاتها pending |
| **Approved/paid distributions** | ✅ محمية | حذف `pending` فقط عند إعادة التوليد |
| **Unresolved allocations** | ✅ مُرجَعة | في JSON، لا تُحفظ |
| **Total ≤ net_amount** | ✅ مُطبَّق | epsilon 0.02 ريال |
| **source_scope/source_type fillable** | ✅ | في `CommissionDistribution.$fillable` |
| **calculated_by_project_setting cast** | ✅ boolean | في `Commission.$casts` |
| **activeProjectCommissionSetting** | ✅ | في `Contract.php` كـ `HasOne` |
| **participantRecords** | ✅ | في `SalesReservation.php` كـ `HasMany` |
| **Duplicate participants rejected** | ✅ | `Rule::distinct` في `ValidatesReservationParticipants` |
| **Route ordering** | ✅ آمن | `eligible-participants` (486) قبل `{id}` (494) |
| **PUT participants — لا permission middleware** | ⚠️ ملاحظة | يعتمد على Policy في Request فقط (لا `middleware('permission:...')` على المسار) |
| **Preview routes — لا route middleware** | ⚠️ ملاحظة | يعتمد على `authorize()` في Form Request (مقبول) |
| **Legacy endpoint** | ✅ يعمل | `POST /api/accounting/sold-units/{id}/commission` |
| **docs/SIMPLE_COMMISSION_MVP_API.md** | ✅ موجود | يوثق formulas، routes، legacy mapping |

### هل آمن للدمج؟ ✅ **نعم**

**لا توجد مشاكل تُمنع الدمج.** الملاحظتان المذكورتان (PUT participants بدون route middleware، وpreviews بدون route middleware) هما **تصميم متعمد** وليسا أخطاءً — الصلاحيات مُطبَّقة في طبقة Request/Policy.

### المخاطر والمتابعات

| المخاطرة | الدرجة | التوصية |
|---|---|---|
| الكود غير مُكوَّم (uncommitted) على فرع `yusuf` | 🔴 عالية | كوِّم الكود وادمج في `main` في أقرب وقت |
| رقم تقريب الـ epsilon = 0.02 ريال | 🟡 منخفضة | قد يُسبب فروقًا صغيرة في عمليات الجمع |
| `commission_distributions.type` يستخدم `other` كـ fallback | 🟡 منخفضة | تأكد أن `other` موجود في الـ enum قبل الترحيل |
| unresolved allocations لا تُحفظ في DB | 🟡 معلومات | الواجهة الأمامية مسؤولة عن العرض |

---

## 15. ملاحظات للفريق الأمامي (Frontend)

### 1. اعرض المعاينة قبل التوليد
دائمًا اطلب `preview-unit-commission` قبل `generate-unit-commission`. المعاينة تُظهر التوزيعات وunresolved قبل الالتزام.

### 2. اعرض `unresolved` بوضوح
```
"هذه المبالغ غير موزَّعة لعدم وجود مشاركين مطابقين:"
- assigned_project_team / bring: 1,250.00 ريال
```
لا تُهمِل `unresolved` — المستخدم يحتاجها لمراجعة قائمة المشاركين.

### 3. لا تعتمد على `commission_distributions.type` للتقارير الجديدة
استخدم `source_scope` + `source_type` للتقارير والـ UI الجديد.

### 4. تعارض العمولة اليدوية يُرجع 422
إذا كان الحجز لديه عمولة يدوية (legacy):
```json
{ "errors": { "commission": ["This reservation has a legacy or manual commission..."] } }
```
اعرض رسالة واضحة: "هذا الحجز لديه عمولة يدوية ولا يمكن التوليد التلقائي."

### 5. معاينة المشروع تتطلب `base_amount`
على الرغم من أن `base_amount` nullable في قواعد التحقق، الخدمة ترمي 422 إذا لم يُرسَل أو كان ≤ 0. أرسل دائمًا `base_amount` عند معاينة المشروع.

### 6. معادلة المالك — لا طرح
> ⚠️ **تنبيه مهم للواجهة الأمامية:**
>
> للمصدر `owner`:
> ```
> commission_amount = unit_price ÷ (1 + commission_percentage ÷ 100)
> ```
> الناتج هو مبلغ العمولة **مباشرةً**.
> **لا تطرح** هذا الناتج من `unit_price`.

### 7. التحقق من صحة حالة التوليد
استخدم `calculated_by_project_setting === true` للتمييز بين العمولات المولَّدة تلقائيًا واليدوية في واجهة المحاسبة.
