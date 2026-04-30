# حزمة Postman لتنبيهات بحث وحدات المبيعات

## 1. ما هي هذه الحزمة؟

هذه الحزمة توثق وتختبر واجهات Sales Unit Search Alerts في نظام RAKEZ ERP باستخدام Postman. الملفات الأساسية:

- `docs/postman/rakez-sales-unit-search-alerts.postman_collection.json`
- `docs/postman/rakez-local.postman_environment.json`
- `docs/postman/rakez-staging-template.postman_environment.json`

الحزمة مكتوبة بالعربية لمساعدة فريق الفرونت على فهم التدفق، payloads، response shape، الصلاحيات، وحالات SMS بدون قراءة كود Laravel بالكامل.

## 2. ما هي ميزة Sales Unit Search Alerts؟

موظف المبيعات يبحث عن وحدة باستخدام `/api/sales/units/search`. إذا لم يجد نتيجة مناسبة، يستطيع تسجيل طلب عميل بمعايير البحث. عند توفر وحدة مطابقة لاحقًا، النظام ينشئ إشعارًا داخليًا لموظف المبيعات. SMS للعميل اختياري ولا يعتبر مصدر الحقيقة.

## 3. لماذا نستخدم Postman هنا؟

نستخدم Postman لتأكيد أن الفرونت يستدعي المسارات الصحيحة، يرسل الحقول الصحيحة، يتعامل مع validation، ويفهم الفرق بين إشعار النظام وSMS. الاختبارات الإنتاجية محمية بمتغيرات تمنع الكتابة أو SMS live افتراضيًا.

## 4. طريقة استيراد collection في Postman

1. افتح Postman.
2. اختر `Import`.
3. اختر ملف `docs/postman/rakez-sales-unit-search-alerts.postman_collection.json`.
4. ستظهر collection باسم `RAKEZ - تنبيهات بحث وحدات المبيعات`.

## 5. طريقة استيراد environment

للمحلي:

1. اختر `Import`.
2. اختر `docs/postman/rakez-local.postman_environment.json`.
3. اختر البيئة `RAKEZ Local - تنبيهات بحث وحدات المبيعات`.

للـ staging:

1. استورد `docs/postman/rakez-staging-template.postman_environment.json`.
2. انسخها إلى بيئة خاصة بك.
3. املأ بيانات الدخول والمعرفات الحقيقية من staging.

## 6. طريقة ضبط `base_url`

في المحلي استخدم مثالًا مثل:

```text
base_url=http://127.0.0.1:8000
api_url={{base_url}}/api
```

لا تضع `/api` داخل `base_url` لأن `api_url` يضيفها.

## 7. طريقة تسجيل الدخول وحفظ التوكن

شغّل أحد طلبات فولدر `المصادقة`:

- `تسجيل دخول موظف مبيعات`
- `تسجيل دخول قائد مبيعات`
- `تسجيل دخول مدير`

المسار الفعلي هو:

```http
POST /api/login
```

الاستجابة تحتوي:

```json
{
  "access_token": "token",
  "user": {}
}
```

اختبار Postman يحفظ `access_token` تلقائيًا في `auth_token`.

## 8. ترتيب تشغيل الطلبات المقترح

1. `المصادقة / تسجيل دخول موظف مبيعات`.
2. `بحث وحدات المبيعات / جلب فلاتر البحث`.
3. جرّب طلبات البحث حسب الفلاتر.
4. إذا رجع البحث بدون نتائج، شغّل `تنبيهات البحث عن وحدة / إنشاء تنبيه جديد بعد فشل البحث`.
5. شغّل `عرض تنبيهاتي`.
6. شغّل `عرض تنبيه محدد`.
7. اختياريًا في بيئة محلية فقط: فعّل `allow_write_smoke_tests=true` لتجربة إنشاء/حذف smoke alert.

## 9. شرح كل فولدر داخل Postman

- `المصادقة`: تسجيل دخول وخروج وحفظ التوكن.
- `بحث وحدات المبيعات`: طلبات read-only للبحث والفلاتر.
- `تنبيهات البحث عن وحدة`: إنشاء وعرض وتعديل وإلغاء alerts.
- `الصلاحيات والملكية`: سيناريوهات ملكية alert وصلاحيات قائد المبيعات/المدير.
- `المطابقة والإشعارات`: عرض UserNotification واختبار اختياري لإنشاء وحدة مطابقة عند السماح بالكتابة.
- `اختبارات إنتاج آمنة`: smoke tests مصممة لتجنب الكتابة وSMS افتراضيًا.

## 10. شرح المتغيرات المهمة

- `base_url`: رابط التطبيق بدون `/api`.
- `api_url`: رابط API ويكون غالبًا `{{base_url}}/api`.
- `auth_token`: Sanctum token المحفوظ بعد login.
- `sales_email`, `sales_password`: بيانات موظف مبيعات.
- `sales_leader_email`, `sales_leader_password`: بيانات قائد مبيعات.
- `admin_email`, `admin_password`: بيانات مدير.
- `customer_name`, `customer_phone`: بيانات عميل اختبار. لا تستخدم بيانات حقيقية بدون موافقة.
- `city_id`, `district_id`, `project_id`: معرفات موجودة في قاعدة البيانات.
- `unit_type`, `min_price`, `max_price`, `min_area`, `max_area`, `min_bedrooms`, `max_bedrooms`: فلاتر البحث والتنبيه.
- `alert_id`: يحفظ تلقائيًا بعد إنشاء alert.
- `matched_unit_id`: يحفظ من أول نتيجة بحث إن وجدت.
- `notification_id`: يحفظ من أول إشعار مستخدم إن وجد.
- `allow_write_smoke_tests`: يجب أن يبقى `false` إلا عند السماح بكتابة بيانات اختبار.
- `allow_sms_live_test`: يجب أن يبقى `false`. لا توجد طلبات ترسل SMS مباشر في هذه الحزمة.

## 11. شرح بيانات seeders المستخدمة

تم اكتشاف القيم التالية من `UsersSeeder` و`AdminUserSeeder`:

| الدور | البريد | كلمة المرور |
|---|---|---|
| مدير | `admin@rakez.com` | `password` |
| قائد مبيعات | `sales.leader@rakez.com` | `password` |
| موظف مبيعات | `sales@rakez.com` | `password` |

`ContractsSeeder` ينشئ مدنًا وأحياءً وعقودًا ووحدات، لكن `project_id`, `city_id`, و`district_id` قد تختلف حسب قاعدة البيانات. عدّل المتغيرات من نتائج `/api/sales/units/filters` أو من قاعدة البيانات المحلية.

لا يوجد متغير ثابت لموظف مبيعات ثانٍ في environment. لاختبار منع الوصول لتنبيه موظف آخر، أنشئ مستخدم مبيعات آخر أو استخدم مستخدم seeded آخر من جدول users.

## 12. كيف يعرف الفرونت أن البحث رجع بدون نتائج؟

مسار البحث:

```http
GET /api/sales/units/search
```

يرجع:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

إذا كانت `data.length === 0` أو `meta.total === 0`، اعرض empty state.

## 13. متى يظهر زر "تسجيل طلب عميل عند عدم توفر وحدة"؟

اعرض الزر عندما:

- البحث اكتمل بدون loading.
- `success=true`.
- لا توجد نتائج.
- المستخدم لديه صلاحية `sales.search_alerts.view`.
- الصفحة ليست في حالة خطأ.

النص المقترح:

```text
تسجيل طلب عميل عند عدم توفر وحدة
```

## 14. ما payload المطلوب لإنشاء alert؟

المسار:

```http
POST /api/sales/units/search-alerts
```

مثال:

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

لا ترسل `page`, `per_page`, `sort_by`, أو `sort_dir`. هذه الحقول مرفوضة في requests الخاصة بحفظ alert.

## 15. ما response المتوقع بعد إنشاء alert؟

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
      "query_text": "U-001"
    },
    "status": "active",
    "last_notification": {},
    "last_matched_unit": null,
    "expires_at": "ISO_DATE",
    "deliveries": [],
    "created_at": "ISO_DATE",
    "updated_at": "ISO_DATE",
    "deleted_at": null
  }
}
```

## 16. كيف تعرض قائمة alert للمستخدم؟

المسار:

```http
GET /api/sales/units/search-alerts
```

يرجع:

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

موظف المبيعات يرى alerts الخاصة به فقط. المدير وقائد المبيعات يمكن أن يروا نطاقًا أوسع حسب الصلاحيات.

## 17. كيف تعرض status؟

- `active`: الطلب نشط وينتظر وحدة مطابقة.
- `paused`: الطلب موقوف مؤقتًا ولا يجب اعتباره قيد المطابقة.
- `matched`: تم العثور على وحدة مطابقة وتم إشعار موظف المبيعات داخل النظام.
- `cancelled`: تم إلغاء الطلب أو حذفه.

## 18. كيف تعرض SMS state؟

تأتي من `deliveries` عندما تكون محملة في response:

- `pending`: SMS مسجل ولم تتم معالجته بعد.
- `sent`: تم الإرسال وحفظ `twilio_sid`.
- `skipped`: لم يتم إرسال SMS عمدًا، والسبب في `skip_reason`.
- `failed`: فشل Twilio أو الخدمة الخارجية، والتفاصيل الآمنة في `error_message`.

## 19. الفرق بين إشعار النظام وSMS

إشعار النظام هو المسار الأساسي. عند المطابقة، backend ينشئ `UserNotification` لموظف المبيعات ويعتبر alert `matched` بعد نجاح هذا الإشعار.

SMS مسار خارجي اختياري للعميل. فشل SMS أو تعطيله لا يعني فشل الميزة.

## 20. لماذا Twilio لا يعمل افتراضيًا؟

لأن `SALES_UNIT_SEARCH_ALERT_SMS_ENABLED=false` في الإعدادات الافتراضية. هذا يمنع إرسال رسائل حقيقية أثناء التطوير والاختبار. وعند تفعيل SMS فعليًا يجب توفير `TWILIO_ACCOUNT_SID` و`TWILIO_AUTH_TOKEN` ومرسل صالح عبر `SALES_UNIT_SEARCH_ALERT_FROM` أو `TWILIO_PHONE_NUMBER`.

## 21. كيف نجرب بدون إرسال SMS حقيقي؟

- استخدم `client_sms_opt_in=false`.
- أبقِ `allow_sms_live_test=false`.
- أبقِ إعدادات SMS معطلة في `.env`.
- اعتمد على `UserNotification` وقائمة alerts للتحقق من المطابقة.
- حالات skipped موثقة في tests ولا تعتبر فشلًا.

## 22. كيف نجري production smoke test بأمان؟

1. استخدم environment منفصلة ولا تحفظ secrets داخل collection.
2. أبقِ `allow_write_smoke_tests=false` للطلبات read-only.
3. شغّل:
   - تسجيل الدخول.
   - فحص route البحث.
   - بحث read-only.
   - جلب فلاتر read-only.
   - عرض تنبيهات المستخدم.
4. لا تجعل `allow_write_smoke_tests=true` إلا بموافقة صريحة.
5. لا تجعل `allow_sms_live_test=true` إلا بعد التأكد من Twilio، Sender ID السعودي، وموافقة العميل.

## 23. ما هي الطلبات read-only؟

- `POST /api/login`
- `GET /api/sales/units/search`
- `GET /api/sales/units/filters`
- `GET /api/sales/units/search-alerts`
- `GET /api/sales/units/search-alerts/{alert}`
- `GET /api/notifications`

## 24. ما هي الطلبات التي تكتب على قاعدة البيانات؟

- `POST /api/sales/units/search-alerts`
- `PATCH /api/sales/units/search-alerts/{alert}`
- `DELETE /api/sales/units/search-alerts/{alert}`
- `POST /api/contracts/units/store/{contractId}` في فولدر المطابقة عند السماح
- `PATCH /api/notifications/{id}/read`
- `POST /api/logout` يحذف tokens

## 25. تحذيرات قبل استخدام staging/production

- لا تشغل write smoke tests على production إلا بعد موافقة صريحة.
- لا تفعل live SMS إلا بعد التأكد من Twilio/Saudi Sender ID/opt-in.
- لا تضع credentials حقيقية داخل Postman collection.
- استخدم environment variables فقط.
- لا تستخدم رقم عميل حقيقي بدون موافقة موثقة.
- لا تعرض `error_message` من Twilio للمستخدم النهائي كما هو.

## 26. Newman commands إذا متاح

تشغيل read-only محلي:

```bash
newman run docs/postman/rakez-sales-unit-search-alerts.postman_collection.json \
  -e docs/postman/rakez-local.postman_environment.json \
  --folder "اختبارات إنتاج آمنة"
```

تشغيل staging مع environment مخصصة:

```bash
newman run docs/postman/rakez-sales-unit-search-alerts.postman_collection.json \
  -e path/to/staging.postman_environment.json \
  --folder "اختبارات إنتاج آمنة"
```

قبل تشغيل أي كتابة:

```bash
newman run docs/postman/rakez-sales-unit-search-alerts.postman_collection.json \
  -e path/to/staging.postman_environment.json \
  --folder "اختبارات إنتاج آمنة" \
  --env-var "allow_write_smoke_tests=true"
```

لا تمرر `allow_sms_live_test=true` إلا بموافقة صريحة وبعد مراجعة إعدادات Twilio وسياسة الرسائل في السعودية.
