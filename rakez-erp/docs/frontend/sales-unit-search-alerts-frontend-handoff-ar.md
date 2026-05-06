# تسليم تطوير الواجهة الأمامية — تنبيهات البحث عن وحدات المبيعات
**Sales Unit Search Alerts — Frontend Handoff (Arabic)**
> مبني على قراءة الكود الفعلي في الفرع `yusuf` | تاريخ: 2026-05-05

---

## 1. شرح الميزة

### ما هي هذه الميزة؟

عندما يبحث موظف مبيعات عن وحدة لعميل ولا يجد ما يناسب، يمكنه إنشاء **تنبيه بحث** يحفظ معايير البحث ومعلومات الاتصال بالعميل. عند توفر وحدة مطابقة لاحقاً، يتلقى موظف المبيعات **إشعاراً داخلياً فورياً** داخل النظام. إرسال SMS للعميل هو ميزة اختيارية ثانوية وقد تكون معطلة حسب الإعدادات.

### مصدر الحقيقة

> ⚠️ **تحذير مهم:**
> - **الإشعار الداخلي (system_notification) هو مصدر الحقيقة الوحيد.**
> - حالة `matched` تعني أن النظام أنشأ إشعاراً داخلياً للموظف.
> - لا تعتمد على SMS للتحقق من تطابق التنبيه. SMS قد يُرسل أو لا يُرسل.
> - لا تعرض مفاتيح Twilio أو أخطاءها الخام للمستخدم.

---

## 2. تدفق UX الكامل

### التدفق الرئيسي — بحث وإنشاء تنبيه

```
1. موظف المبيعات يفتح صفحة البحث عن الوحدات
   └── GET /api/sales/units/search?city_id=1&unit_type=villa&min_price=200000&max_price=800000

2. النتائج فارغة (لا توجد وحدات مطابقة)
   └── تظهر رسالة: "لم يتم العثور على وحدات مطابقة"
   └── زر: "إنشاء تنبيه عند توفر وحدة مناسبة"

3. يضغط الموظف على الزر
   └── تفتح نافذة حوار (Modal) لإدخال بيانات العميل
   └── معايير البحث الحالية تُملأ تلقائياً في النموذج

4. الموظف يكمل بيانات العميل ويضغط "حفظ التنبيه"
   └── POST /api/sales/units/search-alerts
   └── النظام يحفظ التنبيه مع معايير البحث

5. عند توفر وحدة مطابقة (يحدث بشكل تلقائي في الخلفية)
   └── يتلقى الموظف إشعاراً في جرس الإشعارات
   └── حالة التنبيه تتغير من active → matched
   └── قد يتلقى العميل SMS (اختياري، حسب الإعدادات)
```

---

## 3. تدفق نتائج البحث الفارغة

عند إرجاع `meta.total === 0` من نقطة `/api/sales/units/search`:

```
┌─────────────────────────────────────────────────────┐
│   لم يتم العثور على وحدات تطابق معايير البحث        │
│                                                       │
│   هل تريد إنشاء تنبيه ليصلك إشعار عند توفر وحدة؟  │
│                                                       │
│        [ إنشاء تنبيه ]    [ بحث جديد ]              │
└─────────────────────────────────────────────────────┘
```

---

## 4. سلوك نافذة إنشاء التنبيه (Modal)

### الحقول المطلوبة والاختيارية

| الحقل (Label بالعربية) | الحقل API | إلزامي | ملاحظات |
|---|---|---|---|
| رقم جوال العميل | `client_mobile` | ✅ نعم | string, max 50 |
| اسم العميل | `client_name` | اختياري | string, max 255 |
| البريد الإلكتروني | `client_email` | اختياري | email, max 255 |
| موافقة على SMS | `client_sms_opt_in` | اختياري | boolean (checkbox) |
| لغة SMS | `client_sms_locale` | اختياري | string (ar-SA / en) |
| المدينة | `city_id` | اختياري | integer, exists cities |
| الحي | `district_id` | اختياري | integer, exists districts |
| المشروع | `project_id` | اختياري | integer, exists contracts |
| نوع الوحدة | `unit_type` | اختياري | string |
| الدور | `floor` | اختياري | string, max 50 |
| السعر الأدنى | `min_price` | اختياري | numeric, ≥ 0 |
| السعر الأقصى | `max_price` | اختياري | numeric, ≥ min_price |
| المساحة الأدنى | `min_area` | اختياري | numeric, ≥ 0 |
| المساحة الأقصى | `max_area` | اختياري | numeric, ≥ min_area |
| غرف نوم (أدنى) | `min_bedrooms` | اختياري | integer 0-255 |
| غرف نوم (أقصى) | `max_bedrooms` | اختياري | integer 0-255, ≥ min_bedrooms |
| نص البحث | `query_text` | اختياري | string, max 255 |
| الحالة | `status` | اختياري | active/paused/matched/cancelled |
| تاريخ الانتهاء | `expires_at` | اختياري | date after:now |

> **ملاحظة مهمة:** لا ترسل `page`, `per_page`, `sort_by`, `sort_dir` في طلبات الإنشاء أو التحديث — يرفضها الـ API تلقائياً (حقول `prohibited`).

> **ملاحظة:** استخدم `query_text` لحفظ نص البحث. حقل `q` مخصص لـ GET search فقط وليس للتنبيهات.

### سلوك checkbox موافقة SMS

- إذا علّم المستخدم ✅ على `client_sms_opt_in = true`:
  - يُسجَّل `client_sms_opted_in_at` تلقائياً بالوقت الحالي من الـ Backend
  - لا تحتاج الواجهة الأمامية إرسال `client_sms_opted_in_at` (يُعبأ تلقائياً إن لم يُرسل)
- إذا ألغى ✗ الموافقة: يُحفظ `client_sms_opted_in_at = null`

### الملء التلقائي للمعايير

عند فتح الـ Modal من صفحة البحث، امله تلقائياً بالمعايير الحالية:
```javascript
// مثال على الملء التلقائي
const currentFilters = {
  city_id: searchParams.city_id,
  district_id: searchParams.district_id,
  project_id: searchParams.project_id,
  unit_type: searchParams.unit_type,
  min_price: searchParams.min_price,
  max_price: searchParams.max_price,
  query_text: searchParams.q  // ← انتبه: q في البحث يقابل query_text في التنبيه
};
```

---

## 5. سلوك صفحة قائمة التنبيهات

### نقطة الـ API

```
GET /api/sales/units/search-alerts?status=active&per_page=15
```

**الـ Query Params المدعومة للقائمة:**
| Parameter | النوع | القيمة الافتراضية | الوصف |
|---|---|---|---|
| `status` | string | (كل الحالات) | فلتر بحالة معينة |
| `per_page` | integer | 15 | عدد العناصر بالصفحة (max 100) |

### صلاحيات العرض

- موظف المبيعات العادي: يرى تنبيهاته هو فقط (تصفية تلقائية من الـ Backend)
- `sales_leader` أو من لديه `sales.search_alerts.manage`: يرى جميع التنبيهات

### شكل الاستجابة

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "sales_staff_id": 7,
      "client": {
        "name": "Ahmed Al-Harbi",
        "mobile": "+966501234567",
        "email": "ahmed@example.com",
        "sms_opt_in": true,
        "sms_opted_in_at": "2026-05-01T10:00:00.000000Z",
        "sms_locale": "ar-SA"
      },
      "criteria": {
        "city_id": 1,
        "district_id": null,
        "project_id": 5,
        "unit_type": "villa",
        "floor": "2",
        "min_price": 500000.00,
        "max_price": 900000.00,
        "min_area": null,
        "max_area": null,
        "min_bedrooms": 3,
        "max_bedrooms": null,
        "query_text": "A-101"
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
      "last_matched_unit": null,
      "expires_at": "2026-06-04T10:00:00.000000Z",
      "deliveries": [],
      "created_at": "2026-05-05T10:00:00.000000Z",
      "updated_at": "2026-05-05T10:00:00.000000Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

---

## 6. سلوك صفحة تفاصيل التنبيه

### نقطة الـ API

```
GET /api/sales/units/search-alerts/{alert_id}
```

الاستجابة مطابقة لعنصر واحد من قائمة التنبيهات مع تحميل:
- `last_matched_unit`: معلومات الوحدة المطابقة الأخيرة (إن وجدت)
- `deliveries`: سجل جميع عمليات التسليم (system_notification + sms)

### عرض بيانات `last_matched_unit`

```json
"last_matched_unit": {
  "id": 88,
  "unit_number": "A-101",
  "unit_type": "villa",
  "price": 750000.00
}
```

### عرض `deliveries`

```json
"deliveries": [
  {
    "id": 1,
    "contract_unit_id": 88,
    "user_notification_id": 15,
    "client_mobile": "+966501234567",
    "delivery_channel": "system_notification",
    "status": "sent",
    "twilio_sid": null,
    "skip_reason": null,
    "error_message": null,
    "sent_at": "2026-05-05T14:30:00.000000Z",
    "created_at": "2026-05-05T14:30:00.000000Z",
    "updated_at": "2026-05-05T14:30:00.000000Z"
  },
  {
    "id": 2,
    "contract_unit_id": 88,
    "delivery_channel": "sms",
    "status": "skipped",
    "skip_reason": "sms_disabled",
    "error_message": null,
    "sent_at": null
  }
]
```

---

## 7. سلوك جرس الإشعارات وال Polling

### نقطة الـ API للإشعارات

```
GET /api/notifications
```

أو البديل التفصيلي:
```
GET /api/user/notifications/private
```

### نموذج الإشعار عند تطابق تنبيه

```json
{
  "id": 15,
  "user_id": 7,
  "message": "Matching unit found for Ahmed Al-Harbi: المشروع الأول / A-101",
  "event_type": "unit_search_alert_matched",
  "context": {
    "alert_id": 42,
    "contract_unit_id": 88,
    "contract_id": 5,
    "client_name": "Ahmed Al-Harbi",
    "client_mobile": "+966501234567"
  },
  "status": "pending"
}
```

### سلوك Polling (بديل عن WebSocket)

إذا لم يكن WebSocket متاحاً، نفذ polling كل 30 ثانية:

```javascript
// Polling للإشعارات الجديدة
setInterval(async () => {
  const response = await fetch('/api/notifications', {
    headers: { Authorization: `Bearer ${token}` }
  });
  const data = await response.json();
  const unread = data.data?.filter(n => n.status === 'pending') ?? [];
  updateNotificationBadge(unread.length);
}, 30000);
```

### مميزات الإشعار الداخلي

الإشعارات تأتي عبر `event_type = 'unit_search_alert_matched'`. بعد الضغط على الإشعار:
1. أرسل `PATCH /api/notifications/{id}/read` أو `POST /api/notifications/{id}/read`
2. انتقل إلى صفحة تفاصيل التنبيه `GET /api/sales/units/search-alerts/{alert_id}`
3. اعرض `last_matched_unit` بوضوح مع رابط لتفاصيل الوحدة

---

## 8. سلوك SMS كقناة ثانوية اختيارية

### ⚠️ تحذيرات مهمة خاصة بـ SMS

1. **SMS ليس مصدر الحقيقة.** الإشعار الداخلي هو المصدر الوحيد.
2. **SMS قد لا يُرسل** لأسباب متعددة موثقة في الكود.
3. **لا تعرض أخطاء Twilio الخام للمستخدم** — قدم رسائل عامة.
4. **لا ترسل مفاتيح Twilio من الواجهة** — الـ Backend يتولى هذا.
5. موافقة العميل `client_sms_opt_in = true` مطلوبة لإرسال SMS.

### أسباب تخطي SMS (skip_reason)

| skip_reason | الرسالة المناسبة للمستخدم |
|---|---|
| `sms_disabled` | SMS غير مفعّل حالياً |
| `twilio_not_configured` | خدمة SMS غير متوفرة |
| `sms_opt_in_missing` | العميل لم يوافق على استقبال SMS |
| `invalid_phone` | رقم الهاتف غير صحيح |
| `registered_sender_id_required` | متطلبات المرسل السعودي غير مستوفاة |
| `sms_body_url_blocked` | الرسالة تحتوي رابط URL محظور |
| `sms_body_phone_number_blocked` | الرسالة تحتوي رقم هاتف محظور |
| `outside_sms_sending_window` | خارج نافذة الإرسال المسموح بها |
| `sms_throttled` | تم إرسال SMS مؤخراً لهذا التنبيه |
| `unit_not_available` | الوحدة لم تعد متاحة عند محاولة الإرسال |
| `alert_deleted` | التنبيه تم حذفه |
| `alert_cancelled` | التنبيه ملغى |
| `alert_expired` | التنبيه منتهي الصلاحية |

### ما يجب عرضه للمستخدم

```
✅ تم تطابق التنبيه — تم إرسال إشعار داخلي
ℹ️  SMS: تم التخطي (العميل لم يوافق على استقبال SMS)
```

**لا** تعرض: `"Twilio error: Account SID not found"` أو أي خطأ تقني خام.

---

## 9. جدول تعيين الحقول (Arabic Label ↔ API Field)

| التسمية العربية | الحقل في API | النوع | القيمة الافتراضية |
|---|---|---|---|
| اسم العميل | `client_name` | string\|null | — |
| جوال العميل | `client_mobile` | string | **مطلوب** |
| بريد العميل | `client_email` | email\|null | — |
| موافقة SMS | `client_sms_opt_in` | boolean | false |
| وقت الموافقة على SMS | `client_sms_opted_in_at` | datetime\|null | تلقائي |
| لغة SMS | `client_sms_locale` | string\|null | — |
| المدينة | `city_id` | integer\|null | — |
| الحي | `district_id` | integer\|null | — |
| المشروع | `project_id` | integer\|null | — |
| نوع الوحدة | `unit_type` | string\|null | — |
| الدور | `floor` | string\|null | — |
| السعر الأدنى | `min_price` | decimal\|null | — |
| السعر الأقصى | `max_price` | decimal\|null | — |
| المساحة الأدنى | `min_area` | decimal\|null | — |
| المساحة الأقصى | `max_area` | decimal\|null | — |
| غرف النوم (الحد الأدنى) | `min_bedrooms` | integer\|null | — |
| غرف النوم (الحد الأقصى) | `max_bedrooms` | integer\|null | — |
| نص البحث | `query_text` | string\|null | — |
| الحالة | `status` | enum | `active` |
| تاريخ الانتهاء | `expires_at` | datetime\|null | +30 يوم تلقائياً |
| الموظف المنشئ | `sales_staff_id` | integer | تلقائي من التوكن |

---

## 10. قواعد التحقق من الصحة في الواجهة الأمامية

### عند الإنشاء (POST)

```javascript
const createAlertRules = {
  client_mobile: { required: true, maxLength: 50 },
  client_name: { required: false, maxLength: 255 },
  client_email: { required: false, format: 'email', maxLength: 255 },
  client_sms_opt_in: { required: false, type: 'boolean' },
  city_id: { required: false, type: 'integer', positive: true },
  district_id: { required: false, type: 'integer', positive: true },
  project_id: { required: false, type: 'integer', positive: true },
  min_price: { required: false, type: 'number', min: 0 },
  max_price: { required: false, type: 'number', min: 0, mustBeGte: 'min_price' },
  min_area: { required: false, type: 'number', min: 0 },
  max_area: { required: false, type: 'number', min: 0, mustBeGte: 'min_area' },
  min_bedrooms: { required: false, type: 'integer', min: 0, max: 255 },
  max_bedrooms: { required: false, type: 'integer', min: 0, max: 255, mustBeGte: 'min_bedrooms' },
  query_text: { required: false, maxLength: 255 },
  expires_at: { required: false, type: 'date', mustBeAfter: 'now' },
  // محظور تماماً:
  page: 'NEVER_SEND',
  per_page: 'NEVER_SEND',
  sort_by: 'NEVER_SEND',
  sort_dir: 'NEVER_SEND',
};
```

### رسائل الخطأ بالعربية

| الخطأ | الرسالة |
|---|---|
| `client_mobile` مطلوب | رقم جوال العميل مطلوب |
| `max_price` < `min_price` | السعر الأقصى يجب أن يكون أكبر من أو يساوي السعر الأدنى |
| `max_area` < `min_area` | المساحة الأقصى يجب أن تكون أكبر من أو تساوي المساحة الأدنى |
| `max_bedrooms` < `min_bedrooms` | عدد الغرف الأقصى يجب أن يكون أكبر من أو يساوي الأدنى |
| `expires_at` في الماضي | تاريخ الانتهاء يجب أن يكون في المستقبل |
| `page` / `per_page` / `sort_by` / `sort_dir` مُرسَل | هذه الحقول غير مسموح بها في طلبات الإنشاء والتحديث |

---

## 11. تعيين شارات الحالة (Status Badges)

| القيمة | التسمية | اللون المقترح |
|---|---|---|
| `active` | نشط | أخضر |
| `paused` | متوقف مؤقتاً | أصفر |
| `matched` | تم التطابق ✓ | أزرق / بنفسجي |
| `cancelled` | ملغى | رمادي |

### متى تتغير الحالة تلقائياً؟

- `active` → `matched`: عند توفر وحدة مطابقة (إذا `close_after_first_match = true` في الإعدادات)
- `active` → `cancelled`: عند حذف التنبيه (DELETE endpoint)
- **الواجهة الأمامية لا تتحكم في هذا التغيير** — يحدث تلقائياً في الخلفية

---

## 12. تعيين قنوات التسليم (Delivery Channels)

| `delivery_channel` | التسمية | متى يُرسل؟ |
|---|---|---|
| `system_notification` | إشعار داخلي | دائماً عند التطابق (مصدر الحقيقة) |
| `sms` | رسالة SMS | اختياري — يتطلب: sms_enabled + opt_in + Twilio مُعدَّل |

### حالات التسليم

| `status` | التسمية | المعنى |
|---|---|---|
| `pending` | قيد المعالجة | في الانتظار |
| `sent` | تم الإرسال | نجح الإرسال |
| `skipped` | تم التخطي | متعمد — راجع `skip_reason` |
| `failed` | فشل | خطأ غير متوقع — راجع `error_message` |

---

## 13. معالجة الأخطاء في الواجهة الأمامية

### رموز HTTP المتوقعة

| الكود | السبب | التصرف المقترح |
|---|---|---|
| `201 Created` | تم إنشاء التنبيه | اعرض رسالة نجاح وأغلق الـ Modal |
| `200 OK` | GET / PATCH / DELETE ناجح | حدّث الـ State |
| `401 Unauthorized` | التوكن منتهي أو مفقود | أعد توجيه لصفحة تسجيل الدخول |
| `403 Forbidden` | لا يملك الصلاحية أو يحاول الوصول لتنبيه شخص آخر | اعرض "غير مسموح لك بالوصول لهذا التنبيه" |
| `404 Not Found` | alert_id غير موجود أو محذوف | اعرض "التنبيه غير موجود" |
| `422 Unprocessable Entity` | أخطاء التحقق من الصحة | اعرض الأخطاء بجانب الحقول المعنية |
| `500 Internal Server Error` | خطأ في الخادم | اعرض "حدث خطأ، يرجى المحاولة مجدداً" |

### هيكل خطأ 422

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "client_mobile": ["رقم جوال العميل مطلوب"],
    "max_price": ["The max_price field must be greater than or equal to min_price."],
    "sort_by": ["The sort by field is prohibited."]
  }
}
```

### كيفية عرض الأخطاء

```javascript
// مثال على عرض الأخطاء من الـ API
if (response.status === 422) {
  const { errors } = await response.json();
  Object.entries(errors).forEach(([field, messages]) => {
    setFieldError(field, messages[0]);
  });
}
```

---

## 14. قائمة قبول المهام (Acceptance Checklist)

### الوظائف الأساسية

- [ ] يمكن للموظف البحث عن وحدات عبر `GET /api/sales/units/search`
- [ ] عند النتائج الفارغة، يظهر زر "إنشاء تنبيه"
- [ ] الـ Modal يفتح مع ملء تلقائي لمعايير البحث الحالية
- [ ] يمكن إنشاء تنبيه بنجاح `POST /api/sales/units/search-alerts` → 201
- [ ] `query_text` يُرسل بدلاً من `q` عند إنشاء التنبيه
- [ ] `page`, `per_page`, `sort_by`, `sort_dir` لا تُرسل في POST/PATCH
- [ ] حقل `client_mobile` إلزامي ويُعطي خطأ واضح عند الغياب
- [ ] حالة checkbox الموافقة على SMS تُحفظ صحيحة

### القائمة وصفحة التفاصيل

- [ ] القائمة تُعيد فقط تنبيهات الموظف الحالي (ما لم يكن admin/leader)
- [ ] الفلتر بـ `status` يعمل
- [ ] Pagination يعمل مع `per_page`
- [ ] صفحة التفاصيل تعرض `last_matched_unit` إن وجدت
- [ ] صفحة التفاصيل تعرض `deliveries` مع حالة كل قناة

### الإشعارات

- [ ] جرس الإشعارات يعرض الإشعارات الجديدة
- [ ] إشعار `unit_search_alert_matched` يظهر بوضوح
- [ ] الضغط على الإشعار ينتقل لصفحة التنبيه المطابق
- [ ] تحديث حالة الإشعار إلى "مقروء" يعمل

### الحالة والأمان

- [ ] لا يستطيع الموظف الوصول لتنبيه موظف آخر (403)
- [ ] رسائل الخطأ لا تحتوي على بيانات Twilio أو مفاتيح النظام
- [ ] حالة `matched` تُعرض بشكل واضح مع معلومات الوحدة المطابقة
- [ ] عملية الحذف (DELETE) تُظهر تأكيداً للمستخدم

---

## 15. تحذيرات مهمة للفريق الأمامي

### 🚫 لا تفعل هذا

| محظور | السبب |
|---|---|
| إرسال `page`, `per_page`, `sort_by`, `sort_dir` في POST/PATCH | الـ API يرفضها بـ 422 (حقول `prohibited`) |
| استخدام `q` بدلاً من `query_text` عند الإنشاء | `q` للبحث فقط، `query_text` للتنبيهات |
| اعتبار SMS دليلاً على التطابق | SMS قد لا يُرسل لأسباب عديدة |
| عرض أخطاء Twilio الخام للمستخدم | يكشف تفاصيل تقنية حساسة |
| إرسال مفاتيح Twilio أو secrets من الواجهة | مخاطر أمنية |
| تجاهل حقل `unresolved` | (للإشعارات الداخلية) — اعرض حالة التسليم بوضوح |
| افتراض أن `sms_sent_at` يعني "تم التطابق" | الإشعار الداخلي هو الدليل |

### ✅ افعل هذا

- اعتمد `status = matched` + `last_system_notified_at` كمصدر حقيقة
- اعرض `skip_reason` بصيغة وصفية للمستخدم (راجع جدول skip_reason)
- أضف refresh تلقائي للقائمة بعد إنشاء التنبيه
- اعرض "سينتهي التنبيه بتاريخ ..." من `expires_at`
- استخدم `deliveries[]` لعرض تاريخ التسليم لكل قناة

---

## 16. اقتراحات لإدارة الـ State

```javascript
// نموذج State مقترح
const alertsState = {
  list: [],            // SalesUnitSearchAlert[]
  total: 0,
  currentPage: 1,
  perPage: 15,
  statusFilter: 'all', // 'all' | 'active' | 'paused' | 'matched' | 'cancelled'
  loading: false,
  error: null,
  
  // للنافذة الحوارية
  modal: {
    open: false,
    prefillCriteria: {},  // من صفحة البحث
  }
};
```
