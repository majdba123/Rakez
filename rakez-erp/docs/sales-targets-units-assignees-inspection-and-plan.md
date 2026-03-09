# تقرير فحص وتخطيط: أهداف الفريق → عرض الوحدات المعينة + المسؤول عن كل وحدة

## 1. ملخص المتطلب

| البند | الوصف |
|-------|--------|
| **الشاشة** | أهداف الفريق (`/sales/targets`) |
| **السلوك المطلوب** | عند الضغط على هدف (بطاقة مشروع): عرض **الوحدات التي أسندها مدير الفريق للفريق** لذلك المشروع، مع كل وحدة: **مين مستلمها (المسؤول / المسوق)**. |
| **عدم الاعتماد على** | صفحة Project Tracker وواجهات `GET /sales/projects/{id}` و `GET /sales/projects/{id}/units` لأنها قد تُرجع 403 لعضو الفريق. |

---

## 2. فحص الكود الحالي (Backend فقط — مشروع rakez-erp)

> ملاحظة: واجهة المستخدم (Vue/فرونت) غير موجودة داخل هذا المستودع؛ التخطيط يُفترض أن الفرونت يستدعي الـ API المذكور.

### 2.1 شاشة أهداف الفريق والضغط على البطاقة

- **الـ API المستخدم لأهداف الفريق:**  
  `GET /api/sales/targets/my` → `SalesTargetController::my()` → `SalesTargetService::getMyTargets($user, $filters)`.
- **الصلاحية:** `sales.targets.view`.
- **السلوك الحالي (حسب وصفك):** عند الضغط على البطاقة يُستدعى `viewProjectDetails(target.contract_id)` ويتم الانتقال إلى Project Tracker (`/project-tracker/:id`)، ثم تُستدعى `getProjectDetails(projectId)` وتبويب الوحدات `getProjectUnits(projectId)` → **403** إن لم تكن للمستخدم صلاحية وصول المشروع.

### 2.2 صلاحيات الوصول للمشروع والوحدات

| الملف | الدالة / المنطق |
|------|------------------|
| `SalesProjectController::show()` | قبل إرجاع تفاصيل المشروع يستدعي `userCanAccessContract($user, $contractId)`؛ إن كانت النتيجة `false` → **403** مع رسالة "You do not have access to this project". |
| `SalesProjectController::units()` | نفس الفحص: `userCanAccessContract($user, $contractId)` → إن كان `false` → **403** بنفس الرسالة. |
| `SalesProjectService::userCanAccessContract()` | الإدارة: دائماً `true`. مدير مبيعات: إذا المشروع معيّن له أو لمدير في فريقه. موظف مبيعات: إذا المشروع معيّن لمدير في نفس الفريق (`team_id` + `SalesProjectAssignment` نشط). إذا المستخدم بدون `team_id` أو المشروع غير معيّن لفريقيه → **403**. |

النتيجة: ظهور الهدف في "أهدافي" لا يضمن أن `userCanAccessContract` سيعيد `true` (مثلاً عدم تعيين المشروع عبر `SalesProjectAssignment` لفريق المستخدم، أو عدم وجود `team_id`)، لذلك الاعتماد على واجهات المشروع/الوحدات يؤدي إلى 403 في حالات معينة.

### 2.3 مصدر البيانات المنطقي: الأهداف

| الملف | ما يوفره |
|------|----------|
| `SalesTarget` (Model) | `contract_id`, `contract_unit_id`, `marketer_id`, علاقة `contractUnits` (وحدات متعددة)، علاقة `marketer`, `contract`, `leader`. |
| `SalesTargetService::getMyTargets()` | أهداف حيث `marketer_id = $user->id` مع تحميل `contract`, `contractUnit`, `contractUnits`, `leader`. **لا يُرجع** أهداف الفريق كاملة لمشروع معيّن (لا يوجد تصفية حسب `contract_id` فقط لفريق المستخدم). |
| `SalesTargetResource` | يُرجع: `target_id`, `contract_id`, `project_name`, `units` (قائمة وحدات مع `id`, `unit_number`), `contract_unit_ids`, `assigned_by` (اسم المدير). **لا يُرجع** `marketer_id` ولا `marketer_name` (المسؤول/المستلم عن الهدف). |

الفجوة: لعرض "المسؤول عن كل وحدة" الـ Resource الحالي لا يكفي (ينقصه اسم/معرّف المسوق). ولعرض "كل الوحدات المعينة للفريق لهذا المشروع" لا يوجد حالياً endpoint يرجع أهداف المشروع للفريق (فقط `targets/my` لأهدافي).

### 2.4 وجود endpoint لأهداف حسب المشروع

- **التحقق في الكود:** لا يوجد في `routes/api.php` ولا في `SalesTargetController` أي route من نوع `targets/by-project/{id}` أو ما يعادله.
- **الاستنتاج:** "الوحدات المعينة للفريق + مستلم كل وحدة" لمشروع معيّن لا يمكن استنتاجها من الـ API الحالي إلا عبر قائمة "أهدافي" في الفرونت (تصفية حسب `contract_id`)؛ ولعرض **كل** وحدات الفريق للمشروع يلزم إما endpoint جديد أو توسيع منطق موجود.

---

## 3. خلاصة الفجوات

| البند | الحالة في الكود |
|-------|------------------|
| سلوك الضغط | يفتح Project Tracker ويعتمد على واجهات مشروع/وحدات → احتمال 403. |
| مصدر البيانات | أهداف الفريق للمشروع: غير متوفّر كـ endpoint (فقط `targets/my`). |
| عرض "المسؤول" | `SalesTargetResource` لا يتضمن `marketer_id` ولا `marketer_name`. |
| عرض مخصص للوحدات + المستلم | لا يوجد في الـ Backend منطق مخصّص لـ "وحدات معينة للفريق + مستلم" دون الاعتماد على واجهات المشروع/الوحدات. |

---

## 4. خطة العمل المقترحة (بدون تنفيذ)

### 4.1 Backend (API)

1. **إضافة حقول المستلم في الـ Resource (لأي استخدام يعرض "من المستلم")**  
   - في `SalesTargetResource`: إضافة `marketer_id` و `marketer_name` (من علاقة `marketer`).  
   - التأكد من تحميل العلاقة `marketer` في أي استدعاء يُرجع هذا الـ Resource (مثلاً في `getMyTargets` عبر `->with(..., 'marketer')`).

2. **Endpoint جديد: أهداف الفريق لمشروع معيّن (اختياري لكن موصى به)**  
   - مثلاً: `GET /api/sales/targets/by-project/{contractId}`.  
   - الصلاحية: المستخدم لديه على الأقل هدف واحد لهذا `contract_id` (أي أن المشروع ضمن "أهدافي") أو أن يكون من نفس الفريق الذي لديه أهداف على هذا المشروع؛ لا يعتمد على `userCanAccessContract`.  
   - الإرجاع: قائمة أهداف للمشروع المطلوب (للفريق أو للمستخدم حسب السياسة المطلوبة)، كل هدف يحتوي وحدات + مستلم (`marketer_name` / `marketer_id`) باستخدام الـ Resource المحدّث.  
   - تنفيذ الخدمة: دالة في `SalesTargetService` مثل `getTargetsByProject(int $contractId, User $user)` مع تصفية آمنة (نفس الفريق أو فقط أهداف المستخدم لهذا العقد).

3. **عدم ربط صلاحية العرض بـ Project Tracker:**  
   - عدم الاعتماد على `GET /sales/projects/{id}` أو `GET /sales/projects/{id}/units` لعرض "وحدات معينة + مستلم" في سياق أهداف الفريق؛ الاعتماد فقط على بيانات الأهداف (من `targets/my` أو من الـ endpoint الجديد).

### 4.2 Frontend (مفترض — خارج هذا المستودع)

1. **تغيير سلوك الضغط على البطاقة في أهداف الفريق**  
   - بدلاً من الانتقال مباشرة إلى Project Tracker: فتح **مودال أو درج أو صفحة فرعية** تعرض قائمة "الوحدات المعينة للفريق + المسؤول عن كل وحدة" لهذا المشروع.

2. **مصدر البيانات في الواجهة**  
   - إما استخدام بيانات `getMyTargets()` الحالية: تصفية حسب `contract_id` المعطى وعرض من كل هدف: `units` + حقل المستلم (بعد إضافته في الـ API).  
   - أو استدعاء الـ endpoint الجديد `targets/by-project/{contractId}` إن وُجد، وعرض النتيجة نفسها (وحدات + مستلم لكل هدف).

3. **عدم استدعاء Project Tracker (أو تفاصيل/وحدات المشروع)** عند فتح هذا العرض؛ أو استدعاؤها فقط عندما يكون المستخدم لديه صلاحية (مثلاً بعد التحقق من وجود الصلاحية أو عرض زر "تفاصيل المشروع الكاملة" للمستخدمين الذين يملكون الوصول فقط).

### 4.3 ترتيب مقترح للتنفيذ

1. تحديث `SalesTargetResource` + تحميل `marketer` في `getMyTargets`.  
2. تصميم وتنفيذ `GET /api/sales/targets/by-project/{contractId}` (السياسة + الخدمة + الـ Resource).  
3. في الفرونت: تغيير سلوك الضغط → فتح المودال/الدرج، وملؤه من `targets/my` (مصفاة بـ `contract_id`) أو من الـ endpoint الجديد، دون الاعتماد على واجهات المشروع/الوحدات لهذا العرض.

---

## 5. مراجع الكود المباشرة

| الغرض | الملف والسطور/الدوال |
|--------|------------------------|
| استرجاع أهدافي | `SalesTargetController::my()` → `SalesTargetService::getMyTargets()` |
| بنية الهدف المُرجَع | `SalesTargetResource::toArray()` |
| نموذج الهدف وعلاقاته | `SalesTarget` (علاقات: `contract`, `contractUnit`, `contractUnits`, `marketer`, `leader`) |
| صلاحية المشروع والوحدات | `SalesProjectService::userCanAccessContract()` ؛ `SalesProjectController::show()` و `units()` |
| routes الأهداف | `routes/api.php`: `GET sales/targets/my`, `PATCH sales/targets/{id}`, `POST sales/targets` (للإنشاء) |

---

تم إعداد هذا المستند **للفحص والتخطيط فقط** دون تنفيذ أي تعديل في الكود. يمكن لاحقاً تنفيذ التعديلات المحددة أعلاه عند الطلب.
