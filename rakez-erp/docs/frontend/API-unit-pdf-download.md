# تحميل PDF تفاصيل الوحدة — للفرونت

## الراوت (Route)

| المعلومة | القيمة |
|----------|--------|
| **Method** | `GET` |
| **URL** | `{BASE_URL}/api/sales/units/{unitId}/pdf` |
| **مثال** | `https://your-api.com/api/sales/units/123/pdf` |

`{unitId}` = معرف الوحدة (contract_unit.id) — نفس الـ `id` اللي ييجي من قائمة وحدات المشروع.

---

## المصادقة (Auth)

- مطلوب **Laravel Sanctum**: إرسال التوكن في الهيدر.
- الهيدر:
  - `Authorization: Bearer {token}`
  - `Accept: application/pdf` (اختياري؛ لو حاب تتعامل مع الرد كـ PDF)
- المستخدم لازم يكون له:
  - دور: `sales` أو `sales_leader` أو `admin`
  - صلاحية: `sales.projects.view`

---

## الطلب (Request)

- **Query parameters:** لا يوجد.
- **Body:** لا يوجد (GET).

---

## الردود (Responses)

### نجاح (200 OK)

- **Content-Type:** `application/pdf`
- **Content-Disposition:** `attachment; filename="unit_{unit_number}_{date}.pdf"`
- **Body:** ملف PDF (binary).

مثال اسم الملف: `unit_U-002_2026-03-04.pdf`

**الفرونت:** تعامل مع الرد كـ **blob** ثم اعمل تحميل أو فتح في تاب جديد.

### خطأ 404 Not Found

الوحدة مش موجودة أو ما لها عقد مرتبط.

```json
{
  "success": false,
  "message": "تحميل PDF غير متوفر لهذه الوحدة حالياً",
  "reason": "Unit has no associated contract."
}
```

### خطأ 503 Service Unavailable

خطأ أثناء توليد الـ PDF (مثلاً من مكتبة mPDF).

```json
{
  "success": false,
  "message": "تحميل PDF غير متوفر لهذه الوحدة حالياً"
}
```

### 401 Unauthorized

ما في توكن أو التوكن منتهي/غلط.

### 403 Forbidden

المستخدم ما عنده صلاحية `sales.projects.view` أو ما له دور sales/sales_leader/admin.

---

## مثال استخدام (Frontend — Fetch)

```javascript
const API_BASE = 'https://your-api.com'; // أو process.env.VITE_API_URL

async function downloadUnitPdf(unitId, authToken) {
  const url = `${API_BASE}/api/sales/units/${unitId}/pdf`;
  const res = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${authToken}`,
      'Accept': 'application/pdf',
    },
  });

  const contentType = res.headers.get('Content-Type') || '';

  if (!res.ok) {
    if (contentType.includes('application/json')) {
      const data = await res.json();
      throw new Error(data.message || 'تحميل PDF غير متوفر لهذه الوحدة حالياً');
    }
    throw new Error('تحميل PDF غير متوفر لهذه الوحدة حالياً');
  }

  if (contentType.includes('application/pdf')) {
    const blob = await res.blob();
    const disposition = res.headers.get('Content-Disposition');
    let filename = `unit_${unitId}.pdf`;
    if (disposition) {
      const match = disposition.match(/filename="?([^";]+)"?/);
      if (match) filename = match[1].trim();
    }
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
    return;
  }

  const data = await res.json();
  throw new Error(data.message || 'تحميل PDF غير متوفر لهذه الوحدة حالياً');
}
```

---

## ملاحظات للفرونت

1. **معرف الوحدة:** استخدم نفس `unit.id` أو `unit_id` اللي يظهر في تفاصيل الوحدة أو في قائمة وحدات المشروع (من `GET /api/sales/projects/{contractId}/units`).
2. **عرض الإشعار:** إذا كان الرد `!res.ok` أو حصل استثناء، اعرض للمستخدم الرسالة: **«تحميل PDF غير متوفر لهذه الوحدة حالياً»** (من `data.message` إن وُجد).
3. **لا تحاول parse الرد كـ JSON عند النجاح:** عند 200 الرد يكون ملف PDF (binary)، لا تستدعي `res.json()` إلا في حالة الخطأ عندما `Content-Type` يكون `application/json`.

---

## الراوتات ذات الصلة (لنفس السياق)

| الوظيفة | Method | URL |
|---------|--------|-----|
| قائمة المشاريع | GET | `/api/sales/projects` |
| تفاصيل مشروع | GET | `/api/sales/projects/{contractId}` |
| وحدات المشروع | GET | `/api/sales/projects/{contractId}/units` |
| **تحميل PDF الوحدة** | **GET** | **`/api/sales/units/{unitId}/pdf`** |

كلها تحت نفس الـ prefix: `auth:sanctum` + أدوار sales/sales_leader/admin، ووحدات المشروع و PDF يتطلبان صلاحية `sales.projects.view`.
