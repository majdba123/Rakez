# Credit Module – Tabs & Views Verification (تبويبات قسم الائتمان)

مراجعة مطابقة التبويبات الرئيسية لقسم الائتمان مع المواصفات والـ API.

---

## التبويبة (1): لوحة التحكم

| المتطلب | الحالة | الـ API / الملاحظات |
|--------|--------|---------------------|
| عدد الحجوزات المؤكدة | ✅ | `GET /credit/dashboard` → `kpis.confirmed_bookings_count` |
| عدد حجوزات التفاوض | ✅ | `kpis.negotiation_bookings_count` |
| عدد حجوزات الانتظار | ✅ | `kpis.waiting_bookings_count` |
| المشاريع التي تحتاج مراجعة (مراحل) | ✅ | `requires_review_count`, `stage_breakdown`, `stage_labels_ar` (مراحل 1–5 بالعربية) |
| تنفيذ العقود / فترة التجهيز | ✅ | `title_transfer_breakdown`, `title_transfer_labels_ar` |
| مشاريع دفع عربون ورفضها البنك | ✅ | `kpis.rejected_with_paid_down_payment_count` |

**الروابط:** `GET /credit/dashboard`, `POST /credit/dashboard/refresh`

---

## التبويبة (2): الإشعارات

| المتطلب | الحالة | الـ API / الملاحظات |
|--------|--------|---------------------|
| استقبال إشعارات (حجز تفاوض، موافقة/رفض سعر، تأكيد عربون، انتقال لمؤكد، انتهاء مهلة، اكتمال إفراغ) | ✅ | إشعارات المستخدم (credit) مُرسلة عبر `UserNotification`؛ التبويبة تعرضها عبر الـ API التالي. |

**الروابط:**
- `GET /credit/notifications` — قائمة إشعارات موظف الائتمان (مع فلترة تواريخ وحالة).
- `POST /credit/notifications/{id}/read` — تعليم إشعار كمقروء.
- `POST /credit/notifications/read-all` — تعليم الكل كمقروء.

---

## التبويبة (3): إدارة الحجوزات

### 3.1 الحجوزات المؤكدة

**القائمة:** `GET /credit/bookings/confirmed`  
**تفاصيل حجز:** `GET /credit/bookings/{id}`

| القسم | الحقل في المواصفات | الحالة | مفتاح الاستجابة |
|-------|---------------------|--------|------------------|
| **3.1.1 بيانات المشروع** | اسم المشروع | ✅ | `data.project.name` |
| | رقم الوحدة | ✅ | `data.unit.number` |
| | الحي | ✅ | `data.project.district` |
| | نوع العقار | ✅ | `data.project.property_type` (نوع الوحدة) |
| | قيمة العقار | ✅ | `data.project.unit_value` أو `data.unit.price` |
| **3.1.2 بيانات العميل** | اسم العميل | ✅ | `data.client.name` |
| | رقم الهاتف | ✅ | `data.client.mobile` |
| | البريد الإلكتروني (إن وجد) | ⚠️ | `data.client.email` (يُملأ عند إضافة عمود `client_email` لجدول الحجوزات) |
| | جنسية العميل | ✅ | `data.client.nationality` |
| | رقم IBAN | ✅ | `data.client.iban` |
| **3.1.3 التفاصيل المالية** | قيمة العربون | ✅ | `data.financial.down_payment_amount` |
| | تاريخ دفع العربون | ✅ | `data.financial.down_payment_date` |
| | نسبة السعي (من المالك أو المشتري) | ✅ | `data.financial.brokerage_commission_percent`, `commission_payer` |
| | طريقة دفع العربون (كاش / تحويل / إلكتروني) | ✅ | `data.financial.payment_method` |
| | تأكيد استلام من المحاسبة | ✅ | `data.financial.down_payment_confirmed`, `requires_accounting_confirmation` |
| **3.1.4 تفاصيل التسويق** | اسم الفريق | ✅ | `data.marketing.team_name` |
| | اسم المسوق | ✅ | `data.marketing.marketer_name` |

### 3.2 سيناريوهات الحجز المؤكد

| المتطلب | الحالة | الـ API / الملاحظات |
|--------|--------|---------------------|
| 3.2.1 الدفع كاش — 7 أيام قبل الإفراغ، 7 أيام لتجهيز أوراق الإفراغ | منطق أعمال (لا يغير الـ API) | — |
| 3.2.2 بنك (مدعوم/غير مدعوم) — متتبع المراحل | ✅ | `POST /credit/bookings/{id}/financing`, `GET /credit/bookings/{id}/financing`, `PATCH /credit/financing/{id}/stage/{stage}`, `POST /credit/financing/{id}/reject` |
| مراحل المتتبع (1–5) مع المدد والأدخالات | ✅ | مراحل 1–5 مع أسماء عربية في الـ Dashboard وواجهة التمويل |

### 3.3 المشاريع على الخارطة

| المتطلب | الحالة | الملاحظات |
|--------|--------|-----------|
| عند تأكيد العربون والمشروع على الخارطة: إنشاء خطة دفعات | ⚠️ | خطط الدفع حالياً تحت صلاحية المبيعات `sales.payment-plan.manage`؛ يمكن لموظف الائتمان رؤية `payment_installments` في `GET /credit/bookings/{id}` |
| تحديد موعد إفراغ منفصل | ✅ | نقل الملكية: `PATCH /credit/title-transfer/{id}/schedule` (تحديد موعد)، ودعم عقود تحت الإنشاء |

### 3.4 إتمام الإفراغ

| المتطلب | الحالة | الـ API |
|--------|--------|---------|
| بعد اكتمال المراحل: زر "تم الإفراغ" → انتقال للمشاريع المباعة | ✅ | `POST /credit/title-transfer/{id}/complete` يحدّث الحجز إلى `credit_status = sold` |

### 3.5 إلغاء الحجز

| المتطلب | الحالة | الـ API |
|--------|--------|---------|
| في حال رفض البنك أو تراجع العميل: إلغاء الحجز / حذف من القائمة | ✅ | `POST /credit/bookings/{id}/cancel` (مع `cancellation_reason` اختياري). صلاحية الإلغاء لموظف الائتمان مضافة في الخدمة. |

### 4. حجوزات التفاوض

| المتطلب | الحالة | الـ API |
|--------|--------|---------|
| عرض حجوزات التفاوض (للمشاهدة فقط، بدون بدء إجراءات حتى التأكيد) | ✅ | `GET /credit/bookings/negotiation` (قراءة فقط) |

### 5. حجوزات الانتظار

| المتطلب | الحالة | الـ API |
|--------|--------|---------|
| رؤية حجوزات الانتظار فقط، بدون صلاحية تنفيذ إجراءات | ✅ | `GET /credit/bookings/waiting` (قراءة فقط) |

---

## التبويبة (5): إصدار ملف المطالبة والإفراغات

| المتطلب (5.1 البيانات المعروضة) | الحالة | مصدر البيانات |
|-------------------------------|--------|----------------|
| اسم المشروع | ✅ | `file_data.project_name` (ملف المطالبة وملف PDF) |
| رقم الوحدة | ✅ | `file_data.unit_number` |
| نوع الوحدة | ✅ | `file_data.unit_type` |
| نسبة السعي | ✅ | `file_data.brokerage_commission_percent` |
| قيمة الضريبة | ✅ | `file_data.tax_amount` |
| معلومات المشروع | ✅ | `file_data.project_location`, `file_data.project_district` + بيانات الوحدة والعميل والمالية في نفس الـ snapshot |

**الروابط:**
- `GET /credit/claim-files` — قائمة ملفات المطالبة (للتبويبة 5).
- `POST /credit/bookings/{id}/claim-file` — إنشاء ملف مطالبة لحجز (بعد البيع).
- `GET /credit/claim-files/{id}` — تفاصيل ملف مطالبة.
- `POST /credit/claim-files/{id}/pdf` — إنشاء PDF.
- `GET /credit/claim-files/{id}/pdf` — تحميل PDF.
- `GET /credit/sold-projects` — قائمة المشاريع المباعة (مرتبط بنفس التبويبة).

---

## ملخص روابط الـ API حسب التبويب

| التبويب | الروابط |
|---------|---------|
| (1) لوحة التحكم | `GET /credit/dashboard`, `POST /credit/dashboard/refresh` |
| (2) الإشعارات | `GET /credit/notifications`, `POST /credit/notifications/{id}/read`, `POST /credit/notifications/read-all` |
| (3) إدارة الحجوزات | `GET /credit/bookings/confirmed`, `GET /credit/bookings/negotiation`, `GET /credit/bookings/waiting`, `GET /credit/bookings/{id}`, `POST /credit/bookings/{id}/cancel`, `POST /credit/bookings/{id}/financing`, `GET/PATCH/POST` financing & title-transfer |
| (5) ملف المطالبة والإفراغات | `GET /credit/claim-files`, `POST /credit/bookings/{id}/claim-file`, `GET /credit/claim-files/{id}`, `GET /credit/sold-projects` |

---

## تنبيهات تنفيذية

1. **البريد الإلكتروني للعميل:** الحقل `data.client.email` موجود في استجابة `GET /credit/bookings/{id}` ويُرجع `null` حتى إضافة عمود `client_email` لجدول `sales_reservations` (إن رغبت بذلك لاحقاً).
2. **خطة الدفع من واجهة الائتمان:** إنشاء/تعديل خطة الدفع يتم حالياً عبر صلاحيات المبيعات؛ عرض الأقساط متوفر ضمن تفاصيل الحجز لموظف الائتمان.
