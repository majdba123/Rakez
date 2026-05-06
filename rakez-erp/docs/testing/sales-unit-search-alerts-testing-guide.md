# دليل الاختبار — تنبيهات البحث عن وحدات المبيعات
**Sales Unit Search Alerts Testing Guide**
> الفرع: `yusuf` | التاريخ: 2026-05-05

---

## 1. تشغيل الاختبارات الآلية (Automated Tests)

### ملفات الاختبار الموجودة

| الملف | النوع | يغطي |
|---|---|---|
| `tests/Feature/Sales/SalesUnitSearchAlertTest.php` | Feature | CRUD، ملكية، تحقق صحة، بحث |
| `tests/Feature/Sales/SalesUnitSearchAlertMatchingTest.php` | Feature | مطابقة الوحدات، SMS، إشعارات، deduplication |
| `tests/Feature/Sales/SalesUnitSearchAlertTriggerTest.php` | Feature | Dispatch triggers من CSV، تحديث وحدات، إلغاء حجوزات |

### أوامر التشغيل

```bash
# تشغيل جميع اختبارات Search Alerts
php artisan test tests/Feature/Sales/SalesUnitSearchAlertTest.php
php artisan test tests/Feature/Sales/SalesUnitSearchAlertMatchingTest.php
php artisan test tests/Feature/Sales/SalesUnitSearchAlertTriggerTest.php

# تشغيل الثلاثة معاً
php artisan test tests/Feature/Sales/SalesUnitSearchAlert

# تشغيل اختبار بعينه
php artisan test tests/Feature/Sales/SalesUnitSearchAlertMatchingTest.php --filter="test_system_notification_is_sent_when_twilio_credentials_are_missing"
```

### الاختبارات الحالية والنتائج المتوقعة

#### SalesUnitSearchAlertTest.php

| الاختبار | المتوقع |
|---|---|
| `test_sales_user_can_create_list_show_update_and_delete_own_alert` | Happy path كامل — CRUD يمر بنجاح |
| `test_alert_with_client_sms_opt_in_stores_opt_in_timestamp_and_locale` | SMS opt-in + locale يُحفظان |
| `test_sales_user_cannot_access_another_users_alert` | 403 عند الوصول لتنبيه شخص آخر |
| `test_sales_leader_can_access_team_alerts` | القائد يرى تنبيهات فريقه |
| `test_validation_rejects_invalid_min_max_and_persistence_sort_fields` | 422 على max_price < min_price، و sort_by |
| `test_project_id_works_for_search_endpoint` | GET search مع project_id يُرجع وحدات المشروع فقط |

#### SalesUnitSearchAlertMatchingTest.php

| الاختبار | المتوقع |
|---|---|
| `test_system_notification_delivery_is_sent_before_sms_job_is_dispatched` | system_notification=sent، sms=pending، Job مُدفوع |
| `test_system_notification_is_sent_and_sms_skipped_when_sms_disabled` | sms_disabled → skipped، لكن إشعار داخلي = sent |
| `test_system_notification_is_sent_when_twilio_credentials_are_missing` | twilio_not_configured → sms skipped |
| `test_system_notification_survives_twilio_failure_and_sms_is_failed` | Twilio يفشل → sms=failed، لكن إشعار = sent |
| `test_opt_in_false_skips_sms_without_calling_twilio` | بدون opt-in → sms_opt_in_missing |
| `test_sms_job_can_send_for_matched_alert_with_pending_sms_delivery` | SMS يُرسل بنجاح مع twilio_sid |
| `test_remarching_same_alert_and_unit_does_not_send_duplicate_sms` | لا تكرار في الإرسال (deduplication) |
| `test_unit_not_available_when_sms_job_runs_marks_delivery_skipped` | الوحدة غير متاحة → unit_not_available |
| `test_available_unit_with_active_reservation_does_not_match` | وحدة بحجز confirmed لا تُطابَق |
| `test_available_unit_on_non_completed_contract_does_not_match` | مشروع draft لا يُطابَق |
| `test_duplicate_prevention_is_independent_per_channel` | كل قناة تُنشئ delivery واحدة فقط |
| `test_close_after_first_match_blocks_second_unit_delivery` | matched → يمنع delivery ثانية |

#### SalesUnitSearchAlertTriggerTest.php

| الاختبار | المتوقع |
|---|---|
| `test_manual_unit_create_and_update_dispatch_matching_job` | إنشاء/تحديث وحدة → Job مُدفوع |
| `test_queued_csv_import_dispatches_matching_job_for_created_units` | CSV import → Job |
| `test_queued_csv_import_remains_successful_when_alert_dispatch_fails_after_commit` | فشل dispatch لا يوقف CSV |
| `test_reservation_cancellation_dispatches_matching_when_unit_returns_available` | إلغاء حجز → وحدة تعود available → Job |

---

## 2. سيناريوهات QA اليدوية

### سيناريو 1 — تدفق Happy Path الكامل

**الخطوات:**
1. سجل دخول بحساب موظف مبيعات
2. اطلب `GET /api/sales/units/search?unit_type=villa&status=available`
3. لاحظ النتائج الفارغة أو غير المطابقة
4. أنشئ تنبيهاً:
   ```
   POST /api/sales/units/search-alerts
   { "client_mobile": "+966501234567", "unit_type": "villa", "min_price": 500000 }
   ```
5. تحقق من الاستجابة 201 مع `data.id`
6. شغّل `MatchUnitSearchAlertsJob` يدوياً أو أضف وحدة مطابقة
7. اطلب `GET /api/sales/units/search-alerts/{id}` وتحقق من:
   - `status = matched`
   - `last_matched_unit` ليس null
   - `last_system_notified_at` ليس null
   - `deliveries[].delivery_channel = system_notification` مع `status = sent`

**النتيجة المتوقعة:** إشعار داخلي موجود، حالة التنبيه = matched

---

### سيناريو 2 — SMS معطل (الوضع الافتراضي)

**الإعداد:** `SALES_UNIT_SEARCH_ALERT_SMS_ENABLED=false`

**الخطوات:** نفس الخطوات 1-7 أعلاه

**النتيجة المتوقعة:**
- `deliveries[]` يحتوي على:
  - `{ delivery_channel: "system_notification", status: "sent" }` ✅
  - `{ delivery_channel: "sms", status: "skipped", skip_reason: "sms_disabled" }` ✅
- الإشعار الداخلي يعمل مع ذلك

---

### سيناريو 3 — SMS بدون opt-in

**الإعداد:** `SALES_UNIT_SEARCH_ALERT_SMS_ENABLED=true`، Twilio مُعدَّل

**الخطوات:**
1. أنشئ تنبيهاً مع `client_sms_opt_in: false`
2. أضف وحدة مطابقة

**النتيجة المتوقعة:**
- SMS: `{ status: "skipped", skip_reason: "sms_opt_in_missing" }`
- الإشعار الداخلي: يعمل طبيعياً

---

### سيناريو 4 — الملكية والصلاحيات

**الخطوات:**
1. سجل دخول بحساب موظف أ وأنشئ تنبيهاً
2. سجل دخول بحساب موظف ب
3. اطلب `GET /api/sales/units/search-alerts/{id_of_a_alert}`

**النتيجة المتوقعة:** 403 Forbidden

---

### سيناريو 5 — التحقق من الحقول المحظورة

**الخطوات:**
```
POST /api/sales/units/search-alerts
{
  "client_mobile": "+966501234567",
  "sort_by": "price",
  "page": 1,
  "per_page": 10,
  "sort_dir": "asc"
}
```

**النتيجة المتوقعة:** 422 مع `errors.sort_by`, `errors.page`, `errors.per_page`, `errors.sort_dir`

---

### سيناريو 6 — نطاق السعر غير صحيح

**الخطوات:**
```
POST /api/sales/units/search-alerts
{ "client_mobile": "+966501234567", "min_price": 900000, "max_price": 100000 }
```

**النتيجة المتوقعة:** 422 مع `errors.max_price`

---

### سيناريو 7 — تحديث جزئي مع نطاق غير صحيح

**السياق:** التنبيه لديه `min_price=500000`

**الخطوات:**
```
PATCH /api/sales/units/search-alerts/{id}
{ "max_price": 100000 }
```

**النتيجة المتوقعة:** 422 — الـ `withValidator` يقارن max_price المُرسل مع min_price المحفوظ

---

### سيناريو 8 — إعادة مطابقة نفس الوحدة لا تُولد delivery مكررة

**الخطوات:**
1. أنشئ تنبيهاً مع `close_after_first_match=false` في الإعداد
2. أضف وحدة مطابقة
3. شغّل المطابقة مرتين لنفس الوحدة

**النتيجة المتوقعة:** delivery واحدة فقط لكل قناة (unique constraint)

---

### سيناريو 9 — إلغاء حجز يُعيد المطابقة

**الخطوات:**
1. أنشئ تنبيهاً
2. أنشئ وحدة بحجز confirmed (status=reserved)
3. ألغِ الحجز عبر `SalesReservationService::cancelReservation()`
4. تحقق من أن `MatchUnitSearchAlertsJob` تم dispatch

**النتيجة المتوقعة:** الوحدة تعود `available`، Job يُدفع تلقائياً

---

### سيناريو 10 — حذف التنبيه (Soft Delete)

**الخطوات:**
```
DELETE /api/sales/units/search-alerts/{id}
```

**التحقق:**
- الاستجابة: 200 مع message
- قاعدة البيانات: `deleted_at` ليس null
- `status` = `cancelled`
- GET /api/sales/units/search-alerts/{id} يُرجع 404

---

## 3. خطوات تشغيل Postman

1. **استيراد الملفات:**
   - `docs/postman/Sales_Unit_Search_Alerts.postman_collection.json`
   - `docs/postman/Rakez_Local.postman_environment.json`

2. **تعيين البيئة:** اختر "Rakez Local" من قائمة البيئات

3. **تحديث المتغيرات:**
   - `test_email` → بريد موظف مبيعات موجود في قاعدة البيانات المحلية
   - `test_password` → كلمة المرور المقابلة

4. **الترتيب المقترح:**
   ```
   1. POST Login              → يحفظ auth_token تلقائياً
   2. GET Sales Units Filters  → يتحقق من الفلاتر المتاحة
   3. GET Search Sales Units   → يبحث عن وحدات
   4. POST Create Alert        → يحفظ alert_id تلقائياً
   5. GET List Alerts          → يتحقق من القائمة
   6. GET Show Alert           → يتحقق من التفاصيل
   7. PATCH Update Alert       → يُحدِّث الحالة لـ paused
   8. GET My Notifications     → يتحقق من الإشعارات
   9. DELETE Cancel Alert      → يلغي التنبيه
   10. Run Negative Tests       → يتحقق من الأخطاء
   ```

5. **تشغيل Collection Runner:**
   - Collection Runner → حدد "RAKEZ ERP - Sales Unit Search Alerts"
   - البيئة: Rakez Local
   - الترتيب: كما هو أعلاه

---

## 4. تحقق من صحة الـ JSON

```bash
# Postman Collection
python -c "import json; json.load(open('docs/postman/Sales_Unit_Search_Alerts.postman_collection.json')); print('Collection: JSON valid')"

# Postman Environment
python -c "import json; json.load(open('docs/postman/Rakez_Local.postman_environment.json')); print('Environment: JSON valid')"
```

---

## 5. رموز الحالة المتوقعة

| العملية | الكود الناجح | أخطاء شائعة |
|---|---|---|
| POST Login | 200 | 422 (validation), 401 (credentials) |
| GET /units/search | 200 | 401, 403 |
| GET /units/filters | 200 | 401, 403 |
| POST /search-alerts | 201 | 422 (validation), 401, 403 |
| GET /search-alerts | 200 | 401, 403 |
| GET /search-alerts/{id} | 200 | 401, 403, 404 |
| PATCH /search-alerts/{id} | 200 | 422, 401, 403, 404 |
| DELETE /search-alerts/{id} | 200 | 401, 403, 404 |
| GET /notifications | 200 | 401 |

---

## 6. قائمة Regression

| الاختبار | الأهمية |
|---|---|
| ✅ إنشاء وحدة جديدة يُطلق Job المطابقة | عالية |
| ✅ تحديث وحدة (addUnit/updateUnit) يُطلق Job | عالية |
| ✅ CSV import يُطلق Job للوحدات الجديدة | عالية |
| ✅ إلغاء حجز يُعيد الوحدة available ويُطلق Job | عالية |
| ✅ فشل dispatch لا يوقف CSV/إضافة وحدة | عالية |
| ✅ إشعار داخلي يُنشأ دائماً عند التطابق | عالية |
| ✅ SMS لا يُرسل بدون opt-in | متوسطة |
| ✅ لا deduplication في deliveries | متوسطة |
| ✅ close_after_first_match يوقف التنبيه | متوسطة |
| ✅ وحدة غير متاحة عند تشغيل SMS Job → skipped | متوسطة |
| ✅ الموظف لا يرى تنبيهات زميله | عالية (أمان) |
| ✅ page/per_page/sort_by/sort_dir محظورة في POST | متوسطة |

---

## 7. ملاحظات بيئة الاختبار

### الإعدادات المطلوبة للاختبار المحلي

```env
SALES_UNIT_SEARCH_ALERTS_ENABLED=true
SALES_UNIT_SEARCH_ALERT_SMS_ENABLED=false    # ابدأ بـ false للتبسيط
SALES_UNIT_SEARCH_ALERT_DEFAULT_EXPIRATION_DAYS=30
SALES_UNIT_SEARCH_ALERT_CLOSE_AFTER_FIRST_MATCH=true
SALES_UNIT_SEARCH_ALERT_QUEUE=default
```

### لاختبار SMS (اختياري)

```env
SALES_UNIT_SEARCH_ALERT_SMS_ENABLED=true
TWILIO_SID=ACxxxxxxxx
TWILIO_TOKEN=xxxxxxxx
TWILIO_PHONE_NUMBER=+1234567890
SALES_UNIT_SEARCH_ALERT_REQUIRE_SMS_OPT_IN=true
```

> **تحذير:** لا تُفعِّل SMS في بيئة الإنتاج إلا بعد التحقق من Twilio Sender ID السعودي وإعداداته.

---

## 8. اختبارات API السلبية المقترحة (غير موجودة بعد)

| السيناريو | الحالة |
|---|---|
| إنشاء تنبيه بدون مصادقة → 401 | ✅ مغطى بـ Postman |
| إنشاء تنبيه ببريد إلكتروني غير صحيح → 422 | ⚠️ غير مكتوب في PHPUnit |
| تحديث تنبيه محذوف → 404 | ⚠️ غير مكتوب في PHPUnit |
| query_text > 255 حرف → 422 | ⚠️ غير مكتوب في PHPUnit |
| expires_at في الماضي → 422 | ⚠️ غير مكتوب في PHPUnit |
