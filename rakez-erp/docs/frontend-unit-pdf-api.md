# تحميل PDF تفاصيل الوحدة — للفرونت إند

## الراوت (Route)

| المعلومة | القيمة |
|----------|--------|
| **Method** | `GET` |
| **URL** | `{BASE_URL}/api/sales/units/{unitId}/pdf` |
| **مثال** | `https://your-api.com/api/sales/units/123/pdf` |

- **{unitId}**: معرّف الوحدة (ـ `contract_unit_id` / `id` من جدول الوحدات).
- **{BASE_URL}**: عنوان الـ API (مثلاً `http://localhost:8000` في التطوير).

---

## المصادقة (Authentication)

- الـ Route محمي بـ **Sanctum**.
- مطلوب إرسال **Bearer Token** في الـ Header:

```
Authorization: Bearer {token}
```

- الـ Token يُؤخذ من تسجيل الدخول (مثلاً من استجابة `POST /api/login`).

---

## الصلاحية (Permission)

- المستخدم يجب أن يكون له صلاحية: **`sales.projects.view`**.
- الأدوار التي عادة تملك الصلاحية: **`sales`** | **`sales_leader`** | **`admin`**.

---

## الاستجابة عند النجاح (200)

- **Status:** `200 OK`
- **Content-Type:** `application/pdf`
- **Body:** ملف PDF (binary) — تفاصيل الوحدة (رقم، نوع، سعر، مساحة، دور، غرف، حمامات، واجهة، إلخ).
- **Content-Disposition:** فيه اسم الملف، مثلاً:  
  `attachment; filename="unit_U-002_2026-03-03.pdf"`

**ما يفعله الفرونت:**
- الطلب يجب أن يكون من نوع **GET** (أو نافذة/رابط تحميل).
- إذا استخدمت **fetch/axios**: استخدم `responseType: 'blob'` ثم أنشئ رابط تحميل من الـ blob واسم الملف من الـ header إن وُجد.

---

## الاستجابة عند الخطأ

### 1) وحدة غير موجودة (404)

```json
{
  "success": false,
  "message": "تحميل PDF غير متوفر لهذه الوحدة حالياً",
  "reason": "Unit has no associated contract."
}
```

- **Status:** `404 Not Found`
- **Content-Type:** `application/json`

### 2) خطأ في توليد الـ PDF أو صلاحية (503 / 403)

```json
{
  "success": false,
  "message": "تحميل PDF غير متوفر لهذه الوحدة حالياً"
}
```

- **Status:** `503 Service Unavailable` أو `403 Forbidden`
- **Content-Type:** `application/json`

في كل حالات الخطأ أعلاه، اعرض للمستخدم النص: **`message`** (مثلاً في إشعار: «تحميل PDF غير متوفر لهذه الوحدة حالياً»).

---

## ملخص للفرونت

| البند | القيمة |
|-------|--------|
| **الراوت الكامل** | `GET {BASE_URL}/api/sales/units/{unitId}/pdf` |
| **Headers** | `Authorization: Bearer {token}` |
| **نجاح** | 200 + `application/pdf` + body = ملف PDF |
| **فشل** | 404 أو 503 أو 403 + JSON فيه `success: false` و `message` |
| **نص الإشعار عند الفشل** | `تحميل PDF غير متوفر لهذه الوحدة حالياً` (أو استخدام `message` من الـ API) |

---

## مثال استدعاء (JavaScript / Fetch)

```javascript
async function downloadUnitPdf(unitId, authToken) {
  const baseUrl = 'http://localhost:8000'; // أو من env
  const url = `${baseUrl}/api/sales/units/${unitId}/pdf`;

  const res = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${authToken}`,
      'Accept': 'application/pdf',
    },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    const message = data.message || 'تحميل PDF غير متوفر لهذه الوحدة حالياً';
    throw new Error(message); // أو تعرض إشعار للمستخدم
  }

  const blob = await res.blob();
  const contentDisposition = res.headers.get('Content-Disposition');
  let filename = `unit_${unitId}.pdf`;
  if (contentDisposition) {
    const match = contentDisposition.match(/filename="?([^";]+)"?/);
    if (match) filename = match[1];
  }

  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}
```

---

## ملاحظة

- **unitId** المستخدم في الراوت هو **id** الوحدة في جدول `contract_units` (نفس الـ `id` أو `unit_id` الذي يرد في واجهة قائمة وحدات المشروع).
