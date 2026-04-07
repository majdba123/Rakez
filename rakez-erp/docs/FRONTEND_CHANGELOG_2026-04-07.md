# دليل تعديلات API للفرونت إند — 2026-04-07

هذا الملف يوثّق **التغييرات ذات الصلة بالواجهات الأمامية** فقط (عقود الاستجابة، الحقول الجديدة، الأخطاء، وسلوك الأزرار). المسار الأساسي للـ API: `/api/...` مع `Authorization: Bearer` (Sanctum) حسب المسارات أدناه.

---

## 1) المحاسبة — العربونات: تمييز `deposit_id` عن `reservation_id`

### المشكلة التي حُلّت

كان من الممكن إرسال **معرّف حجز** (`sales_reservations.id`) إلى مسارات مخصّصة لـ **عربون** (`deposits.id`)، فيفشل النظام أو يحدث لبس. تم توحيد عقد الاستجابة وإرجاع رسالة واضحة عند الخطأ.

### قاعدة للفرونت إند

| السياق | الحقل الذي يُستخدم في المسارات `{id}` |
|--------|----------------------------------------|
| تأكيد عربون، استرداد، PDF عربون | **`deposit_id`** فقط (من قائمة المعلقة أو من `deposits[].deposit_id` في المتابعة) |
| صفوف **المتابعة** | الصف يمثل **حجزاً**؛ المعرّف الأساسي للصف هو **`reservation_id`**؛ كل عربون تحت الحجز له **`deposit_id`** منفصل داخل `deposits[]` |

> **تنبيه:** الحقل `id` ما زال يُرجع لأغراض التوافق مع الإصدارات القديمة لكنه **مُهمل (deprecated)**. يُفضّل الاعتماد على `deposit_id` / `reservation_id` و`row_entity`.

### الحقول الجديدة / الموحّدة في قوائم العربونات

#### أ) قائمة العربون المعلقة — `GET /api/accounting/deposits/pending`

لكل عنصر في `data[]`:

| الحقل | المعنى |
|-------|--------|
| `deposit_id` | معرّف سجل العربون في `deposits` — **استخدمه** في `confirm` / `refund` / `pdf-data` |
| `reservation_id` | معرّف الحجز المرتبط (`sales_reservations.id`) |
| `row_entity` | القيمة الثابتة: `"deposit"` |
| `id` | نفس `deposit_id` (deprecated) |

#### ب) قائمة المتابعة — `GET /api/accounting/deposits/follow-up`

لكل عنصر في `data[]`:

| الحقل | المعنى |
|-------|--------|
| `reservation_id` | معرّف **الحجز** — هو معرّف الصف في هذا السياق |
| `row_entity` | `"sales_reservation"` |
| `deposit_id` | في مستوى الصف: `null` (العربونات داخل المصفوفة) |
| `deposits[]` | مصفوفة عربونات الحجز؛ كل عنصر يحتوي `deposit_id` و`id` (deprecated) و`amount` و`status` |
| `id` | نفس `reservation_id` (deprecated) |

### المسارات (لم تتغيّر)

| الطريقة | المسار | الصلاحية (مختصر) |
|---------|--------|-------------------|
| `GET` | `/api/accounting/deposits/pending` | `accounting.deposits.view` |
| `GET` | `/api/accounting/deposits/follow-up` | `accounting.deposits.view` |
| `GET` | `/api/accounting/deposits/{id}/pdf-data` | `accounting.deposits.view` — **`{id}` = deposit_id** |
| `POST` | `/api/accounting/deposits/{id}/confirm` | `accounting.deposits.manage` — **`{id}` = deposit_id** |
| `POST` | `/api/accounting/deposits/{id}/refund` | `accounting.deposits.manage` — **`{id}` = deposit_id** |

### استجابة PDF عربون — `GET .../deposits/{id}/pdf-data`

- يُفضّل قراءة **`deposit_id`** و**`reservation_id`** من الجذر.
- يبقى **`id`** مساوياً لـ `deposit_id` للتوافق.

### أخطاء متوقعة (يجب عرضها للمستخدم)

| الحالة | HTTP | ملاحظة |
|--------|------|--------|
| `deposit_id` غير موجود وليس هناك حجز بنفس المفتاح | **404** | رسالة مثل `Deposit not found` |
| المعرّف يطابق **حجزاً** وليس عربوناً | **422** | نص عربي يوضح أن المعرّف لحجز وليس عربوناً، ويذكر استخدام `deposit_id` من القائمة أو `deposits[].deposit_id` |
| منطق أعمال (مثلاً عمولة المشتري غير قابلة للاسترداد، عربون غير مؤهل) | **400** عادة | حسب الرسالة من الخادم |

### حافة تقنية (تصميم)

المسارات تستخدم رقماً واحداً `{id}`. إذا وُجد **عربون** و**حجز** بنفس القيمة الرقمية (تصادف نادر بين جدولين)، يُعطى **الأولوية لجدول العربونات**. لذلك يجب أن يعتمد الواجهة على **`deposit_id` الصريح** من الاستجابة وليس على افتراض أن أي `id` عام صحيح.

---

## 2) المحاسبة — توزيع العمولة: أقل من 100%، والباقي للشركة

### القاعدة الجديدة

- **مسموح:** مجموع نسب التوزيع **≤ 100%** (بما في ذلك أقل من 100%).
- **مرفوض:** المجموع **> 100%**.
- **لا يوجد** تطبيع تلقائي للنسب؛ **لا** يُضاف الباقي تلقائياً لموظف — الباقي يبقى **للشركة/النظام** كمبلغ متبقٍ في الحقول أدناه.
- **مصفوفة فارغة** `distributions: []` تُفرّغ جميع سطور التوزيع المعلّقة لهذه العمولة (عندما تكون العمولة `pending`).

### المسار

`PUT /api/accounting/commissions/{id}/distributions`

- **Validation:** `distributions` مطلوب كمفتاح **`present`** (يمكن أن تكون مصفوفة فارغة `[]`).
- كل عنصر: `type`, `percentage` (0–100 لكل سطر)، وحقول المستلم كما سبق.

### الحقول الإضافية في الاستجابة (بعد التحديث وفي تفاصيل الوحدة)

تُحسب من **`net_amount`** العمولة ومجموع النسب/المبالغ المخزّنة في سطور التوزيع:

| الحقل | المعنى |
|-------|--------|
| `total_distributed_percentage` | مجموع نسب `distributions[].percentage` |
| `remaining_percentage` | `100 - total_distributed_percentage` (لا يقل عن 0) |
| `distributed_amount` | مجموع `distributions[].amount` |
| `remaining_amount` | الجزء النسبي من `net_amount` المقابل لـ `remaining_percentage` |

**في `GET /api/accounting/sold-units/{id}`** (تفاصيل وحدة مباعة): نفس الحقول الأربعة ضمن `data` بجانب `distributions`.

**في `GET /api/accounting/commissions/{id}/summary`:** تُرجع أيضاً:

- `total_distributed_percentage`, `remaining_percentage`, `distributed_amount`, `remaining_amount`
- إضافة: `total_distributed_amount` — نفس قيمة `distributed_amount` (للتوافق مع تسميات قديمة محتملة)

### أخطاء

- مجموع النسب **> 100%** → **400** برسالة تتضمن المجموع الحالي (نص إنجليزي من الخادم).
- محاولة تعديل توزيعات لعمولة ليست `pending` → **400**.

### مسار مبيعات/عمولات آخر (إن وُجد)

طلب `DistributeCommissionRequest` (مسارات غير المحاسبة): التحقق بعدي يرفض فقط إذا **المجموع > 100%** مع رسالة عربية تشير إلى أن الباقي للشركة.

---

## 3) التسويق — تفاصيل المشروع: فرق المبيعات المسؤولة

### المسار

`GET /api/marketing/projects/{contractId}`

- **`contractId`** = معرّف العقد (`contracts.id`)، ليس معرّف جدول `marketing_projects` مباشرة.

### الحقل الجديد في الجذر داخل `data`

`responsible_sales_teams`: مصفوفة (قد تكون فارغة).

كل عنصر فريق:

```json
{
  "id": 0,
  "name": "string",
  "leaders": [{ "id": 0, "name": "string" }],
  "members": [
    {
      "id": 0,
      "name": "string",
      "role": "leader" | "member",
      "rating": null
    }
  ]
}
```

### قواعد السلوك

- المصدر: فرق مرتبطة بالعقد + تعيينات قائد المشروع (`sales_project_assignments`) ضمن نطاق **المبيعات**.
- **التقييم `rating`:** يُجلب من علاقة تقييم قائد–عضو (`SalesTeamMemberRating`) عند وجودها؛ إن لم يوجد مصدر صالح يبقى **`null`** (لا يُخترع رقم).
- عند وجود أكثر من قائد للفريق، التقييمات تُبنى من **أول قائد** كمرجع أساسي للمفاتيح (سلوك الخادم الحالي).

### حقول أخرى في نفس الاستجابة

- ما زال يُدمَج `duration_status` مع بيانات العقد/المشروع كما سبق؛ راجع هيكل `Contract`/`MarketingProject` في الاستجابة الفعلية.

---

## 4) ملخص سريع لما يجب تحديثه في الواجهة

1. **العربونات:** استخدام **`deposit_id`** لأزرار التأكيد / الاسترداد / PDF؛ في المتابعة استخدام **`reservation_id`** لعرض صف الحجز و**`deposits[].deposit_id`** لإجراءات العربون.
2. **العمولة:** السماح بإدخال مجموع نسب **أقل من 100%**؛ عرض **`remaining_percentage`** و**`remaining_amount`** للمستخدم؛ رفض الواجهة مسبقاً إن كان المجموع **> 100%**.
3. **تسويق:** قراءة **`responsible_sales_teams`** من تفاصيل المشروع وعرض القادة والأعضاء والتقييم (أو `-` عند `null`).

---

## 5) الملفات المرجعية في المستودع (للمطورين)

| المنطقة | ملفات رئيسية |
|---------|----------------|
| العربونات | `app/Services/Accounting/AccountingDepositService.php`, `app/Http/Controllers/Accounting/AccountingDepositController.php` |
| العمولة | `app/Services/Accounting/AccountingCommissionService.php`, `app/Models/Commission.php` (`getDistributionPoolFigures`) |
| التسويق | `app/Services/Marketing/MarketingProjectService.php`, `app/Http/Controllers/Marketing/MarketingProjectController.php` |

---

*آخر تحديث للتوثيق: 2026-04-07.*
