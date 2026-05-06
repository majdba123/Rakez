# دليل الـ API — تنبيهات البحث عن وحدات المبيعات
**Sales Unit Search Alerts API Guide (Arabic)**
> مبني على قراءة الكود الفعلي | الفرع: `yusuf`

---

## 1. معلومات الاتصال الأساسية

| المعلومة | القيمة |
|---|---|
| Base URL | `http://your-domain.com/api` |
| نوع المصادقة | Sanctum Bearer Token |
| تنسيق البيانات | JSON |
| ترميز الأحرف | UTF-8 |

---

## 2. المصادقة

### الحصول على التوكن

```
POST /api/login
```

**الـ Headers:**
```
Accept: application/json
Content-Type: application/json
```

**Body:**
```json
{
  "email": "sales@example.com",
  "password": "secret"
}
```

**الاستجابة:**
```json
{
  "access_token": "1|abc123...",
  "user": { "id": 7, "name": "...", "email": "...", "type": "sales" }
}
```

### استخدام التوكن في كل طلب

```
Authorization: Bearer {access_token}
Accept: application/json
```

---

## 3. الصلاحيات المطلوبة

| العملية | الصلاحية المطلوبة | الدور الافتراضي |
|---|---|---|
| البحث عن وحدات | `sales.projects.view` | sales / sales_leader / admin |
| قائمة الفلاتر | `sales.projects.view` | sales / sales_leader / admin |
| إنشاء تنبيه | `sales.search_alerts.view` | sales / sales_leader / admin |
| قائمة التنبيهات | `sales.search_alerts.view` | sales / sales_leader / admin |
| تفاصيل تنبيه | `sales.search_alerts.view` + ملكية | sales (own) / admin / manage |
| تحديث تنبيه | `sales.search_alerts.view` + ملكية | sales (own) / admin / manage |
| حذف تنبيه | `sales.search_alerts.view` + ملكية | sales (own) / admin / manage |
| الوصول لكل التنبيهات | `sales.search_alerts.manage` | sales_leader / admin |
| الإشعارات | مصادقة فقط | أي مستخدم مسجل |

### ملاحظة على الملكية

الموظف العادي (role: `sales`) يرى تنبيهاته هو فقط.
من يملك `sales.search_alerts.manage` أو دور `admin` يرى جميع التنبيهات.
محاولة الوصول لتنبيه شخص آخر تُرجع `403 Forbidden`.

---

## 4. قائمة نقاط الـ API الكاملة

### أ. البحث عن الوحدات

#### GET /api/sales/units/search

يبحث عن وحدات متاحة.

**Query Parameters:**

| الحقل | النوع | الوصف |
|---|---|---|
| `city_id` | integer | فلتر بمعرف المدينة |
| `district_id` | integer | فلتر بمعرف الحي |
| `city` | string | اسم المدينة (نصي) |
| `district` | string | اسم الحي (نصي) |
| `status` | string | available \| reserved \| sold \| pending |
| `unit_type` | string | نوع الوحدة |
| `floor` | integer | الدور |
| `min_price` | numeric | السعر الأدنى |
| `max_price` | numeric | السعر الأقصى |
| `min_area` | numeric | المساحة الأدنى |
| `max_area` | numeric | المساحة الأقصى |
| `min_bedrooms` | integer | عدد غرف النوم (الحد الأدنى) |
| `max_bedrooms` | integer | عدد غرف النوم (الحد الأقصى) |
| `project_id` | integer | معرف المشروع |
| `q` | string | بحث نصي حر (**استخدم `q` هنا، ليس `query_text`**) |
| `sort_by` | string | price \| area \| bedrooms \| created_at |
| `sort_dir` | string | asc \| desc |
| `page` | integer | رقم الصفحة |
| `per_page` | integer | عدد العناصر (max 100) |

**مثال الطلب:**
```
GET /api/sales/units/search?city_id=1&unit_type=villa&min_price=500000&max_price=1000000&status=available
```

**مثال الاستجابة (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 88,
      "unit_number": "A-101",
      "unit_type": "villa",
      "price": 750000,
      "area": 250,
      "bedrooms": 4,
      "floor": "2",
      "status": "available"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

---

#### GET /api/sales/units/filters

يُرجع قيم الفلاتر المتاحة (مدن، أحياء، أنواع الوحدات، إلخ).

**مثال الاستجابة (200 OK):**
```json
{
  "success": true,
  "data": {
    "cities": [{ "id": 1, "name": "الرياض" }],
    "unit_types": ["villa", "apartment", "duplex"],
    "price_range": { "min": 100000, "max": 5000000 }
  }
}
```

---

### ب. تنبيهات البحث

#### POST /api/sales/units/search-alerts

ينشئ تنبيه بحث جديد.

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Body Fields:**

| الحقل | النوع | إلزامي | الوصف |
|---|---|---|---|
| `client_mobile` | string (max 50) | **نعم** | رقم جوال العميل |
| `client_name` | string (max 255) | لا | اسم العميل |
| `client_email` | email (max 255) | لا | بريد العميل |
| `client_sms_opt_in` | boolean | لا | موافقة على SMS |
| `client_sms_opted_in_at` | datetime | لا | وقت الموافقة (تلقائي إن لم يُرسل) |
| `client_sms_locale` | string (max 20) | لا | لغة SMS (ar-SA, en) |
| `city_id` | integer | لا | معرف المدينة |
| `district_id` | integer | لا | معرف الحي |
| `project_id` | integer | لا | معرف المشروع (contracts.id) |
| `unit_type` | string (max 255) | لا | نوع الوحدة |
| `floor` | string (max 50) | لا | الدور |
| `min_price` | numeric (≥0) | لا | السعر الأدنى |
| `max_price` | numeric (≥0, ≥min_price) | لا | السعر الأقصى |
| `min_area` | numeric (≥0) | لا | المساحة الأدنى |
| `max_area` | numeric (≥0, ≥min_area) | لا | المساحة الأقصى |
| `min_bedrooms` | integer (0-255) | لا | غرف (أدنى) |
| `max_bedrooms` | integer (0-255, ≥min_bedrooms) | لا | غرف (أقصى) |
| `query_text` | string (max 255) | لا | نص البحث (**ليس `q`**) |
| `status` | enum | لا | active (افتراضي) |
| `expires_at` | date (after:now) | لا | انتهاء (+30 يوم تلقائياً) |

**حقول محظورة (تُرجع 422 إن أُرسلت):**
- `page`
- `per_page`
- `sort_by`
- `sort_dir`

**مثال Body:**
```json
{
  "client_name": "أحمد الحربي",
  "client_mobile": "+966501234567",
  "client_email": "ahmed@example.com",
  "client_sms_opt_in": true,
  "client_sms_locale": "ar-SA",
  "city_id": 1,
  "project_id": 5,
  "unit_type": "villa",
  "floor": "2",
  "min_price": 500000,
  "max_price": 900000,
  "min_bedrooms": 3,
  "query_text": "A-101"
}
```

**مثال الاستجابة (201 Created):**
```json
{
  "success": true,
  "message": "Sales unit search alert created successfully",
  "data": {
    "id": 42,
    "sales_staff_id": 7,
    "client": {
      "name": "أحمد الحربي",
      "mobile": "+966501234567",
      "email": "ahmed@example.com",
      "sms_opt_in": true,
      "sms_opted_in_at": "2026-05-05T10:00:00.000000Z",
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
}
```

---

#### GET /api/sales/units/search-alerts

قائمة مُقسَّمة بالصفحات.

**Query Parameters:**

| الحقل | النوع | الوصف |
|---|---|---|
| `status` | string | فلتر: active \| paused \| matched \| cancelled |
| `per_page` | integer | 1-100, افتراضي 15 |

**مثال الاستجابة (200 OK):**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

---

#### GET /api/sales/units/search-alerts/{alert}

تفاصيل تنبيه واحد.

**Path Parameter:** `alert` — معرف التنبيه (integer)

**الاستجابة:** كائن تنبيه كامل مع `last_matched_unit` و `deliveries[]`

---

#### PATCH /api/sales/units/search-alerts/{alert}

تحديث جزئي للتنبيه.

**Body Fields:** نفس حقول الإنشاء + قاعدة `sometimes` (كل حقل اختياري)

**التحقق الإضافي في التحديث:**
عند إرسال `max_price` بدون `min_price`، يُقارن `max_price` مع القيمة المحفوظة في التنبيه.
هذا يمنع إنشاء نطاق غير منطقي عبر التحديثات الجزئية.

---

#### DELETE /api/sales/units/search-alerts/{alert}

يلغي التنبيه (soft delete).

- يُحدَّث `status` → `cancelled`
- يُسجَّل `deleted_at` (soft delete)
- لا يُحذف من قاعدة البيانات بشكل كامل

**الاستجابة (200 OK):**
```json
{
  "success": true,
  "message": "Sales unit search alert cancelled successfully"
}
```

---

### ج. الإشعارات

#### GET /api/notifications

يُرجع الإشعارات الخاصة للمستخدم المسجل.

البديل: `GET /api/user/notifications/private`

**نموذج إشعار تطابق التنبيه:**
```json
{
  "id": 15,
  "user_id": 7,
  "message": "Matching unit found for أحمد الحربي: مشروع النخيل / A-101",
  "event_type": "unit_search_alert_matched",
  "context": {
    "alert_id": 42,
    "contract_unit_id": 88,
    "contract_id": 5,
    "client_name": "أحمد الحربي",
    "client_mobile": "+966501234567"
  },
  "status": "pending"
}
```

#### PATCH /api/notifications/{id}/read
#### POST /api/notifications/mark-all-read

---

## 5. الاستجابات الشائعة للأخطاء

### 401 Unauthorized
```json
{ "message": "Unauthenticated." }
```
**السبب:** التوكن مفقود أو منتهي الصلاحية
**التصرف:** أعد توجيه لصفحة تسجيل الدخول

---

### 403 Forbidden
```json
{ "message": "You are not authorized to access this alert." }
```
**السبب:** محاولة الوصول لتنبيه موظف آخر
**التصرف:** اعرض "لا تملك صلاحية للوصول لهذا التنبيه"

---

### 404 Not Found
```json
{ "message": "No query results for model [App\\Models\\SalesUnitSearchAlert]." }
```
**السبب:** التنبيه غير موجود أو محذوف
**التصرف:** اعرض "التنبيه غير موجود أو تم حذفه"

---

### 422 Unprocessable Entity
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "client_mobile": ["The client mobile field is required."],
    "max_price": ["The max price field must be greater than or equal to min price."],
    "sort_by": ["The sort by field is prohibited."]
  }
}
```
**السبب:** أخطاء في التحقق من الصحة
**التصرف:** اعرض الأخطاء بجانب الحقول المعنية

---

## 6. ملاحظات تكامل الواجهة الأمامية

### الفرق بين `q` و `query_text`

| السياق | الحقل الصحيح |
|---|---|
| البحث في GET /api/sales/units/search | `q` |
| حفظ معايير البحث في POST/PATCH /search-alerts | `query_text` |

> **ملاحظة:** `UnitSearchCriteria` في الخلفية تُحول تلقائياً: إذا أُرسل `query_text` لـ GET search يُعادل `q`. لكن في POST/PATCH يجب إرسال `query_text` صراحةً.

### الملء التلقائي للـ Modal عند إنشاء التنبيه

```javascript
// المعايير الحالية من صفحة البحث
const searchParams = useSearchParams();

// الملء التلقائي
const prefillData = {
  city_id: searchParams.city_id ?? null,
  district_id: searchParams.district_id ?? null,
  project_id: searchParams.project_id ?? null,
  unit_type: searchParams.unit_type ?? null,
  min_price: searchParams.min_price ?? null,
  max_price: searchParams.max_price ?? null,
  min_bedrooms: searchParams.min_bedrooms ?? null,
  max_bedrooms: searchParams.max_bedrooms ?? null,
  query_text: searchParams.q ?? null,  // ← تحويل q → query_text
};
```

---

## 7. إدارة الـ State المقترحة

```typescript
interface SalesUnitSearchAlert {
  id: number;
  sales_staff_id: number;
  client: {
    name: string | null;
    mobile: string;
    email: string | null;
    sms_opt_in: boolean;
    sms_opted_in_at: string | null;
    sms_locale: string | null;
  };
  criteria: {
    city_id: number | null;
    district_id: number | null;
    project_id: number | null;
    unit_type: string | null;
    floor: string | null;
    min_price: number | null;
    max_price: number | null;
    min_area: number | null;
    max_area: number | null;
    min_bedrooms: number | null;
    max_bedrooms: number | null;
    query_text: string | null;
  };
  status: 'active' | 'paused' | 'matched' | 'cancelled';
  last_notification: {
    last_notified_at: string | null;
    last_system_notified_at: string | null;
    last_sms_attempted_at: string | null;
    last_sms_sent_at: string | null;
    last_sms_error: string | null;
    last_twilio_sid: string | null;
    last_delivery_error: string | null;
  };
  last_matched_unit: {
    id: number;
    unit_number: string | null;
    unit_type: string | null;
    price: number | null;
  } | null;
  expires_at: string | null;
  deliveries: SalesUnitSearchAlertDelivery[];
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

interface SalesUnitSearchAlertDelivery {
  id: number;
  contract_unit_id: number;
  user_notification_id: number | null;
  client_mobile: string;
  delivery_channel: 'system_notification' | 'sms';
  status: 'pending' | 'sent' | 'skipped' | 'failed';
  twilio_sid: string | null;
  skip_reason: string | null;
  error_message: string | null;
  sent_at: string | null;
}
```

---

## 8. التسميات العربية المقترحة

```javascript
const arabicLabels = {
  // حالات التنبيه
  status: {
    active: 'نشط',
    paused: 'متوقف مؤقتاً',
    matched: 'تم التطابق ✓',
    cancelled: 'ملغى',
  },
  // قنوات التسليم
  delivery_channel: {
    system_notification: 'إشعار داخلي',
    sms: 'رسالة SMS',
  },
  // حالات التسليم
  delivery_status: {
    pending: 'قيد المعالجة',
    sent: 'تم الإرسال',
    skipped: 'تم التخطي',
    failed: 'فشل',
  },
  // أسباب التخطي
  skip_reason: {
    sms_disabled: 'SMS غير مفعّل',
    twilio_not_configured: 'خدمة SMS غير متوفرة',
    sms_opt_in_missing: 'العميل لم يوافق على SMS',
    invalid_phone: 'رقم الهاتف غير صالح',
    unit_not_available: 'الوحدة غير متاحة',
    alert_deleted: 'التنبيه محذوف',
    alert_cancelled: 'التنبيه ملغى',
    alert_expired: 'التنبيه منتهي الصلاحية',
    sms_throttled: 'تم إرسال SMS مؤخراً',
  },
};
```

---

## 9. رسائل المستخدم المقترحة

```javascript
const userMessages = {
  alertCreated: 'تم إنشاء التنبيه. سيصلك إشعار داخلي عند توفر وحدة مطابقة.',
  alertUpdated: 'تم تحديث التنبيه بنجاح.',
  alertDeleted: 'تم إلغاء التنبيه.',
  alertMatched: 'وجدنا وحدة تطابق معايير بحثك!',
  noResultsFound: 'لم يتم العثور على وحدات مطابقة. يمكنك إنشاء تنبيه ليصلك إشعار عند توفر وحدة.',
  unauthorizedAccess: 'لا تملك صلاحية الوصول لهذا التنبيه.',
  alertNotFound: 'التنبيه غير موجود أو تم حذفه.',
  smsOptInHint: 'عند الموافقة على SMS، سيتلقى العميل رسالة نصية عند توفر وحدة مناسبة (إذا كانت الخدمة مفعلة).',
  smsNotGuaranteed: 'إرسال SMS ثانوي واختياري. الإشعار الداخلي هو التأكيد الرسمي.',
};
```

---

## 10. تحذيرات SMS

> ⚠️ **القواعد الذهبية للتعامل مع SMS:**

1. SMS **ليس** مصدر الحقيقة. `last_system_notified_at` هو المصدر.
2. SMS قد لا يُرسل لأسباب متعددة — اعرض `skip_reason` بشكل وصفي.
3. **لا** تعرض محتوى `last_sms_error` أو `last_delivery_error` أو `error_message` الخام للمستخدم.
4. **لا** تطلب من الواجهة الأمامية تكوين Twilio أو إرسال credentials.
5. سياسة الإرسال السعودية مُطبَّقة في الخلفية:
   - لا روابط URL في الرسائل
   - لا أرقام هاتف في جسم الرسالة
   - نافذة إرسال محددة (اختياري)
   - opt-in مطلوب

---

## 11. Real-time والـ Polling

### WebSocket (إذا متاح)
استمع لأحداث `UserNotificationEvent` على القناة الخاصة للمستخدم.
تحقق من `event_type === 'unit_search_alert_matched'`.

### Polling كبديل
```javascript
let pollingInterval;

function startNotificationPolling(intervalMs = 30000) {
  pollingInterval = setInterval(async () => {
    try {
      const res = await api.get('/notifications');
      const unread = res.data.data?.filter(n => n.status === 'pending') ?? [];
      updateBadgeCount(unread.length);
      
      const alertMatches = unread.filter(n => n.event_type === 'unit_search_alert_matched');
      if (alertMatches.length > 0) {
        showMatchNotification(alertMatches[0]);
      }
    } catch (e) {
      // فشل polling لا يجب أن يؤثر على الواجهة
    }
  }, intervalMs);
}

function stopPolling() {
  clearInterval(pollingInterval);
}
```

---

## 12. قائمة اختبار التكامل

- [ ] البحث عن وحدات يُرجع بيانات مقسمة بالصفحات
- [ ] نتائج فارغة تُظهر زر "إنشاء تنبيه"
- [ ] إنشاء التنبيه يُرجع 201 مع data.id
- [ ] `alert_id` يُحفظ ويُستخدم في الطلبات اللاحقة
- [ ] `query_text` (وليس `q`) يُرسل في POST التنبيه
- [ ] `client_mobile` إلزامي — بدونه يُرجع 422
- [ ] `max_price < min_price` يُرجع 422 على errors.max_price
- [ ] `sort_by` في POST/PATCH يُرجع 422 (prohibited)
- [ ] قائمة التنبيهات تتضمن meta.total
- [ ] الموظف لا يرى تنبيهات زميله (403)
- [ ] الـ Modal يُملأ تلقائياً بمعايير البحث الحالية
- [ ] إشعار `unit_search_alert_matched` يُعرض في الجرس
- [ ] الضغط على الإشعار ينقل لصفحة التنبيه المطابق
