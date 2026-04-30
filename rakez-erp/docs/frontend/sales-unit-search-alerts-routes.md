# دليل الفرونت — مسارات تنبيهات البحث عن وحدة

هذا الدليل يشرح مسارات `Sales Unit Search Alerts` الموجودة حاليًا في Laravel. الفكرة أن موظف المبيعات يستطيع تسجيل تنبيه بحث لعميل عندما لا يجد وحدة مناسبة. لاحقًا، عندما تصبح وحدة مطابقة متاحة، النظام ينشئ إشعارًا داخليًا لموظف المبيعات. SMS للعميل اختياري، ولا يتم إلا إذا كان مفعّلًا في الإعدادات، وكانت بيانات Twilio مكتملة، وكان العميل موافقًا على SMS، واجتازت سياسة الإرسال.

SMS ليس مصدر الحقيقة. إشعار النظام الداخلي هو السلوك الأساسي، وفشل SMS أو تعطيله لا يعني فشل التنبيه.

## قواعد API العامة

- قاعدة المسارات: `{{base_url}}/api`.
- كل المسارات في هذا الملف تحت: `/api/sales/units/search-alerts`.
- المصادقة مطلوبة عبر Sanctum:

```http
Authorization: Bearer {{auth_token}}
Accept: application/json
```

- أضف `Content-Type: application/json` مع `POST` و`PATCH`.
- المسارات محمية في `routes/api.php` بـ `auth:sanctum` و `role:sales|sales_leader|admin` و `permission:sales.search_alerts.view`.
- المستخدم العادي يرى تنبيهاته فقط. المستخدم `admin` أو من يملك `sales.search_alerts.manage` يستطيع الوصول لنطاق أوسع حسب الكود.
- SMS لا يرسل عند إنشاء التنبيه. الإرسال يحدث لاحقًا عند مطابقة وحدة متاحة عبر خدمة المطابقة.
- لا ترسل مفاتيح Twilio أو أي أسرار من الفرونت. إعدادات SMS مكانها الخادم فقط.

## تبويب: إنشاء تنبيه

الغرض: إنشاء تنبيه بحث جديد للعميل.

```http
POST /api/sales/units/search-alerts
```

الهيدرز المطلوبة:

```http
Authorization: Bearer {{auth_token}}
Accept: application/json
Content-Type: application/json
```

### حقول body

الحقول حسب `StoreSalesUnitSearchAlertRequest`:

| الحقل | النوع | مطلوب | ملاحظات |
|---|---:|---:|---|
| `client_name` | string | لا | حد أقصى `255` |
| `client_mobile` | string | نعم | حد أقصى `50` |
| `client_email` | email | لا | حد أقصى `255` |
| `client_sms_opt_in` | boolean | لا | عند عدم الإرسال يتم اعتباره `false` |
| `client_sms_opted_in_at` | date | لا | إذا كان `client_sms_opt_in=true` ولم يرسل الحقل، يضعه backend تلقائيًا |
| `client_sms_locale` | string | لا | حد أقصى `20` |
| `city_id` | integer | لا | يجب أن يوجد في جدول `cities` إذا أرسل |
| `district_id` | integer | لا | يجب أن يوجد في جدول `districts` إذا أرسل |
| `project_id` | integer | لا | يجب أن يوجد في جدول `contracts` إذا أرسل |
| `unit_type` | string | لا | حد أقصى `255` |
| `floor` | string | لا | حد أقصى `50` |
| `min_price` | numeric | لا | أقل قيمة `0` |
| `max_price` | numeric | لا | أقل قيمة `0` ويجب أن يكون `>= min_price` |
| `min_area` | numeric | لا | أقل قيمة `0` |
| `max_area` | numeric | لا | أقل قيمة `0` ويجب أن يكون `>= min_area` |
| `min_bedrooms` | integer | لا | من `0` إلى `255` |
| `max_bedrooms` | integer | لا | من `0` إلى `255` ويجب أن يكون `>= min_bedrooms` |
| `query_text` | string | لا | حد أقصى `255` |
| `status` | string | لا | القيم: `active`, `paused`, `matched`, `cancelled` |
| `expires_at` | date | لا | يجب أن يكون بعد الوقت الحالي |

حقول مرفوضة عند إنشاء التنبيه:

- `page`
- `per_page`
- `sort_by`
- `sort_dir`

### قاعدة الحد الأدنى للمعايير

لا توجد قاعدة في الكود الحالي تفرض إرسال معيار بحث واحد على الأقل. الحد الأدنى المقبول في backend هو `client_mobile`. مع ذلك، من ناحية UX، اعرض نموذج التنبيه بعد فشل بحث فعلي واملأ المعايير من البحث الفاشل.

### سلوك `query_text`

استخدم `query_text` في مسارات التنبيه. يوجد دعم داخلي في `UnitSearchCriteria` لتحويل `q` إلى `query_text`، لكن `StoreSalesUnitSearchAlertRequest` لا يعرّف `q` ضمن الحقول الموثقة للطلب، لذلك لا يعتمد الفرونت على `q` في إنشاء التنبيه.

### مثال صالح بدون SMS

```json
{
  "client_name": "عميل تجريبي",
  "client_mobile": "+966500000000",
  "client_sms_opt_in": false,
  "project_id": 1,
  "unit_type": "villa",
  "floor": "2",
  "min_price": 100000,
  "max_price": 900000,
  "min_area": 80,
  "max_area": 250,
  "min_bedrooms": 2,
  "max_bedrooms": 5
}
```

### مثال صالح مع موافقة SMS

```json
{
  "client_name": "عميل تجريبي",
  "client_mobile": "+966500000000",
  "client_sms_opt_in": true,
  "client_sms_locale": "ar-SA",
  "project_id": 1,
  "unit_type": "villa"
}
```

عند `client_sms_opt_in=true`، يحفظ backend `client_sms_opted_in_at` تلقائيًا إذا لم ترسله الواجهة. هذا لا يرسل SMS وقت الإنشاء.

### أمثلة غير صالحة

`max_price` أقل من `min_price`:

```json
{
  "client_mobile": "+966500000000",
  "min_price": 900000,
  "max_price": 100000
}
```

إرسال حقول pagination/sorting كمعايير تنبيه:

```json
{
  "client_mobile": "+966500000000",
  "page": 1,
  "per_page": 20,
  "sort_by": "price",
  "sort_dir": "asc"
}
```

عدم إرسال `client_mobile`:

```json
{
  "client_name": "عميل تجريبي",
  "unit_type": "villa"
}
```

### استجابة نجاح

Status code: `201`

```json
{
  "success": true,
  "message": "Sales unit search alert created successfully",
  "data": {
    "id": 1,
    "sales_staff_id": 10,
    "client": {
      "name": "عميل تجريبي",
      "mobile": "+966500000000",
      "email": null,
      "sms_opt_in": false,
      "sms_opted_in_at": null,
      "sms_locale": null
    },
    "criteria": {
      "city_id": null,
      "district_id": null,
      "project_id": 1,
      "unit_type": "villa",
      "floor": "2",
      "min_price": 100000,
      "max_price": 900000,
      "min_area": 80,
      "max_area": 250,
      "min_bedrooms": 2,
      "max_bedrooms": 5,
      "query_text": null
    },
    "status": "active",
    "last_notification": {
      "last_notified_at": null,
      "last_system_notified_at": null,
      "last_sms_attempted_at": null,
      "last_sms_sent_at": null,
      "last_sms_error": null,
      "last_twilio_sid": null,
      "last_delivery_error": null
    },
    "last_matched_unit": {
      "id": null,
      "unit_number": null,
      "unit_type": null,
      "price": null
    },
    "expires_at": "2026-05-30T12:00:00.000000Z",
    "deliveries": [],
    "created_at": "2026-04-30T12:00:00.000000Z",
    "updated_at": "2026-04-30T12:00:00.000000Z",
    "deleted_at": null
  }
}
```

ملاحظة: `expires_at` يملؤه backend تلقائيًا إذا لم يرسل، بناءً على `sales.unit_search_alerts.default_expiration_days`.

### استجابة validation

Status code: `422`

```json
{
  "message": "The max price field must be greater than or equal to min price.",
  "errors": {
    "max_price": [
      "The max price field must be greater than or equal to min price."
    ]
  }
}
```

### سلوك الفرونت بعد النجاح

- خزّن `data.id` إذا كان المستخدم سيُفتح له عرض التفاصيل مباشرة.
- اعرض حالة التنبيه حسب `data.status`.
- لا تعرض أن SMS أرسل بعد الإنشاء؛ هذه ليست حقيقة في backend.
- إذا كان `client.sms_opt_in=true`، اعرضها كموافقة محفوظة فقط.

## تبويب: عرض تنبيهاتي

الغرض: جلب قائمة التنبيهات المرئية للمستخدم الحالي.

```http
GET /api/sales/units/search-alerts
```

الهيدرز المطلوبة:

```http
Authorization: Bearer {{auth_token}}
Accept: application/json
```

### Query params

| الحقل | النوع | ملاحظات |
|---|---:|---|
| `status` | string | فلترة مباشرة بدون validation في controller |
| `per_page` | integer | الافتراضي `15`، والحد من `1` إلى `100` |
| `page` | integer | مدعوم ضمن Laravel pagination |

لا ترسل `sort_by` أو `sort_dir` هنا؛ controller يستخدم `latest()` فقط.

### استجابة النجاح

Status code: `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sales_staff_id": 10,
      "client": {
        "name": "عميل تجريبي",
        "mobile": "+966500000000",
        "email": null,
        "sms_opt_in": false,
        "sms_opted_in_at": null,
        "sms_locale": null
      },
      "criteria": {
        "city_id": null,
        "district_id": null,
        "project_id": 1,
        "unit_type": "villa",
        "floor": "2",
        "min_price": 100000,
        "max_price": 900000,
        "min_area": null,
        "max_area": null,
        "min_bedrooms": null,
        "max_bedrooms": null,
        "query_text": null
      },
      "status": "active",
      "last_notification": {
        "last_notified_at": null,
        "last_system_notified_at": null,
        "last_sms_attempted_at": null,
        "last_sms_sent_at": null,
        "last_sms_error": null,
        "last_twilio_sid": null,
        "last_delivery_error": null
      },
      "last_matched_unit": {
        "id": null,
        "unit_number": null,
        "unit_type": null,
        "price": null
      },
      "expires_at": "2026-05-30T12:00:00.000000Z",
      "deliveries": [],
      "created_at": "2026-04-30T12:00:00.000000Z",
      "updated_at": "2026-04-30T12:00:00.000000Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### ملاحظات تصميم القائمة

- اعرض اسم العميل أو رقم الجوال كعنوان أساسي.
- اعرض `status` بوسم واضح.
- اعرض أهم المعايير غير الفارغة فقط؛ القيم `null` تعني أن المعيار غير مفعّل.
- اعرض حالة SMS من `deliveries` إذا وُجدت، لكن لا تجعل فشل SMS يعني فشل التنبيه.
- القائمة مرتبة من الأحدث للأقدم حسب `latest()` في controller.

## تبويب: عرض تنبيه محدد

الغرض: عرض تفاصيل تنبيه واحد.

```http
GET /api/sales/units/search-alerts/{alert}
```

Path parameter:

| الحقل | النوع | ملاحظات |
|---|---:|---|
| `alert` | integer | معرف التنبيه |

الهيدرز المطلوبة:

```http
Authorization: Bearer {{auth_token}}
Accept: application/json
```

### استجابة النجاح

Status code: `200`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "sales_staff_id": 10,
    "client": {
      "name": "عميل تجريبي",
      "mobile": "+966500000000",
      "email": null,
      "sms_opt_in": true,
      "sms_opted_in_at": "2026-04-30T12:00:00.000000Z",
      "sms_locale": "ar-SA"
    },
    "criteria": {
      "city_id": null,
      "district_id": null,
      "project_id": 1,
      "unit_type": "villa",
      "floor": null,
      "min_price": null,
      "max_price": null,
      "min_area": null,
      "max_area": null,
      "min_bedrooms": null,
      "max_bedrooms": null,
      "query_text": null
    },
    "status": "active",
    "last_notification": {
      "last_notified_at": null,
      "last_system_notified_at": null,
      "last_sms_attempted_at": null,
      "last_sms_sent_at": null,
      "last_sms_error": null,
      "last_twilio_sid": null,
      "last_delivery_error": null
    },
    "last_matched_unit": {
      "id": null,
      "unit_number": null,
      "unit_type": null,
      "price": null
    },
    "expires_at": "2026-05-30T12:00:00.000000Z",
    "deliveries": [],
    "created_at": "2026-04-30T12:00:00.000000Z",
    "updated_at": "2026-04-30T12:00:00.000000Z",
    "deleted_at": null
  }
}
```

### ملاحظات صفحة أو نافذة التفاصيل

- اعرض بيانات العميل في قسم منفصل عن المعايير.
- اعرض `last_notification` كحالة تشغيلية، وليس كمدخل قابل للتعديل.
- إذا ظهرت `deliveries`، اعرض القناة والحالة وسبب التخطي أو الخطأ عند الحاجة.
- إذا كان `status=matched` فهذا يعني أن موظف المبيعات تم إشعاره داخل النظام.

## تبويب: تعديل تنبيه

الغرض: تعديل بيانات العميل أو معايير التنبيه أو حالة التنبيه.

```http
PATCH /api/sales/units/search-alerts/{alert}
```

الهيدرز المطلوبة:

```http
Authorization: Bearer {{auth_token}}
Accept: application/json
Content-Type: application/json
```

### الحقول القابلة للتعديل

الحقول حسب `UpdateSalesUnitSearchAlertRequest`:

- `client_name`
- `client_mobile`
- `client_email`
- `client_sms_opt_in`
- `client_sms_opted_in_at`
- `client_sms_locale`
- `city_id`
- `district_id`
- `project_id`
- `unit_type`
- `floor`
- `min_price`
- `max_price`
- `min_area`
- `max_area`
- `min_bedrooms`
- `max_bedrooms`
- `query_text`
- `status`
- `expires_at`

كل الحقول `sometimes`، لذلك أرسل الحقول المراد تغييرها فقط.

حقول مرفوضة عند التعديل:

- `page`
- `per_page`
- `sort_by`
- `sort_dir`

### مثال تعديل بيانات العميل

```json
{
  "client_name": "عميل تجريبي محدث",
  "client_mobile": "+966511111111",
  "client_email": "demo.customer@example.com"
}
```

### مثال تعديل المعايير

```json
{
  "max_price": 800000,
  "min_bedrooms": 3,
  "query_text": "A-101"
}
```

### مثال تعديل الحالة

```json
{
  "status": "paused"
}
```

القيم المقبولة للحالة هي:

- `active`
- `paused`
- `matched`
- `cancelled`

### ملاحظات validation

- إذا أرسلت `client_mobile` في التعديل يجب ألا يكون فارغًا.
- إذا أرسلت `client_sms_opt_in=true` ولم ترسل `client_sms_opted_in_at`، يضع backend وقت الموافقة تلقائيًا.
- إذا أرسلت `client_sms_opt_in=false`، يضع backend `client_sms_opted_in_at=null`.
- تحقق min/max في التعديل يقارن القيم الجديدة مع القيم الحالية للتنبيه إذا لم ترسل الطرف الآخر.

### استجابة النجاح

Status code: `200`

```json
{
  "success": true,
  "message": "Sales unit search alert updated successfully",
  "data": {
    "id": 1,
    "sales_staff_id": 10,
    "client": {
      "name": "عميل تجريبي محدث",
      "mobile": "+966511111111",
      "email": "demo.customer@example.com",
      "sms_opt_in": false,
      "sms_opted_in_at": null,
      "sms_locale": null
    },
    "criteria": {
      "city_id": null,
      "district_id": null,
      "project_id": 1,
      "unit_type": "villa",
      "floor": null,
      "min_price": null,
      "max_price": 800000,
      "min_area": null,
      "max_area": null,
      "min_bedrooms": 3,
      "max_bedrooms": null,
      "query_text": "A-101"
    },
    "status": "paused",
    "last_notification": {
      "last_notified_at": null,
      "last_system_notified_at": null,
      "last_sms_attempted_at": null,
      "last_sms_sent_at": null,
      "last_sms_error": null,
      "last_twilio_sid": null,
      "last_delivery_error": null
    },
    "last_matched_unit": {
      "id": null,
      "unit_number": null,
      "unit_type": null,
      "price": null
    },
    "expires_at": "2026-05-30T12:00:00.000000Z",
    "deliveries": [],
    "created_at": "2026-04-30T12:00:00.000000Z",
    "updated_at": "2026-04-30T12:10:00.000000Z",
    "deleted_at": null
  }
}
```

## تبويب: إلغاء/حذف تنبيه

الغرض: إلغاء التنبيه وإزالته من القوائم العادية.

```http
DELETE /api/sales/units/search-alerts/{alert}
```

الهيدرز المطلوبة:

```http
Authorization: Bearer {{auth_token}}
Accept: application/json
```

### السلوك الفعلي من الكود

- يحدث backend الحقل `status` إلى `cancelled`.
- بعد ذلك ينفذ soft delete عبر `delete()` لأن النموذج يستخدم `SoftDeletes`.
- القوائم العادية لا تعرض التنبيه بعد الحذف لأن الاستعلام لا يستخدم `withTrashed()`.
- لا يوجد body لهذا الطلب.

### استجابة النجاح

Status code: `200`

```json
{
  "success": true,
  "message": "Sales unit search alert cancelled successfully"
}
```

### نص تأكيد مقترح للفرونت

```text
هل تريد إلغاء تنبيه البحث؟ لن يظهر التنبيه في قائمتك بعد الإلغاء.
```

## خريطة حالات التنبيه

القيم الفعلية في `SalesUnitSearchAlert`:

| القيمة | النص العربي المقترح | المعنى |
|---|---|---|
| `active` | نشط | التنبيه قابل للمطابقة إذا لم ينته وقته |
| `paused` | موقوف مؤقتًا | لا يظهر ضمن `scopeActive` للمطابقة |
| `matched` | تمت المطابقة | تم العثور على وحدة مطابقة وتم إشعار موظف المبيعات داخل النظام |
| `cancelled` | ملغى | تم إلغاء التنبيه أو حذفه |

## خريطة قنوات وحالات التسليم

القنوات الفعلية في `SalesUnitSearchAlertDelivery`:

| القيمة | النص العربي المقترح | المعنى |
|---|---|---|
| `system_notification` | إشعار داخل النظام | القناة الأساسية لإشعار موظف المبيعات |
| `sms` | رسالة SMS | قناة اختيارية للعميل |

حالات التسليم الفعلية:

| القيمة | النص العربي المقترح | المعنى |
|---|---|---|
| `pending` | قيد المعالجة | تم تسجيل التسليم ولم يكتمل بعد |
| `sent` | تم الإرسال | تم التسليم بنجاح |
| `skipped` | تم التخطي | لم يتم الإرسال بسبب شرط أو إعداد، والسبب في `skip_reason` |
| `failed` | فشل | فشل مسار SMS أو الخدمة الخارجية، والتفاصيل في `error_message` |

## قواعد UX للفرونت

- اعرض زر `تسجيل طلب عميل` عندما ينتهي بحث المستخدم عن وحدة بدون نتائج مناسبة، ويكون المستخدم لديه صلاحية استخدام الميزة.
- املأ نموذج التنبيه من معايير البحث الفاشل: `city_id`, `district_id`, `project_id`, `unit_type`, `floor`, `min_price`, `max_price`, `min_area`, `max_area`, `min_bedrooms`, `max_bedrooms`, `query_text`.
- لا ترسل الحقول غير المستخدمة كـ `null` إلا إذا كان قصد المستخدم مسح المعيار عند التعديل.
- لا ترسل `page`, `per_page`, `sort_by`, أو `sort_dir` كمعايير تنبيه؛ هذه الحقول مرفوضة في create/update.
- القيم `null` داخل `criteria` في الاستجابة تعني أن المعيار غير مفعّل.
- فشل SMS أو تخطيه لا يعرض كفشل كامل للميزة. اعرضه كحالة فرعية داخل التسليمات.
- `matched` يعني أن موظف المبيعات تم إشعاره داخل النظام.
- لا تعرض أن العميل استلم SMS إلا إذا وجدت delivery بقناة `sms` وحالة `sent`.
- لا تجعل إرسال SMS إجراءً يدويًا من شاشة التنبيه؛ الكود الحالي يرسله تلقائيًا لاحقًا عبر job عند المطابقة.

## معالجة الأخطاء

### 401 غير مصادق

يظهر عند غياب token أو انتهاء صلاحيته.

```json
{
  "message": "Unauthenticated."
}
```

### 403 ممنوع

الأسباب المتوقعة من الكود:

- المستخدم لا يملك `sales.search_alerts.view`.
- المستخدم ليس ضمن `role:sales|sales_leader|admin`.
- المستخدم يحاول عرض أو تعديل أو حذف تنبيه لا يملكه، وليس `admin` ولا يملك `sales.search_alerts.manage`.

شكل الرسالة قد يأتي من Laravel أو Spatie أو `AuthorizationException`. مثال من controller عند منع الوصول لتنبيه لا يملكه المستخدم:

```json
{
  "message": "You are not authorized to access this alert."
}
```

### 404 غير موجود

يظهر إذا كان `alert` غير موجود أو محذوفًا soft delete ولا يتم تحميله بـ `withTrashed()`.

```json
{
  "message": "No query results for model [App\\Models\\SalesUnitSearchAlert] 999."
}
```

### 422 خطأ تحقق

يظهر عند فشل قواعد `StoreSalesUnitSearchAlertRequest` أو `UpdateSalesUnitSearchAlertRequest`.

```json
{
  "message": "The max price field must be greater than or equal to min price.",
  "errors": {
    "max_price": [
      "The max price field must be greater than or equal to min price."
    ]
  }
}
```

## Payloads جاهزة للاستخدام

### إنشاء تنبيه بدون SMS

```http
POST /api/sales/units/search-alerts
```

```json
{
  "client_name": "عميل تجريبي",
  "client_mobile": "+966500000000",
  "client_sms_opt_in": false,
  "project_id": 1,
  "unit_type": "villa",
  "min_price": 100000,
  "max_price": 900000
}
```

### إنشاء تنبيه مع SMS opt-in

```http
POST /api/sales/units/search-alerts
```

```json
{
  "client_name": "عميل تجريبي",
  "client_mobile": "+966500000000",
  "client_sms_opt_in": true,
  "client_sms_locale": "ar-SA",
  "project_id": 1,
  "unit_type": "villa"
}
```

### إنشاء تنبيه باستخدام `query_text`

```http
POST /api/sales/units/search-alerts
```

```json
{
  "client_name": "عميل تجريبي",
  "client_mobile": "+966500000000",
  "query_text": "A-101",
  "project_id": 1
}
```

### تعديل تنبيه

```http
PATCH /api/sales/units/search-alerts/1
```

```json
{
  "status": "paused",
  "max_price": 800000
}
```

### إلغاء/حذف تنبيه

```http
DELETE /api/sales/units/search-alerts/1
```

لا يوجد body.

## قائمة تنفيذ الفرونت

- اربط كل الطلبات بـ `Authorization: Bearer {{auth_token}}`.
- أنشئ شاشة قائمة تعتمد على `GET /api/sales/units/search-alerts`.
- أضف فلتر `status` واستخدم `page` و`per_page` للقائمة فقط.
- أنشئ نموذج إنشاء يرسل `client_mobile` ومعايير البحث التي اختارها المستخدم.
- اجعل `client_sms_opt_in` اختيارًا واضحًا من العميل، ولا تفترض الموافقة.
- استخدم `query_text` بدل `q` في التنبيهات.
- امنع إرسال `page`, `per_page`, `sort_by`, و`sort_dir` في create/update.
- في التعديل، أرسل الحقول المتغيرة فقط.
- في الحذف، اعرض تأكيدًا قبل `DELETE`.
- اعرض `status=matched` كنجاح إشعار داخلي لموظف المبيعات.
- اعرض SMS كحالة اختيارية من `deliveries` ولا تجعل فشله يفشل تجربة التنبيه.
- لا تعرض `last_sms_error` أو `error_message` كنص تقني طويل للمستخدم النهائي بدون صياغة مناسبة.
- لا تضع أي مفاتيح Twilio أو بيانات عملاء حقيقية في الكود أو Postman أو الواجهة.

## ملاحظات غير مؤكدة من الكود الحالي

- نصوص رسائل 403 قد تختلف حسب مصدر المنع: middleware permissions أو `AuthorizationException`.
- لا توجد قاعدة backend تمنع إنشاء alert بدون معايير بحث، لذلك شرط إظهار النموذج بعد بحث فاشل هو مسؤولية الفرونت.
