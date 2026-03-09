# واجهات أهداف الفريق — الوحدات المعينة + المسؤول (مرجع الفرونت)

مرجع موحّد لجميع الـ APIs والبيانات المتعلقة بشاشة **أهداف الفريق** وعرض **الوحدات المعينة للفريق + من المستلم لكل وحدة**. الهدف يُسند ل**موظف مبيعات من الفريق** — أي شخص نوعه في النظام **`sales`** (عضو الفريق) — ويُعرض عنده في "أهداف الفريق". قديم وجديد مع أمثلة.

---

## 1. الصلاحيات

| الصلاحية | الاستخدام |
|----------|-----------|
| `sales.targets.view` | عرض أهدافي + عرض أهداف المشروع (by-project). |
| `sales.targets.update` | تحديث حالة الهدف (PATCH) — الموظف صاحب الهدف يمكنه جعل الهدف «منجز» (تحقق: الوحدة حُجزت أو بيعت). |
| `sales.team.manage` | إنشاء أهداف + جلب أعضاء الفريق (مدير المبيعات فقط). |

---

## 2. الـ Endpoints (قديم + جديد)

### 2.1 أهدافي / أهداف الفريق (قائمة البطاقات في الصفحة)

| | |
|---|---|
| **Method / URL** | `GET /api/sales/targets/my` |
| **الصلاحية** | `sales.targets.view` |
| **الاستخدام** | جلب الأهداف لعرض البطاقات في شاشة أهداف الفريق. |
| **سلوك حسب الدور** | **مدير مبيعات (sales_leader):** يُرجَع **أهداف الفريق كاملة** (كل الأهداف المعينة لموظفي فريقه). **موظف مبيعات (عضو الفريق):** يُرجَع **أهدافه فقط** (الأهداف المسندة إليه كمستلم). في الحالتين كل عنصر يحتوي `marketer_id` و `marketer_name` = معرّف واسم **موظف المبيعات المستلم** (عضو الفريق). |

**Query (اختياري):**

- `from` — تاريخ بداية
- `to` — تاريخ نهاية
- `status` — new | in_progress | completed
- `per_page` — عدد في الصفحة (افتراضي 15)

**مثال طلب:**

```http
GET /api/sales/targets/my?per_page=20
Authorization: Bearer {token}
```

**مثال استجابة ناجحة (200):**

```json
{
  "success": true,
  "data": [
    {
      "target_id": 1,
      "contract_id": 20,
      "project_name": "مشروع الرياض الجديدة 17",
      "unit_number": "A-101",
      "contract_unit_ids": [101, 102],
      "units": [
        { "id": 101, "unit_number": "A-101" },
        { "id": 102, "unit_number": "A-102" }
      ],
      "target_type": "reservation",
      "target_type_label_ar": "حجز",
      "start_date": "2026-03-01",
      "end_date": "2026-03-18",
      "status": "new",
      "status_label_ar": "جديد",
      "leader_notes": null,
      "assigned_by": "أحمد المدير",
      "marketer_id": 5,
      "marketer_name": "محمد (موظف المبيعات)"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

**شكل عنصر واحد في `data` (لأي هدف):**

| الحقل | النوع | الوصف |
|-------|--------|--------|
| `target_id` | number | معرّف الهدف |
| `contract_id` | number | معرّف العقد/المشروع (استخدمه للضغط على البطاقة ولواجهة by-project) |
| `project_name` | string | اسم المشروع |
| `unit_number` | string \| null | رقم الوحدة الأولى (للتوافق القديم) |
| `contract_unit_ids` | number[] | معرّفات الوحدات المعينة لهذا الهدف |
| `units` | array | قائمة `{ id, unit_number }` لكل وحدة معينة |
| `target_type` | string | reservation \| negotiation \| closing |
| `target_type_label_ar` | string | حجز \| تفاوض \| إقفال |
| `start_date` | string | Y-m-d |
| `end_date` | string | Y-m-d |
| `status` | string | new \| in_progress \| completed |
| `status_label_ar` | string | جديد \| قيد التنفيذ \| منجز |
| `leader_notes` | string \| null | ملاحظات المدير |
| `assigned_by` | string | اسم مدير الفريق الذي أسند الهدف |
| `marketer_id` | number | معرّف المستلم — مستخدم نوعه في النظام **`sales`** (عضو الفريق الذي أُسند له الهدف) |
| `marketer_name` | string \| null | اسم المستلم — مستخدم نوعه **`sales`** (المسؤول عن الوحدات في هذا الهدف؛ يُعرض الهدف عنده في أهداف الفريق) |

---

### 2.2 أهداف المشروع — الوحدات المعينة + المستلم (عند الضغط على البطاقة)

| | |
|---|---|
| **Method / URL** | `GET /api/sales/targets/by-project/{contractId}` |
| **الصلاحية** | `sales.targets.view` |
| **الاستخدام** | عند الضغط على بطاقة هدف في أهداف الفريق: جلب كل الأهداف (وحدات + مستلم) لهذا المشروع فقط، دون استدعاء واجهات المشروع/الوحدات (لتجنب 403). |

**مثال طلب:**

```http
GET /api/sales/targets/by-project/20
Authorization: Bearer {token}
```

**مثال استجابة ناجحة (200):**

```json
{
  "success": true,
  "data": [
    {
      "target_id": 1,
      "contract_id": 20,
      "project_name": "مشروع الرياض الجديدة 17",
      "unit_number": "A-101",
      "contract_unit_ids": [101, 102],
      "units": [
        { "id": 101, "unit_number": "A-101" },
        { "id": 102, "unit_number": "A-102" }
      ],
      "target_type": "reservation",
      "target_type_label_ar": "حجز",
      "start_date": "2026-03-01",
      "end_date": "2026-03-18",
      "status": "new",
      "status_label_ar": "جديد",
      "leader_notes": null,
      "assigned_by": "أحمد المدير",
      "marketer_id": 5,
      "marketer_name": "محمد (موظف المبيعات)"
    },
    {
      "target_id": 2,
      "contract_id": 20,
      "project_name": "مشروع الرياض الجديدة 17",
      "unit_number": "B-201",
      "contract_unit_ids": [201],
      "units": [
        { "id": 201, "unit_number": "B-201" }
      ],
      "target_type": "negotiation",
      "target_type_label_ar": "تفاوض",
      "start_date": "2026-03-01",
      "end_date": "2026-03-25",
      "status": "in_progress",
      "status_label_ar": "قيد التنفيذ",
      "leader_notes": null,
      "assigned_by": "أحمد المدير",
      "marketer_id": 7,
      "marketer_name": "سارة (موظفة المبيعات)"
    }
  ]
}
```

**ملاحظات:**

- لا يوجد `meta` (بدون pagination).
- نفس شكل العنصر المستخدم في `targets/my`: كل عنصر = هدف واحد يحتوي `units` + `marketer_name` (المستلم).
- يُسمح بالوصول فقط إذا كان للمستخدم هدف على هذا المشروع أو لفريقه أهداف على نفس المشروع؛ وإلا 403.

**مثال استجابة 403:**

```json
{
  "success": false,
  "message": "You do not have access to targets for this project"
}
```

---

### 2.3 تحديث حالة الهدف — الموظف صاحب الهدف يعدّله (تحقق: حجز أو بيع)

| | |
|---|---|
| **Method / URL** | `PATCH /api/sales/targets/{id}` |
| **الصلاحية** | `sales.targets.update` |
| **من يعدل** | **موظف المبيعات صاحب الهدف** (المستلم، أي المستخدم الذي `marketer_id` = له) يمكنه تعديل الهدف وجعله **منجز** عندما تحقّق الهدف (الوحدة حُجزت أو بيعت). المدير أيضاً يمكنه تحديث أهداف فريقه. |
| **Body** | `{ "status": "new" | "in_progress" | "completed" }` — استخدم `"completed"` لتعليم الهدف كـ **منجز** (تحقق). |

**مثال طلب (جعل الهدف منجز):**

```http
PATCH /api/sales/targets/1
Authorization: Bearer {token}
Content-Type: application/json

{ "status": "completed" }
```

**استجابة ناجحة (200):** تُرجَع نفس بنية الهدف مع `status: "completed"` و `status_label_ar: "منجز"`.

**ملاحظة:** اعرض زر أو خيار «منجز» / «تحقق» للبطاقة فقط عندما يكون المستخدم الحالي هو صاحب الهدف (`marketer_id === currentUser.id`) أو مدير الفريق، واستدعِ الـ PATCH عند الضغط.

---

### 2.4 إنشاء هدف (مدير الفريق فقط — قديم)

| | |
|---|---|
| **Method / URL** | `POST /api/sales/targets` |
| **الصلاحية** | `sales.team.manage` |
| **الاستخدام** | إنشاء هدف جديد — الهدف يُسند لمستخدم **نوعه في النظام `sales`** من الفريق (`marketer_id` = أحد أعضاء الفريق من `team/members`) ويُعرض عند هذا الموظف في أهداف الفريق. |

---

### 2.5 أعضاء فريق المبيعات (للقائد — عرض القائمة عند مشاهدة الأهداف أو إنشاء هدف جديد)

| | |
|---|---|
| **Method / URL** | `GET /api/sales/team/members` |
| **الصلاحية** | `sales.team.manage` (مدير المبيعات فقط) |
| **الاستخدام** | جلب **أعضاء فريق المبيعات** التابعين لمدير المبيعات الحالي — لعرضهم في صفحة أهداف الفريق (من يستلم كل هدف) وعند إنشاء هدف جديد (قائمة المسوقين لاختيار المستلم). |

**Query (اختياري):**

- `with_ratings` — إذا `true` (الافتراضي) يُرجَع مع كل عضو: تقييم المدير، عدد الحجوزات المؤكدة. إذا `false` تُرجَع بيانات أساسية فقط (مناسبة لقائمة اختيار عند إنشاء هدف).

**مثال طلب:**

```http
GET /api/sales/team/members?with_ratings=false
Authorization: Bearer {token}
```

**مثال استجابة ناجحة (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "محمد المسوق",
      "email": "mohamed@example.com",
      "team_id": 1,
      "leader_rating": null,
      "leader_rating_comment": null,
      "confirmed_reservations_count": 0
    },
    {
      "id": 7,
      "name": "سارة (موظفة المبيعات)",
      "email": "sara@example.com",
      "team_id": 1,
      "leader_rating": 4,
      "leader_rating_comment": "أداء جيد",
      "confirmed_reservations_count": 3
    }
  ]
}
```

**ملاحظات:**

- المتاح فقط لمستخدم لديه صلاحية `sales.team.manage` (مدير المبيعات). إن لم تكن الصلاحية متوفرة يُرجع 403.
- كل عنصر في `data` يمثّل **مستخدم نوعه في النظام `sales`** (موظف مبيعات من الفريق) — مدير المبيعات يسند له الأهداف ويُعرض الهدف عند هذا الموظف في صفحة أهداف الفريق. استخدم `id` كـ `marketer_id` عند إنشاء هدف، و `name` للعرض في الواجهة.

---

## 3. سلوك مطلوب في الواجهة (أهداف الفريق)

### عرض أعضاء الفريق (مدير المبيعات)

- عند فتح صفحة **أهداف الفريق** أو نموذج **إضافة هدف جديد**: إذا كان المستخدم مدير مبيعات (لديه `sales.team.manage`) استدعِ **`GET /api/sales/team/members`** لعرض قائمة أعضاء فريقه (مثلاً جانب الصفحة أو في قائمة اختيار المستلم عند إنشاء هدف). استخدم `data[].id` كـ `marketer_id` و `data[].name` للعرض.

### تعديل الهدف من قبل صاحبه (تحقق — الوحدة حُجزت أو بيعت)

- **موظف المبيعات صاحب الهدف** (المستلم، حيث `marketer_id` = المستخدم الحالي) يجب أن يستطيع **تعديل الهدف** وجعله **منجز** عندما تتحقق الغاية (الوحدة حُجزت أو بيعت).
- اعرض زر أو قائمة حالات (مثل «جديد» / «قيد التنفيذ» / «منجز») على البطاقة عندما يكون المستخدم صاحب الهدف أو مدير الفريق.
- عند اختيار «منجز» (أو «تحقق»): استدعِ **`PATCH /api/sales/targets/{target_id}`** مع `{ "status": "completed" }`. الاستجابة تحتوي الهدف المحدّث مع `status: "completed"` و `status_label_ar: "منجز"`.

### عند الضغط على بطاقة هدف (مشروع)

- **لا تفعل:** لا تنتقل إلى Project Tracker ولا تستدعي:
  - `GET /api/sales/projects/{id}`
  - `GET /api/sales/projects/{id}/units`
  لأنها قد تعيد **403** لعضو الفريق رغم ظهور الهدف في أهداف الفريق.

- **افعل:**
  1. افتح **مودال أو درج** في نفس الصفحة.
  2. استدعِ **`GET /api/sales/targets/by-project/{contractId}`** حيث `contractId = target.contract_id` من البطاقة.
  3. اعرض في المودال:
     - عنوان المشروع (مثلاً من أول عنصر: `data[0].project_name`).
     - قائمة: **لكل هدف في `data`** اعرض وحداته (`units`) ومع كل وحدة أو مع كل صف: **موظف المبيعات المستلم = `marketer_name`** (واختياريًا `marketer_id`). الهدف يُعرض عند هذا الموظف في صفحة أهداف الفريق.

### مثال عرض في المودال (من استجابة by-project)

من `data` يمكن بناء جدول أو قائمة كالتالي:

| رقم الوحدة | موظف المبيعات (المستلم) |
|------------|--------------------------|
| A-101      | محمد                     |
| A-102      | محمد                     |
| B-201      | سارة                     |

أي: لكل عنصر في `data` خذ `units` وكرر كل وحدة مع نفس `marketer_name` لهذا العنصر.

```js
// مثال (لوجيك فقط)
const rows = data.flatMap((target) =>
  target.units.map((unit) => ({
    unit_id: unit.id,
    unit_number: unit.unit_number,
    marketer_id: target.marketer_id,
    marketer_name: target.marketer_name,
  }))
);
```

---

## 4. ملخص سريع للفرونت

| الغرض | الـ API | متى |
|--------|---------|-----|
| قائمة البطاقات (أهداف الفريق) | `GET /api/sales/targets/my` | تحميل الصفحة + pagination. للقائد = أهداف الفريق كاملة؛ للمسوق = أهدافه فقط. |
| أعضاء فريق المبيعات (للقائد) | `GET /api/sales/team/members` | عند تحميل صفحة أهداف الفريق أو نموذج "إضافة هدف" — لعرض أسماء الفريق و/أو قائمة اختيار المستلم (marketer_id). |
| وحدات معينة + مستلم عند الضغط | `GET /api/sales/targets/by-project/{contractId}` | عند فتح المودال (contractId من البطاقة) |
| تحديث حالة هدف (منجز / تحقق) | `PATCH /api/sales/targets/{id}` مع `{ "status": "completed" }` | عندما الموظف صاحب الهدف يعدّل ويجعل الهدف منجز (الوحدة حُجزت أو بيعت) |
| عدم الاستخدام لهذا العرض | `GET /api/sales/projects/{id}` و `GET /api/sales/projects/{id}/units` | لا تستدعيهما داخل مودال "وحدات معينة + مستلم" |

---

## 5. شكل العنصر الموحّد (Target) في أي استجابة

يُستخدم هذا الشكل في كل من `targets/my` و `targets/by-project` و `store` و `update`:

```ts
interface SalesTargetItem {
  target_id: number;
  contract_id: number;
  project_name: string;
  unit_number: string | null;
  contract_unit_ids: number[];
  units: { id: number; unit_number: string }[];
  target_type: 'reservation' | 'negotiation' | 'closing';
  target_type_label_ar: string;
  start_date: string;
  end_date: string;
  status: 'new' | 'in_progress' | 'completed';
  status_label_ar: string;
  leader_notes: string | null;
  assigned_by: string;
  /** المستلم: مستخدم نوعه في النظام = sales (موظف مبيعات من الفريق) */
  marketer_id: number;
  marketer_name: string | null;
}
```

جميع الحقول أعلاه مضمونة من الـ Backend في الاستجابات التي تستخدم `SalesTargetResource`. المستلم (`marketer_id` / `marketer_name`) هو دائماً مستخدم **نوعه `sales`** في النظام.
