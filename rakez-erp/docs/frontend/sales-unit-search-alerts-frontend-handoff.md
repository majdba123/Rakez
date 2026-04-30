# تسليم فرونت: Sales Unit Search Alerts

## A. الفكرة التجارية ببساطة

موظف المبيعات يبحث عن وحدة مناسبة للعميل. إذا لم يجد نتائج، يعرض النظام خيار تسجيل طلب عميل. الموظف يدخل اسم العميل ورقم الجوال ومعايير البحث. عند إضافة وحدة جديدة أو رجوع وحدة للتوفر وكانت مطابقة للمعايير، النظام يخبر موظف المبيعات داخل النظام. SMS للعميل اختياري حسب إعدادات النظام وموافقة العميل ولا يجب اعتباره مصدر الحقيقة.

## B. UX flow المطلوب

1. صفحة البحث عن الوحدات تستخدم `GET /api/sales/units/search`.
2. عند وجود نتائج، اعرض كروت/جدول الوحدات كالمعتاد.
3. عند عدم وجود نتائج، اعرض empty state واضح.
4. أظهر زر `تسجيل طلب عميل` في empty state.
5. افتح modal لإنشاء alert بمعايير البحث الحالية.
6. أضف صفحة أو تبويب `طلبات العملاء / تنبيهات البحث`.
7. صفحة تفاصيل alert تعرض بيانات العميل، المعايير، الحالة، وآخر وحدة مطابقة إن وجدت.
8. عند `matched` اعرض أن وحدة مطابقة توفرت وأن موظف المبيعات تم إشعاره.
9. إشعار النظام يظهر في notification bell أو قائمة الإشعارات.
10. إذا كانت `deliveries` ظاهرة من API، اعرض حالة SMS كحالة ثانوية.

## C. UI components المقترحة

- Empty state عند عدم وجود نتائج.
- CTA واضح: `تسجيل طلب عميل`.
- Modal form لإنشاء alert.
- Alert status badge.
- SMS status badge.
- Timeline أو activity section إذا response يحتوي `deliveries`.
- Toast عند نجاح إنشاء alert.
- Toast عند فشل validation.
- Notification bell أو real-time notification إذا WebSocket مفعل، مع fallback polling.

## D. Form fields

| الحقل | label عربي | ملاحظات |
|---|---|---|
| `client_name` | اسم العميل | اختياري |
| `client_mobile` | رقم جوال العميل | مطلوب عند الإنشاء |
| `client_sms_opt_in` | موافقة العميل على SMS | افتراضيًا false |
| `city_id` | المدينة | اختياري |
| `district_id` | الحي | اختياري |
| `project_id` | المشروع | يطابق `contracts.id` |
| `unit_type` | نوع الوحدة | اختياري |
| `floor` | الطابق | نص/رقم، backend يخزنه كنص |
| `min_price` | أقل سعر | اختياري |
| `max_price` | أعلى سعر | اختياري ويجب أن يكون >= أقل سعر |
| `min_area` | أقل مساحة | اختياري |
| `max_area` | أعلى مساحة | اختياري ويجب أن يكون >= أقل مساحة |
| `min_bedrooms` | أقل عدد غرف | اختياري |
| `max_bedrooms` | أعلى عدد غرف | اختياري ويجب أن يكون >= أقل عدد غرف |
| `query_text` | نص البحث | استخدمه بدل `q` عند إنشاء alert |
| `expires_at` | تاريخ انتهاء الطلب | اختياري، يجب أن يكون بعد الوقت الحالي |

## E. Frontend validation

- `client_mobile` مطلوب عند إنشاء alert.
- رقم الجوال يجب أن يكون نصًا صالحًا للعرض والإرسال. backend يقبل string حتى 50 حرفًا، وSMS job يتحقق لاحقًا من صيغة `+?[0-9]{8,15}`.
- `min_price <= max_price`.
- `min_area <= max_area`.
- `min_bedrooms <= max_bedrooms`.
- `expires_at` يجب أن يكون تاريخًا مستقبليًا إن تم إرساله.
- `client_sms_opt_in=true` يعني أن العميل وافق على SMS حسب سياسة العمل، لكنه لا يضمن إرسال SMS.
- لا ترسل `page`, `per_page`, `sort_by`, `sort_dir` عند إنشاء أو تعديل alert.
- في البحث استخدم `q`. عند إنشاء alert حوّل `q` إلى `query_text`.

## F. API integration guide

### تسجيل الدخول

```http
POST /api/login
```

Auth: لا يحتاج token.

Request:

```json
{
  "email": "sales@rakez.com",
  "password": "password"
}
```

Success:

```json
{
  "access_token": "token",
  "user": {}
}
```

احفظ `access_token` واستخدمه كـ Bearer token.

### بحث وحدات المبيعات

```http
GET /api/sales/units/search
```

Auth: مطلوب `auth:sanctum` وصلاحية `sales.projects.view`.

Query examples:

```text
?project_id=1&unit_type=villa&min_price=100000&max_price=900000&q=U-001
```

Success shape:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "unit_number": "U-001",
      "unit_type": "villa",
      "status": "available",
      "price": 500000,
      "area": 120,
      "bedrooms": 3,
      "floor": "2",
      "street_width": null,
      "description": null,
      "project": {
        "id": 1,
        "name": "اسم المشروع",
        "city": "الرياض",
        "district": "حي الملقا",
        "developer_name": "اسم المطور"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

Empty state:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "total": 0
  }
}
```

تعامل الفرونت: إذا `data` فارغة، اعرض CTA لتسجيل طلب عميل.

### جلب فلاتر البحث

```http
GET /api/sales/units/filters
```

Auth: مطلوب.

Success:

```json
{
  "success": true,
  "data": {
    "cities": [],
    "districts": {},
    "unit_types": [],
    "bedrooms_range": { "min": null, "max": null },
    "area_range": { "min": null, "max": null },
    "price_range": { "min": null, "max": null },
    "statuses": []
  }
}
```

### إنشاء alert

```http
POST /api/sales/units/search-alerts
```

Auth: مطلوب `sales.search_alerts.view`.

Request:

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
  "max_bedrooms": 5,
  "query_text": "U-001"
}
```

Success:

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
      "project_id": 1,
      "unit_type": "villa",
      "floor": "2",
      "min_price": 100000,
      "max_price": 900000,
      "query_text": "U-001"
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
    "expires_at": "ISO_DATE",
    "deliveries": [],
    "created_at": "ISO_DATE",
    "updated_at": "ISO_DATE",
    "deleted_at": null
  }
}
```

Common validation errors:

- 422 عند غياب `client_mobile`.
- 422 عند `min_price > max_price`.
- 422 عند إرسال `page`, `per_page`, `sort_by`, `sort_dir`.
- 422 عند `project_id` غير موجود.

Frontend handling:

- أظهر loading أثناء الحفظ.
- عند 201 أغلق modal واعرض toast نجاح.
- احفظ `data.id` لتفاصيل alert.
- عند 422 اعرض رسائل validation بجانب الحقول.

### عرض قائمة alerts

```http
GET /api/sales/units/search-alerts
```

Auth: مطلوب.

Success:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

موظف المبيعات يرى alerts الخاصة به فقط. المدير أو من لديه `sales.search_alerts.manage` يرى نطاقًا أوسع.

### عرض alert محدد

```http
GET /api/sales/units/search-alerts/{alert}
```

Auth: مطلوب.

تعامل مع:

- 200: اعرض التفاصيل.
- 403: المستخدم لا يملك alert ولا يملك صلاحية إدارة.
- 404: alert غير موجود أو محذوف.

### تعديل alert

```http
PATCH /api/sales/units/search-alerts/{alert}
```

Request:

```json
{
  "status": "paused",
  "max_price": 800000
}
```

الحالات المسموحة: `active`, `paused`, `matched`, `cancelled`.

### إلغاء/حذف alert

```http
DELETE /api/sales/units/search-alerts/{alert}
```

Success:

```json
{
  "success": true,
  "message": "Sales unit search alert cancelled successfully"
}
```

backend يضع `status=cancelled` ثم soft delete.

### إشعارات المستخدم

```http
GET /api/notifications
```

Auth: مطلوب.

Success:

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 10,
      "message": "Matching unit found for ...",
      "event_type": "unit_search_alert_matched",
      "context": {
        "alert_id": 1,
        "contract_unit_id": 5
      },
      "status": "pending",
      "is_public": false,
      "created_at": "2026-04-30 12:00:00"
    }
  ],
  "meta": {
    "pagination": {
      "total": 1,
      "count": 1,
      "per_page": 15,
      "current_page": 1,
      "total_pages": 1,
      "has_more_pages": false
    }
  }
}
```

رسالة العرض المقترحة بالعربية:

```text
توفرت وحدة مطابقة لطلب العميل، يرجى التواصل معه.
```

## G. State management

احتفظ بالحالات التالية:

- `currentFilters`: فلاتر البحث الحالية.
- `searchResults`: نتائج البحث.
- `searchMeta`: pagination.
- `isSearchEmpty`: true إذا `data.length === 0`.
- `createAlertModalOpen`: حالة modal.
- `createAlertForm`: حقول العميل والمعايير.
- `createdAlertId`: آخر alert تم إنشاؤه.
- `alertsListCache`: قائمة alerts للمستخدم.
- `notificationState`: عدد الإشعارات، آخر إشعار، وحالة القراءة.
- `smsState`: حالة SMS من deliveries إن كانت متوفرة.

لا تستخدم optimistic update في إنشاء alert إلا إذا كان rollback واضحًا. الأفضل انتظار 201 ثم تحديث القائمة.

## H. Realtime/notifications

backend ينشئ `UserNotification` عند المطابقة ويرسل `UserNotificationEvent`. إذا كان WebSocket/Broadcast مفعلًا في الواجهة، يمكن الاستماع لقناة المستخدم حسب إعدادات المشروع الموجودة. إذا لم يكن realtime جاهزًا:

- اعمل polling لـ `/api/notifications`.
- أو refresh لقائمة alerts كل فترة.
- أو افحص alert details عند فتح صفحة الطلب.

لا تعتمد على SMS لإظهار matched؛ المصدر الأساسي هو alert `status=matched` و`last_system_notified_at`.

## I. Production behavior

- SMS قد يكون معطلًا في staging/production.
- الفرونت لا يجب أن يفترض أن SMS تم إرساله.
- اعرض `matched` كنقطة نجاح أساسية.
- SMS status ثانوي إذا عاد في `deliveries`.
- لا تعرض أخطاء Twilio الخام للعميل النهائي.
- إذا `sms.status=skipped` فهذا غالبًا قرار آمن وليس فشل ميزة.

## J. Acceptance checklist للفرونت

- البحث يعمل مع الفلاتر.
- empty result يعرض CTA لتسجيل alert.
- modal إنشاء alert يرسل payload صحيح.
- لا يتم إرسال `page/per_page/sort_by/sort_dir` عند إنشاء alert.
- alert يظهر في القائمة بعد الإنشاء.
- أخطاء ownership/security تعرض رسالة مناسبة.
- `matched` يظهر بوضوح كنجاح إشعار النظام.
- إشعار النظام يظهر في notification bell أو fallback polling.
- SMS disabled/skipped لا يظهر كفشل للميزة.
- labels العربية مكتملة.
- حالات loading/empty/error مصممة بعناية.
- الواجهة responsive على الجوال.
