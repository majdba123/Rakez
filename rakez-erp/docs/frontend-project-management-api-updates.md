# Project Management API Updates

## 1. الملخص | Summary

تم إضافة 3 تغييرات رئيسية في Backend Laravel API الخاصة بإدارة المشاريع، مع اعتبار أن كيان المشروع في النظام هو `Contract`:

1. أرشفة المشروع أصبحت من خطوتين:
   - `completed -> pending_archive -> archived`
2. إضافة تاريخ انتهاء لرقم المعلن في `second_party_data`.
3. إضافة رفع صور اللوحات/السنايبورد مع إعادة استخدام جدول `project_media`.

هذه الوثيقة هي عقد التكامل النهائي للواجهة الأمامية.

## 2. دورة حالة الأرشفة | Archive Lifecycle

الحالة الجديدة للمشروع:

- `completed`
- `pending_archive`
- `archived`

التدفق:

1. عند طلب الأرشفة لأول مرة:
   - يتم تحويل الحالة إلى `pending_archive`
   - يتم إرجاع الرسالة التالية حرفيا:
   - `يرجى التأكد من إزالة لوحات المشروع قبل تأكيد الأرشفة.`
2. بعد تأكيد إزالة اللوحات:
   - يتم تحويل الحالة إلى `archived`
   - يتم تسجيل بيانات التدقيق الخاصة بطلب/تأكيد الأرشفة

## 3. الصلاحيات | Auth / Permission Notes

- جميع مسارات الأرشفة تتطلب:
  - `auth:sanctum`
  - دور `project_management` أو `admin`
  - صلاحية فعالة `projects.archive`
- رفع صور اللوحات يتطلب:
  - `auth:sanctum`
  - دور `project_management` أو `admin`
  - صلاحية `projects.media.upload`
- بيانات الطرف الثاني ما زالت تستخدم مساراتها الحالية وصلاحية:
  - `second_party.edit`

## 4. المسارات الجديدة | New Endpoints

### 4.1 طلب أرشفة مشروع

- Method: `POST`
- URL: `/api/contracts/{contract_id}/archive-request`
- Permission: `projects.archive`

#### Request Body

```json
{
  "archive_note": "Optional note"
}
```

#### Success Response

```json
{
  "success": true,
  "message": "يرجى التأكد من إزالة لوحات المشروع قبل تأكيد الأرشفة.",
  "data": {
    "id": 15,
    "status": "pending_archive",
    "can_confirm_archive": true,
    "archive_requested_at": "2026-05-10T07:15:00+03:00",
    "archive_note": "Optional note"
  }
}
```

#### Error Cases

- `409` إذا كان المشروع `archived`
- `422` إذا كانت الحالة الحالية غير مناسبة للأرشفة
- `403` إذا لم توجد الصلاحية

### 4.2 تأكيد الأرشفة بعد إزالة اللوحات

- Method: `POST`
- URL: `/api/contracts/{contract_id}/archive-confirm`
- Permission: `projects.archive`

#### Request Body

```json
{
  "boards_removed_confirmed": true,
  "archive_note": "Optional final note"
}
```

#### Success Response

```json
{
  "success": true,
  "message": "تم تأكيد إزالة لوحات المشروع وأرشفة المشروع بنجاح.",
  "data": {
    "id": 15,
    "status": "archived",
    "can_confirm_archive": false,
    "boards_removed_confirmed_at": "2026-05-10T07:20:00+03:00",
    "archived_at": "2026-05-10T07:20:00+03:00"
  }
}
```

#### Error Cases

- `422` إذا لم تكن الحالة الحالية `pending_archive`
- `422` إذا لم يتم إرسال `boards_removed_confirmed=true`
- `403` إذا لم توجد الصلاحية

### 4.3 قائمة المشاريع المؤرشفة

- Method: `GET`
- URL: `/api/contracts/archived`
- Permission: `projects.archive`

#### Query Params

- `status` اختياري:
  - `pending_archive`
  - `archived`
- `project_name`
- `city_id`
- `district_id`
- `user_id`
- `per_page`

#### Behavior

- بدون `status`:
  - يتم إرجاع `pending_archive` و `archived`
- مع `status=pending_archive`:
  - يتم إرجاع المشاريع بانتظار التأكيد فقط
- مع `status=archived`:
  - يتم إرجاع المشاريع المؤرشفة نهائيا فقط

#### Example Response

```json
{
  "success": true,
  "message": "تم جلب المشاريع المؤرشفة بنجاح",
  "data": [
    {
      "id": 15,
      "project_name": "Demo Project",
      "status": "pending_archive",
      "can_confirm_archive": true,
      "archive_requested_at": "2026-05-10T07:15:00+03:00",
      "archive_requested_by": {
        "id": 7,
        "name": "PM Manager",
        "type": "project_management"
      }
    }
  ],
  "meta": {
    "total": 1,
    "count": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

## 5. تحديث بيانات الطرف الثاني | Advertiser Expiry Support

المسارات الحالية لم تتغير:

- إضافة بيانات الطرف الثاني:
  - `POST /api/second-party-data/store/{contract_id}`
- تحديث بيانات الطرف الثاني:
  - `PUT /api/second-party-data/update/{contract_id}`
- عرض بيانات الطرف الثاني:
  - `GET /api/second-party-data/show/{contract_id}`

### الحقل الجديد

- `advertiser_section_expiry_date`
- Type: `date`
- Example: `2026-06-25`

### مثال Request

```json
{
  "real_estate_papers_url": "https://example.com/deed.pdf",
  "plans_equipment_docs_url": "https://example.com/plans.pdf",
  "project_logo_url": "https://example.com/logo.png",
  "prices_units_url": "https://example.com/prices.pdf",
  "marketing_license_url": "https://example.com/license.pdf",
  "advertiser_section_url": "123456789",
  "advertiser_section_expiry_date": "2026-06-25"
}
```

## 6. حقول رقم المعلن الجديدة | New Advertiser Fields

هذه الحقول أصبحت متاحة في:

- `ContractResource` (`/api/contracts/show/{id}`)
- `ContractIndexResource` (`/api/contracts/index`, `/api/contracts/admin-index`, `/api/contracts/archived`)
- `SecondPartyDataResource`
- `SalesProjectResource`
- `SalesProjectDetailResource`
- `MarketingProjectResource`
- `Marketing` project detail payload

### الحقول

- `advertiser_number`
- `advertiser_number_source`
- `advertiser_number_expires_at`
- `advertiser_number_remaining_days`
- `advertiser_number_expiry_status`

### الأنواع

- `advertiser_number`: `string|null`
- `advertiser_number_source`: `string|null`
- `advertiser_number_expires_at`: `YYYY-MM-DD|null`
- `advertiser_number_remaining_days`: `integer|null`
- `advertiser_number_expiry_status`: one of:
  - `missing`
  - `valid`
  - `expiring_soon`
  - `expired`

### قاعدة الحالة | Status Logic

- `missing`
  - إذا كان رقم المعلن مفقودا
  - أو تاريخ الانتهاء مفقودا
- `expired`
  - إذا كان التاريخ قبل اليوم
- `expiring_soon`
  - إذا كان التاريخ اليوم أو خلال 14 يوما
- `valid`
  - إذا كان التاريخ بعد 14 يوما

### توصيات الواجهة

- `missing`: عرض تنبيه أحمر أو أصفر بحسب شاشة الاستخدام
- `expired`: منع أو تحذير قوي حسب البزنس
- `expiring_soon`: تحذير أصفر
- `valid`: بدون تحذير

## 7. التوافق الخلفي | Backward Compatibility

### Canonical Source

المصدر الأساسي الجديد لرقم المعلن هو:

- `second_party_data.advertiser_section_url`

### Fallback

لأغراض التوافق الخلفي خاصة في Marketing APIs:

- إذا كان `second_party_data.advertiser_section_url` فارغا
- يتم استخدام `contract_infos.agency_number` كـ fallback فقط

### ملاحظات مهمة

- لا تقم الواجهة الأمامية بإرسال `agency_number` كمصدر أساسي جديد
- استخدم `advertiser_section_url` و `advertiser_section_expiry_date`
- بعض استجابات التسويق ما زالت تحافظ على الحقول القديمة:
  - `advertiser_number`
  - `advertiser_number_value`
  - `advertiser_number_status`
- وتمت إضافة الحقول الجديدة بجانبها:
  - `advertiser_number_source`
  - `advertiser_number_expires_at`
  - `advertiser_number_remaining_days`
  - `advertiser_number_expiry_status`

## 8. رفع صور اللوحات | Board / Signboard Upload

- Method: `POST`
- URL: `/api/contracts/{contract_id}/board-images`
- Permission: `projects.media.upload`
- Content-Type: `multipart/form-data`

### Form Data

- `images[]`: required array of files
- `kind`: optional
  - `board_image`
  - `signboard_image`
- `note`: optional string, max 1000

### Allowed Files

- `jpg`
- `jpeg`
- `png`
- `webp`
- max size: `5120 KB`

### Storage

- disk: `public`
- path pattern:
  - `projects/{contract_id}/boards`

### Persistence

يتم إنشاء صفوف داخل `project_media` باستخدام:

- `department = boards`
- `type = image`
- `kind = board_image | signboard_image`
- `note = ...`
- `url = frontend-usable public storage URL`

### Example Response

```json
{
  "success": true,
  "message": "تم رفع صور اللوحات بنجاح",
  "data": [
    {
      "id": 44,
      "contract_id": 15,
      "type": "image",
      "department": "boards",
      "kind": "signboard_image",
      "note": "North entrance boards",
      "url": "https://api.example.com/storage/projects/15/boards/example.jpg",
      "created_at": "2026-05-10T07:30:00+03:00"
    }
  ]
}
```

## 9. حقول الأرشفة في الموارد | Archive Fields Exposed to Frontend

هذه الحقول أصبحت موجودة في استجابات العقد والقوائم المؤرشفة:

- `archive_requested_at`
- `archive_requested_by`
- `archive_confirmed_at`
- `archive_confirmed_by`
- `archived_at`
- `archived_by`
- `archive_note`
- `boards_removed_confirmed_at`
- `boards_removed_confirmed_by`
- `can_confirm_archive`

## 10. ملاحظات الاختبار | Backend Testing Notes

تمت إضافة/تحديث اختبارات تغطي:

- طلب الأرشفة
- تأكيد الأرشفة
- قائمة المشاريع المؤرشفة
- تواريخ انتهاء رقم المعلن
- fallback التسويق والمبيعات
- رفع صور اللوحات

## 11. أسماء المسارات | Route Summary

- `POST /api/contracts/{contract_id}/archive-request`
- `POST /api/contracts/{contract_id}/archive-confirm`
- `GET /api/contracts/archived`
- `POST /api/contracts/{contract_id}/board-images`
- `POST /api/second-party-data/store/{contract_id}`
- `PUT /api/second-party-data/update/{contract_id}`
- `GET /api/second-party-data/show/{contract_id}`

## 12. المطلوب من الواجهة الأمامية | Frontend Action Items

1. إضافة شاشة/زر "طلب أرشفة" للمستخدم المخول.
2. عند نجاح طلب الأرشفة:
   - اعرض الرسالة:
   - `يرجى التأكد من إزالة لوحات المشروع قبل تأكيد الأرشفة.`
3. أضف شاشة قائمة المشاريع المؤرشفة باستخدام:
   - `/api/contracts/archived`
4. أظهر زر "تأكيد الأرشفة" فقط عندما:
   - `status = pending_archive`
   - أو `can_confirm_archive = true`
5. أضف حقول رقم المعلن الجديدة في:
   - تفاصيل المشروع
   - القوائم
   - شاشات التحذير
6. استخدم `multipart/form-data` لرفع صور اللوحات.
7. تعامل مع `advertiser_number_expiry_status` كحقل التحذير الأساسي.
