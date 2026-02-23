# مرجع سريع للمطورين - التكامل مع Backend

> **📌 ملخص سريع للتغييرات الأساسية التي يجب على Frontend معرفتها**

---

## 🔗 الروابط الجديدة (API Routes)

```
/api/sales/analytics/dashboard              → لوحة التحكم
/api/sales/analytics/sold-units             → الوحدات المباعة
/api/sales/analytics/commissions/monthly-report  → تقرير الرواتب
/api/sales/commissions/*                    → إدارة العمولات
/api/sales/deposits/*                       → إدارة الودائع
```

---

## 📦 هيكل الاستجابة الجديد

### نجاح (Success)
```json
{
  "success": true,
  "message": "تم جلب البيانات بنجاح",
  "data": {...},
  "meta": {
    "pagination": {...}
  }
}
```

### خطأ (Error)
```json
{
  "success": false,
  "message": "عمولة موجودة بالفعل لهذه الوحدة",
  "error_code": "COMM_001",
  "errors": {
    "field_name": ["رسالة الخطأ"]
  }
}
```

---

## ⚠️ رموز الأخطاء الأساسية

| الرمز | المعنى | الإجراء |
|------|--------|---------|
| `COMM_001` | عمولة موجودة بالفعل | عرض رسالة تنبيه |
| `COMM_003` | مجموع التوزيع ≠ 100% | تحقق من المجموع |
| `COMM_004` | لا يمكن تعديل عمولة معتمدة | إخفاء زر التعديل |
| `COMM_012` | المسوق الخارجي يحتاج حساب بنكي | طلب الحساب البنكي |
| `DEP_002` | لا يمكن استرداد وديعة المشتري | إخفاء زر الاسترداد |
| `DEP_011` | تاريخ دفع في المستقبل | التحقق من التاريخ |

[**قائمة كاملة بـ 27 رمز خطأ**](ERROR_CODES_REFERENCE.md)

---

## 🎯 التبويبات الستة

### 1️⃣ لوحة التحكم (Dashboard)
```javascript
GET /api/sales/analytics/dashboard?from=2026-01-01&to=2026-12-31

// الاستجابة
{
  "units_sold": 150,
  "total_received_deposits": 2500000.00,
  "total_refunded_deposits": 150000.00,
  "total_projects_value": 45000000.00,
  "total_sales_value": 43500000.00,
  "total_commissions": 1305000.00,
  "pending_commissions": 450000.00
}
```

### 2️⃣ الإشعارات (Notifications)
- ✅ تُرسل تلقائياً من Backend
- ✅ استخدم نظام الإشعارات الموجود
- ✅ 6 أنواع إشعارات مختلفة

### 3️⃣ الوحدات المباعة (Sold Units)
```javascript
GET /api/sales/analytics/sold-units?from=2026-01-01&per_page=15

// عرض في الجدول
- اسم المشروع
- رقم الوحدة
- نوع الوحدة
- السعر النهائي
- مصدر العمولة
- نسبة العمولة
- الفريق المسؤول
```

### 4️⃣ ملخص العمولة (Commission Summary)
```javascript
GET /api/sales/commissions/{id}/summary

// عرض
- إجمالي قبل الضريبة
- ضريبة القيمة المضافة (15%)
- مصاريف التسويق
- رسوم البنك
- صافي المبلغ القابل للتوزيع

// جدول التوزيع
- نوع العمولة
- اسم الموظف
- رقم الحساب البنكي
- النسبة المخصصة
- المبلغ بالريال
- الحالة
```

### 5️⃣ إدارة الودائع (Deposit Management)

#### 5.1 إدارة الودائع
```javascript
GET /api/sales/deposits?status=pending
POST /api/sales/deposits
POST /api/sales/deposits/{id}/confirm-receipt
```

#### 5.2 المتابعة
```javascript
GET /api/sales/deposits/follow-up
POST /api/sales/deposits/{id}/refund

// شروط الاسترداد
✅ مصدر العمولة = owner فقط
✅ الحالة = received أو confirmed
❌ لا يمكن استرداد وديعة buyer
❌ لا يمكن استرداد وديعة pending
```

### 6️⃣ الرواتب والعمولات (Salaries)
```javascript
GET /api/sales/analytics/commissions/monthly-report?year=2026&month=2

// عرض في الجدول
- اسم الموظف
- الراتب الأساسي
- المسمى الوظيفي
- عدد المشاريع المباعة
- صافي العمولة الشهرية
- الإجمالي (راتب + عمولة)
```

---

## 🔄 سير عمل العمولة (Commission Workflow)

### 1. إنشاء العمولة
```javascript
POST /api/sales/commissions
{
  "contract_unit_id": 1,
  "sales_reservation_id": 100,
  "final_selling_price": 485000.00,
  "commission_percentage": 3.0,
  "commission_source": "owner"
}
```

### 2. توزيع العمولة (يجب أن يساوي 100%)
```javascript
// توليد العملاء (30%)
POST /api/sales/commissions/{id}/distribute/lead-generation

// الإقناع (25%)
POST /api/sales/commissions/{id}/distribute/persuasion

// الإغلاق (30%)
POST /api/sales/commissions/{id}/distribute/closing

// الإدارة (15%)
POST /api/sales/commissions/{id}/distribute/management
```

### 3. اعتماد التوزيعات
```javascript
POST /api/sales/commissions/distributions/{id}/approve
```

### 4. اعتماد العمولة
```javascript
POST /api/sales/commissions/{id}/approve
// يجب اعتماد جميع التوزيعات أولاً!
```

### 5. تحديد كمدفوعة
```javascript
POST /api/sales/commissions/{id}/mark-paid
// يجب اعتماد العمولة أولاً!
```

---

## ✅ قواعد التحقق الأساسية

### العمولات
```javascript
// 1. المجموع يجب أن يساوي 100%
const total = distributions.reduce((sum, d) => sum + d.percentage, 0);
if (Math.abs(total - 100) > 0.01) {
  throw new Error('مجموع النسب يجب أن يساوي 100%');
}

// 2. لا تكرار للموظف
const userIds = distributions.map(d => d.user_id).filter(Boolean);
if (new Set(userIds).size !== userIds.length) {
  throw new Error('لا يمكن تكرار الموظف');
}

// 3. المسوق الخارجي يحتاج حساب بنكي
if (type === 'external_marketer' && !bank_account) {
  throw new Error('المسوق الخارجي يحتاج حساب بنكي');
}

// 4. لا يمكن تعديل عمولة معتمدة
if (commission.status !== 'pending') {
  disableEditButton();
}
```

### الودائع
```javascript
// 1. التاريخ لا يمكن أن يكون في المستقبل
if (new Date(payment_date) > new Date()) {
  throw new Error('التاريخ لا يمكن أن يكون في المستقبل');
}

// 2. المبلغ يجب أن يكون موجب
if (amount <= 0) {
  throw new Error('المبلغ يجب أن يكون أكبر من صفر');
}

// 3. شروط الاسترداد
const canRefund = (deposit) => {
  return deposit.commission_source === 'owner' 
    && ['received', 'confirmed'].includes(deposit.status);
};
```

---

## 🎨 حالات الحالة (Status States)

### حالات العمولة
```
pending (معلقة) → approved (معتمدة) → paid (مدفوعة)
```

| الحالة | يمكن التعديل | يمكن الاعتماد | يمكن الدفع | يمكن الحذف |
|--------|-------------|---------------|-----------|-----------|
| pending | ✅ | ✅ | ❌ | ✅ |
| approved | ❌ | ❌ | ✅ | ❌ |
| paid | ❌ | ❌ | ❌ | ❌ |

### حالات الوديعة
```
pending (معلقة) → received (مستلمة) → confirmed (مؤكدة)
                           ↓
                      refunded (مستردة)
```

| الحالة | يمكن التعديل | يمكن التأكيد | يمكن الاسترداد | يمكن الحذف |
|--------|-------------|-------------|---------------|-----------|
| pending | ✅ | ✅ | ❌ | ✅ |
| received | ❌ | ✅ | ✅ | ❌ |
| confirmed | ❌ | ❌ | ✅ | ❌ |
| refunded | ❌ | ❌ | ❌ | ❌ |

---

## 🔐 الصلاحيات الجديدة (14 صلاحية)

### العمولات
- `view-commissions` - عرض العمولات
- `create-commission` - إنشاء عمولة
- `update-commission` - تعديل عمولة
- `delete-commission` - حذف عمولة
- `approve-commission` - اعتماد عمولة
- `mark-commission-paid` - تحديد كمدفوعة
- `approve-commission-distribution` - اعتماد توزيع
- `reject-commission-distribution` - رفض توزيع

### الودائع
- `view-deposits` - عرض الودائع
- `create-deposit` - إنشاء وديعة
- `update-deposit` - تعديل وديعة
- `delete-deposit` - حذف وديعة
- `confirm-deposit-receipt` - تأكيد الاستلام
- `refund-deposit` - استرداد وديعة

### الأدوار
- **Admin**: جميع الصلاحيات
- **Sales Manager**: إنشاء وتوزيع واعتماد العمولات
- **Accountant**: تأكيد الودائع وصرف العمولات (دور جديد)
- **Sales**: عرض عمولاته الخاصة

---

## 🧪 قائمة الاختبار السريعة

### أساسي
- [ ] تحديث روابط API
- [ ] معالجة `message` و `meta` في الاستجابات
- [ ] معالجة رموز الأخطاء الـ 27
- [ ] عرض رسائل التحقق العربية

### التبويبات
- [ ] لوحة التحكم - 7 مؤشرات
- [ ] الإشعارات - 6 أنواع
- [ ] الوحدات المباعة - جدول + pagination
- [ ] ملخص العمولة - breakdown + جدول التوزيع
- [ ] إدارة الودائع - إنشاء + تأكيد + استرداد
- [ ] الرواتب - تقرير شهري

### العمولات
- [ ] إنشاء عمولة
- [ ] توزيع 100%
- [ ] اعتماد التوزيعات
- [ ] اعتماد العمولة
- [ ] تحديد كمدفوعة
- [ ] توليد PDF

### الودائع
- [ ] إنشاء وديعة
- [ ] التحقق من التاريخ
- [ ] تأكيد الاستلام
- [ ] استرداد (owner فقط)
- [ ] توليد PDF

### الصلاحيات
- [ ] إخفاء الأزرار حسب الصلاحية
- [ ] اختبار جميع الأدوار
- [ ] التحقق من الوصول للـ endpoints

---

## 📚 المراجع الكاملة

1. **دليل API الشامل**: [`FRONTEND_API_GUIDE.md`](FRONTEND_API_GUIDE.md) - 2000+ سطر
2. **رموز الأخطاء**: [`ERROR_CODES_REFERENCE.md`](ERROR_CODES_REFERENCE.md) - 27 رمز
3. **دليل التكامل الكامل**: [`../FRONTEND_BACKEND_CHANGES.md`](../FRONTEND_BACKEND_CHANGES.md)

---

## 💡 مثال سريع

```javascript
// إعداد API Client
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.VUE_APP_API_URL,
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// معالجة الأخطاء
api.interceptors.response.use(
  response => response,
  error => {
    const { error_code, message, errors } = error.response?.data || {};
    
    // معالجة حسب رمز الخطأ
    switch(error_code) {
      case 'COMM_003':
        alert('مجموع التوزيع يجب أن يساوي 100%');
        break;
      case 'DEP_002':
        alert('لا يمكن استرداد وديعة المشتري');
        break;
      default:
        alert(message || 'حدث خطأ');
    }
    
    // عرض أخطاء التحقق
    if (errors) {
      Object.keys(errors).forEach(field => {
        showFieldError(field, errors[field][0]);
      });
    }
    
    return Promise.reject(error);
  }
);

// جلب لوحة التحكم
const getDashboard = async () => {
  const response = await api.get('/api/sales/analytics/dashboard');
  return response.data.data;
};

// إنشاء عمولة
const createCommission = async (data) => {
  const response = await api.post('/api/sales/commissions', data);
  return response.data.data;
};

// توزيع العمولة
const distributeCommission = async (commissionId, distributions) => {
  // التحقق من المجموع = 100%
  const total = distributions.reduce((sum, d) => sum + d.percentage, 0);
  if (Math.abs(total - 100) > 0.01) {
    throw new Error('مجموع النسب يجب أن يساوي 100%');
  }
  
  // إضافة التوزيعات
  await api.post(`/api/sales/commissions/${commissionId}/distribute/lead-generation`, {
    distributions: distributions.filter(d => d.type === 'lead_generation')
  });
  
  await api.post(`/api/sales/commissions/${commissionId}/distribute/persuasion`, {
    distributions: distributions.filter(d => d.type === 'persuasion')
  });
  
  await api.post(`/api/sales/commissions/${commissionId}/distribute/closing`, {
    distributions: distributions.filter(d => d.type === 'closing')
  });
  
  await api.post(`/api/sales/commissions/${commissionId}/distribute/management`, {
    distributions: distributions.filter(d => ['sales_manager', 'team_leader', 'project_manager', 'external_marketer', 'other'].includes(d.type))
  });
};
```

---

## ⚡ نصائح سريعة

1. **دائماً تحقق من المجموع = 100%** قبل إرسال التوزيعات
2. **استخدم `error_code`** للمعالجة البرمجية
3. **استخدم `message`** للعرض للمستخدم
4. **أخفِ الأزرار** حسب الحالة والصلاحية
5. **لا تترجم رسائل التحقق** - هي بالعربية بالفعل
6. **تحقق من `commission_source`** قبل إظهار زر الاسترداد
7. **تحقق من `status`** قبل إظهار أزرار التعديل
8. **استخدم `meta.pagination`** للصفحات

---

## ✅ النظام جاهز للإنتاج

- ✅ **45 اختبار PHPUnit** - جميعها ناجحة
- ✅ **39 endpoint** - موثقة بالكامل
- ✅ **27 رمز خطأ** - مع شرح مفصل
- ✅ **14 صلاحية** - محددة بوضوح
- ✅ **6 تبويبات** - جاهزة للتكامل

**آخر تحديث**: 2026-02-02
