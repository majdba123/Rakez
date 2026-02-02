# مرجع رموز الأخطاء - نظام العمولات والودائع

## نظرة عامة

هذا المستند يحتوي على جميع رموز الأخطاء المستخدمة في نظام إدارة العمولات والودائع مع شرح تفصيلي لكل رمز وكيفية التعامل معه.

---

## رموز الأخطاء العامة

### HTTP Status Codes - رموز حالة HTTP

| الرمز | الاسم | الوصف بالعربية |
|------|------|----------------|
| 200 | OK | نجاح العملية |
| 201 | Created | تم الإنشاء بنجاح |
| 202 | Accepted | تم قبول الطلب وسيتم معالجته |
| 204 | No Content | نجاح بدون محتوى |
| 400 | Bad Request | طلب غير صحيح |
| 401 | Unauthorized | غير مصرح - يجب تسجيل الدخول |
| 403 | Forbidden | ممنوع - صلاحيات غير كافية |
| 404 | Not Found | غير موجود |
| 409 | Conflict | تعارض في البيانات |
| 422 | Unprocessable Entity | خطأ في التحقق من البيانات |
| 500 | Internal Server Error | خطأ في الخادم |

---

## رموز أخطاء العمولات (COMM_XXX)

### COMM_001: عمولة موجودة بالفعل
```json
{
  "success": false,
  "message": "عمولة موجودة بالفعل لهذه الوحدة",
  "error_code": "COMM_001"
}
```

**الحالة:** 409 Conflict

**السبب:** محاولة إنشاء عمولة لوحدة لديها عمولة موجودة مسبقاً

**الحل:**
- تحقق من وجود عمولة للوحدة قبل الإنشاء
- استخدم endpoint التحديث بدلاً من الإنشاء
- احذف العمولة القديمة إذا لزم الأمر

**مثال على الطلب الخاطئ:**
```javascript
// محاولة إنشاء عمولة ثانية لنفس الوحدة
POST /api/sales/commissions
{
  "contract_unit_id": 123, // لديها عمولة بالفعل
  "sales_reservation_id": 456,
  "final_selling_price": 1000000,
  "commission_percentage": 2.5,
  "commission_source": "owner"
}
```

---

### COMM_002: نسبة عمولة غير صحيحة
```json
{
  "success": false,
  "message": "نسبة العمولة غير صحيحة",
  "error_code": "COMM_002"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** نسبة العمولة خارج النطاق المسموح (0-100)

**الحل:**
- تأكد أن النسبة بين 0 و 100
- استخدم أرقام عشرية للنسب الدقيقة (مثل 2.5)

**مثال على القيم الصحيحة:**
```javascript
"commission_percentage": 2.5  // ✓ صحيح
"commission_percentage": 0    // ✓ صحيح (صفر)
"commission_percentage": 100  // ✓ صحيح (100%)
"commission_percentage": -5   // ✗ خطأ (سالب)
"commission_percentage": 150  // ✗ خطأ (أكبر من 100)
```

---

### COMM_003: مجموع التوزيع لا يساوي 100%
```json
{
  "success": false,
  "message": "مجموع نسب التوزيع يجب أن يساوي 100% (المجموع الحالي: 95%)",
  "error_code": "COMM_003"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** مجموع نسب توزيع العمولة لا يساوي 100%

**الحل:**
- احسب مجموع النسب قبل الإرسال
- تأكد أن المجموع = 100% بالضبط
- استخدم دالة للتحقق من المجموع

**مثال على التوزيع الصحيح:**
```javascript
{
  "distributions": [
    { "type": "lead_generation", "percentage": 30 },
    { "type": "persuasion", "percentage": 25 },
    { "type": "closing", "percentage": 30 },
    { "type": "management", "percentage": 15 }
  ]
  // المجموع = 100% ✓
}
```

**مثال على التوزيع الخاطئ:**
```javascript
{
  "distributions": [
    { "type": "lead_generation", "percentage": 30 },
    { "type": "persuasion", "percentage": 25 },
    { "type": "closing", "percentage": 30 }
  ]
  // المجموع = 85% ✗
}
```

---

### COMM_004: لا يمكن تعديل عمولة معتمدة
```json
{
  "success": false,
  "message": "لا يمكن تعديل عمولة تم اعتمادها",
  "error_code": "COMM_004"
}
```

**الحالة:** 403 Forbidden

**السبب:** محاولة تعديل عمولة في حالة "approved" أو "paid"

**الحل:**
- تحقق من حالة العمولة قبل التعديل
- يمكن تعديل العمولات في حالة "pending" فقط
- اتصل بالمدير لإلغاء الاعتماد إذا لزم الأمر

**حالات العمولة:**
- `pending` - يمكن التعديل ✓
- `approved` - لا يمكن التعديل ✗
- `paid` - لا يمكن التعديل ✗

---

### COMM_005: توزيع مكرر لنفس الموظف
```json
{
  "success": false,
  "message": "لا يمكن توزيع العمولة على نفس الموظف أكثر من مرة",
  "error_code": "COMM_005"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** محاولة إضافة أكثر من توزيع لنفس الموظف في نفس العمولة

**الحل:**
- تحقق من عدم تكرار `user_id` في التوزيعات
- استخدم التحديث بدلاً من الإضافة
- اجمع النسب إذا كان الموظف يستحق أكثر من دور

---

### COMM_006: نوع توزيع غير صحيح
```json
{
  "success": false,
  "message": "نوع التوزيع غير صحيح: invalid_type",
  "error_code": "COMM_006"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** استخدام نوع توزيع غير مدعوم

**الأنواع المدعومة:**
```javascript
const validTypes = [
  'lead_generation',      // توليد العملاء المحتملين
  'persuasion',          // الإقناع
  'closing',             // الإغلاق
  'team_leader',         // قائد الفريق
  'sales_manager',       // مدير المبيعات
  'project_manager',     // مدير المشروع
  'external_marketer',   // مسوق خارجي
  'other'                // أخرى
];
```

---

### COMM_007: خطأ في حساب العمولة
```json
{
  "success": false,
  "message": "خطأ في حساب العمولة: تفاصيل الخطأ",
  "error_code": "COMM_007"
}
```

**الحالة:** 500 Internal Server Error

**السبب:** خطأ في العمليات الحسابية

**الحل:**
- تحقق من صحة البيانات المدخلة
- تأكد من أن الأرقام ليست null
- أبلغ فريق الدعم إذا استمر الخطأ

---

### COMM_008: المصاريف تتجاوز مبلغ العمولة
```json
{
  "success": false,
  "message": "إجمالي المصاريف لا يمكن أن يتجاوز مبلغ العمولة",
  "error_code": "COMM_008"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** مجموع (مصاريف التسويق + رسوم البنك) > مبلغ العمولة الإجمالي

**الحل:**
- احسب المجموع قبل الإرسال
- تأكد أن: `marketing_expenses + bank_fees <= total_amount`

**مثال:**
```javascript
// إذا كان total_amount = 25,000 ريال
{
  "marketing_expenses": 20000,  // ✗ خطأ
  "bank_fees": 10000            // المجموع = 30,000 > 25,000
}

{
  "marketing_expenses": 10000,  // ✓ صحيح
  "bank_fees": 5000             // المجموع = 15,000 < 25,000
}
```

---

### COMM_009: العمولة غير موجودة
```json
{
  "success": false,
  "message": "العمولة غير موجودة",
  "error_code": "COMM_009"
}
```

**الحالة:** 404 Not Found

**السبب:** محاولة الوصول إلى عمولة غير موجودة

---

### COMM_010: التوزيع غير موجود
```json
{
  "success": false,
  "message": "التوزيع غير موجود",
  "error_code": "COMM_010"
}
```

**الحالة:** 404 Not Found

**السبب:** محاولة الوصول إلى توزيع غير موجود

---

### COMM_011: لا يمكن حذف توزيع معتمد
```json
{
  "success": false,
  "message": "لا يمكن حذف توزيع تم اعتماده",
  "error_code": "COMM_011"
}
```

**الحالة:** 403 Forbidden

**السبب:** محاولة حذف توزيع في حالة "approved" أو "paid"

---

### COMM_012: المسوق الخارجي يتطلب حساب بنكي
```json
{
  "success": false,
  "message": "رقم الحساب البنكي مطلوب للمسوق الخارجي",
  "error_code": "COMM_012"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** إضافة مسوق خارجي بدون رقم حساب بنكي

**الحل:**
```javascript
{
  "type": "external_marketer",
  "external_name": "محمد أحمد",
  "bank_account": "SA1234567890",  // مطلوب
  "percentage": 10
}
```

---

### COMM_013: الحد الأدنى لمبلغ العمولة
```json
{
  "success": false,
  "message": "مبلغ العمولة يجب أن يكون 100 ريال على الأقل",
  "error_code": "COMM_013"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** مبلغ العمولة المحسوب أقل من الحد الأدنى

---

## رموز أخطاء الودائع (DEP_XXX)

### DEP_001: وديعة موجودة بالفعل
```json
{
  "success": false,
  "message": "وديعة موجودة بالفعل لهذا الحجز",
  "error_code": "DEP_001"
}
```

**الحالة:** 409 Conflict

**السبب:** محاولة إنشاء وديعة لحجز لديه وديعة موجودة

---

### DEP_002: لا يمكن استرداد وديعة المشتري
```json
{
  "success": false,
  "message": "لا يمكن استرداد وديعة من مصدر المشتري",
  "error_code": "DEP_002"
}
```

**الحالة:** 403 Forbidden

**السبب:** محاولة استرداد وديعة مصدرها "buyer"

**قواعد الاسترداد:**
- `commission_source: "owner"` - قابلة للاسترداد ✓
- `commission_source: "buyer"` - غير قابلة للاسترداد ✗

**مثال:**
```javascript
// وديعة من المالك - يمكن استردادها
{
  "commission_source": "owner",
  "status": "received"
}
// يمكن استدعاء: POST /api/sales/deposits/{id}/refund

// وديعة من المشتري - لا يمكن استردادها
{
  "commission_source": "buyer",
  "status": "received"
}
// لا يمكن استردادها
```

---

### DEP_003: تم استرداد الوديعة بالفعل
```json
{
  "success": false,
  "message": "تم استرداد الوديعة بالفعل",
  "error_code": "DEP_003"
}
```

**الحالة:** 409 Conflict

**السبب:** محاولة استرداد وديعة تم استردادها مسبقاً

---

### DEP_004: طريقة دفع غير صحيحة
```json
{
  "success": false,
  "message": "طريقة الدفع غير صحيحة: invalid_method",
  "error_code": "DEP_004"
}
```

**الحالة:** 422 Unprocessable Entity

**الطرق المدعومة:**
```javascript
const validMethods = [
  'bank_transfer',   // تحويل بنكي
  'cash',           // نقداً
  'bank_financing'  // تمويل بنكي
];
```

---

### DEP_005: لا يمكن تعديل وديعة مؤكدة
```json
{
  "success": false,
  "message": "لا يمكن تعديل وديعة تم تأكيدها",
  "error_code": "DEP_005"
}
```

**الحالة:** 403 Forbidden

**السبب:** محاولة تعديل وديعة في حالة "confirmed" أو "refunded"

**حالات الوديعة:**
- `pending` - يمكن التعديل ✓
- `received` - يمكن التعديل ✓
- `confirmed` - لا يمكن التعديل ✗
- `refunded` - لا يمكن التعديل ✗

---

### DEP_006: مبلغ سالب غير مسموح
```json
{
  "success": false,
  "message": "مبلغ الوديعة يجب أن يكون أكبر من صفر",
  "error_code": "DEP_006"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** إدخال مبلغ سالب أو صفر

---

### DEP_007: انتقال حالة غير صحيح
```json
{
  "success": false,
  "message": "لا يمكن تغيير حالة الوديعة من pending إلى refunded",
  "error_code": "DEP_007"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** محاولة انتقال غير مسموح بين الحالات

**الانتقالات المسموحة:**
```
pending → received → confirmed
pending → received → refunded (owner only)
```

**الانتقالات الممنوعة:**
```
pending → refunded ✗
confirmed → received ✗
refunded → any ✗
```

---

### DEP_008: الوديعة غير موجودة
```json
{
  "success": false,
  "message": "الوديعة غير موجودة",
  "error_code": "DEP_008"
}
```

**الحالة:** 404 Not Found

---

### DEP_009: لا يمكن تأكيد وديعة غير معلقة
```json
{
  "success": false,
  "message": "يمكن تأكيد الودائع المعلقة فقط",
  "error_code": "DEP_009"
}
```

**الحالة:** 422 Unprocessable Entity

---

### DEP_010: لا يمكن استرداد وديعة معلقة
```json
{
  "success": false,
  "message": "لا يمكن استرداد وديعة معلقة. يجب تأكيدها أولاً",
  "error_code": "DEP_010"
}
```

**الحالة:** 422 Unprocessable Entity

**السبب:** محاولة استرداد وديعة في حالة "pending"

**الحل:**
1. أكد استلام الوديعة أولاً: `POST /api/sales/deposits/{id}/confirm-receipt`
2. ثم قم بالاسترداد: `POST /api/sales/deposits/{id}/refund`

---

### DEP_011: تاريخ دفع في المستقبل
```json
{
  "success": false,
  "message": "تاريخ الدفع لا يمكن أن يكون في المستقبل",
  "error_code": "DEP_011"
}
```

**الحالة:** 422 Unprocessable Entity

---

### DEP_012: الحجز لديه وديعة بالفعل
```json
{
  "success": false,
  "message": "يوجد وديعة بالفعل لهذا الحجز",
  "error_code": "DEP_012"
}
```

**الحالة:** 409 Conflict

---

### DEP_013: الوحدة لا تنتمي للمشروع
```json
{
  "success": false,
  "message": "الوحدة المحددة لا تنتمي إلى هذا المشروع",
  "error_code": "DEP_013"
}
```

**الحالة:** 422 Unprocessable Entity

---

### DEP_014: الحجز لا ينتمي للوحدة
```json
{
  "success": false,
  "message": "الحجز المحدد لا ينتمي إلى هذه الوحدة",
  "error_code": "DEP_014"
}
```

**الحالة:** 422 Unprocessable Entity

---

## رموز الأخطاء العامة

### VALIDATION_ERROR: خطأ في التحقق
```json
{
  "success": false,
  "message": "خطأ في التحقق من البيانات",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "field_name": ["رسالة الخطأ"]
  }
}
```

**الحالة:** 422 Unprocessable Entity

---

### UNAUTHORIZED: غير مصرح
```json
{
  "success": false,
  "message": "غير مصرح - يجب تسجيل الدخول",
  "error_code": "UNAUTHORIZED"
}
```

**الحالة:** 401 Unauthorized

**الحل:**
- تأكد من إرسال token في الـ header
- تحقق من صلاحية الـ token
- سجل دخول مرة أخرى إذا انتهت الجلسة

---

### FORBIDDEN: ممنوع
```json
{
  "success": false,
  "message": "ممنوع - صلاحيات غير كافية",
  "error_code": "FORBIDDEN"
}
```

**الحالة:** 403 Forbidden

**الحل:**
- تحقق من صلاحيات المستخدم
- اتصل بالمدير لمنح الصلاحيات المطلوبة

---

### NOT_FOUND: غير موجود
```json
{
  "success": false,
  "message": "غير موجود",
  "error_code": "NOT_FOUND"
}
```

**الحالة:** 404 Not Found

---

### CONFLICT: تعارض
```json
{
  "success": false,
  "message": "تعارض في البيانات",
  "error_code": "CONFLICT"
}
```

**الحالة:** 409 Conflict

---

### SERVER_ERROR: خطأ في الخادم
```json
{
  "success": false,
  "message": "خطأ في الخادم",
  "error_code": "SERVER_ERROR"
}
```

**الحالة:** 500 Internal Server Error

**الحل:**
- أعد المحاولة بعد قليل
- أبلغ فريق الدعم إذا استمر الخطأ
- تحقق من سجلات الأخطاء

---

## أمثلة على معالجة الأخطاء

### مثال 1: معالجة أخطاء التحقق (Validation)

```javascript
try {
  const response = await axios.post('/api/sales/commissions', data);
  console.log('نجح:', response.data);
} catch (error) {
  if (error.response.status === 422) {
    // خطأ في التحقق
    const errors = error.response.data.errors;
    Object.keys(errors).forEach(field => {
      console.error(`${field}: ${errors[field].join(', ')}`);
    });
  }
}
```

### مثال 2: معالجة رموز الأخطاء المخصصة

```javascript
try {
  const response = await axios.post('/api/sales/deposits/{id}/refund');
} catch (error) {
  const errorCode = error.response.data.error_code;
  
  switch(errorCode) {
    case 'DEP_002':
      alert('لا يمكن استرداد وديعة من مصدر المشتري');
      break;
    case 'DEP_003':
      alert('تم استرداد الوديعة بالفعل');
      break;
    default:
      alert(error.response.data.message);
  }
}
```

### مثال 3: معالجة شاملة للأخطاء

```javascript
async function handleApiCall(apiFunction) {
  try {
    const response = await apiFunction();
    return { success: true, data: response.data };
  } catch (error) {
    if (!error.response) {
      // خطأ في الشبكة
      return {
        success: false,
        message: 'خطأ في الاتصال بالخادم',
        code: 'NETWORK_ERROR'
      };
    }
    
    const { status, data } = error.response;
    
    return {
      success: false,
      message: data.message || 'حدث خطأ',
      code: data.error_code,
      status: status,
      errors: data.errors
    };
  }
}

// الاستخدام
const result = await handleApiCall(() => 
  axios.post('/api/sales/commissions', commissionData)
);

if (result.success) {
  console.log('نجح:', result.data);
} else {
  console.error('فشل:', result.message);
  if (result.errors) {
    // عرض أخطاء التحقق
  }
}
```

---

## جدول مرجعي سريع

| الرمز | الرسالة | الحالة | الحل السريع |
|------|---------|--------|-------------|
| COMM_001 | عمولة موجودة | 409 | تحقق قبل الإنشاء |
| COMM_002 | نسبة خاطئة | 422 | 0-100 فقط |
| COMM_003 | مجموع ≠ 100% | 422 | احسب المجموع |
| COMM_004 | معتمدة | 403 | فقط pending |
| COMM_005 | مكرر | 422 | user_id فريد |
| DEP_002 | مشتري | 403 | owner فقط |
| DEP_003 | مسترد | 409 | تحقق من الحالة |
| DEP_007 | انتقال خاطئ | 422 | اتبع المسار |
| DEP_010 | معلقة | 422 | أكد أولاً |

---

## ملاحظات مهمة

1. **جميع الرسائل باللغة العربية** لسهولة الفهم
2. **رموز الأخطاء ثابتة** ولن تتغير
3. **استخدم `error_code`** للمعالجة البرمجية
4. **استخدم `message`** للعرض للمستخدم
5. **تحقق من `status`** لتحديد نوع الخطأ

---

**تاريخ التحديث:** 1 فبراير 2026  
**الإصدار:** 1.0
