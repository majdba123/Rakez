# Permission Dictionary Freeze

## القرار الحاكم
هذه الوثيقة تجمد قاموس الصلاحيات الذي يسمح به التنفيذ من هذه النقطة وحتى نهاية MVP.

المبادئ:
- لا تضاف أي صلاحية جديدة أثناء التنفيذ إلا عبر مراجعة معمارية
- إعادة استخدام الصلاحيات الحالية أولًا إذا كانت تعبر عن نفس intent
- أي صلاحية جديدة تخص حوكمة Filament فقط يجب أن تكون تحت `admin.*`
- أي صلاحية جديدة تخص قطاعًا لا يملك family صريحًا حاليًا يجب أن تستخدم صيغة `section.subject.action`
- لا يسمح بإضافة أسماء مترادفة لنفس المعنى (`edit` مقابل `update`, `all` مقابل `view_all`, إلخ)

## الأفعال المسموح بها فقط
| الفعل | الدلالة |
|---|---|
| `view` | رؤية عنصر أو شاشة أو KPI |
| `list` | استعراض قائمة |
| `create` | إنشاء سجل جديد |
| `update` | تعديل سجل موجود |
| `delete` | حذف سجل |
| `review` | مراجعة إدارية بدون اعتماد نهائي |
| `approve` | اعتماد أو رفض قرار/طلب |
| `assign` | إسناد أو ربط أو منح |
| `export` | تصدير أو تنزيل |
| `manage` | صلاحية مركبة تستخدم فقط إذا كانت موجودة فعليًا في النظام أو إذا كان التفكيك غير مطلوب في هذه المرحلة |

## الاستثناءات الموروثة المسموح باستمرارها
هذه أسماء موجودة فعليًا في النظام الحالي وتبقى كما هي في MVP:

| الصلاحية | السبب |
|---|---|
| `use-ai-assistant` | مستخدمة فعليًا في AI layer |
| `manage-ai-knowledge` | مستخدمة فعليًا في routes وAI knowledge management |
| `dashboard.analytics.view` | مستخدمة فعليًا ومربوطة بالـ analytics العامة |
| `exclusive_projects.*` | family قائمة فعليًا |
| `second_party.*` | family قائمة فعليًا |
| `units.*` | family قائمة فعليًا |
| `departments.boards.*` | قائمة فعليًا |
| `departments.photography.*` | قائمة فعليًا |
| `departments.montage.*` | قائمة فعليًا |

## Families الحالية التي تعتبر معتمدة ومسموح بإعادة استخدامها
المصدر: `config/ai_capabilities.php`, `app/Constants/PermissionConstants.php`, `routes/api.php`

| Family | الحالة | الاستخدام في Filament |
|---|---|---|
| `contracts.*` | موجودة | نعم |
| `projects.*` | موجودة | نعم |
| `sales.*` | موجودة | نعم |
| `hr.*` | موجودة | نعم |
| `marketing.*` | موجودة | نعم |
| `credit.*` | موجودة | نعم |
| `accounting.*` | موجودة | نعم |
| `commissions.*` | موجودة | نعم |
| `commission_distributions.*` | موجودة | نعم |
| `deposits.*` | موجودة | نعم |
| `notifications.*` | موجودة | نعم |
| `employees.manage` | موجودة | نعم، لكن داخل Filament تستبدل بشاشات حوكمة أوضح |
| `tasks.create` | موجودة | لا تستخدم كصــلاحية Filament أساسية؛ تبقى تشغيلية |

## Families الجديدة المحجوزة لطبقة Filament Admin
هذه الصلاحيات لا توجد حاليًا كلها في المستودع، لكنها مجمدة الآن كأسماء معتمدة لأي إضافة لاحقة.

### Governance / Panel
| الصلاحية | الحالة | تدخل في MVP |
|---|---|---|
| `admin.panel.access` | جديدة | نعم |
| `admin.dashboard.view` | جديدة | نعم |
| `admin.navigation.view` | جديدة | نعم |
| `admin.users.view` | جديدة | نعم |
| `admin.users.manage` | جديدة | نعم |
| `admin.roles.view` | جديدة | نعم |
| `admin.roles.manage` | جديدة | نعم |
| `admin.permissions.view` | جديدة | نعم |
| `admin.permissions.manage` | جديدة | نعم |
| `admin.direct_permissions.view` | جديدة | نعم |
| `admin.direct_permissions.manage` | جديدة | نعم |
| `admin.effective_access.view` | جديدة | نعم |
| `admin.audit.view` | جديدة | نعم |
| `admin.settings.view` | جديدة | نعم |
| `admin.settings.manage` | جديدة | لاحقًا |
| `admin.health.view` | جديدة | لاحقًا |

### Inventory
قرار Phase 0: لا يستمر Filament في الاعتماد على `contracts.*` وحدها لرقابة المخزون. يتم تجميد family صريحة للمخزون للاستخدام الإداري لاحقًا.

| الصلاحية | الحالة | تدخل في MVP |
|---|---|---|
| `inventory.dashboard.view` | جديدة | لا |
| `inventory.overview.view` | جديدة | لا |
| `inventory.export` | جديدة | لا |

### AI Governance
| الصلاحية | الحالة | تدخل في MVP |
|---|---|---|
| `ai_governance.dashboard.view` | جديدة | لا |
| `ai_governance.audit.view` | جديدة | لا |
| `ai_governance.knowledge.view` | جديدة | لا |
| `ai_governance.knowledge.manage` | جديدة | لا |
| `ai_governance.calls.view` | جديدة | لا |
| `ai_governance.calls.manage` | جديدة | لا |

### Workflow / Requests Center
| الصلاحية | الحالة | تدخل في MVP |
|---|---|---|
| `workflow.requests.view` | جديدة | لا |
| `workflow.requests.review` | جديدة | لا |
| `workflow.requests.approve` | جديدة | لا |
| `workflow.requests.export` | جديدة | لا |

## قواعد استخدام الصلاحيات الحالية داخل Filament
- إذا كانت الشاشة الإدارية تمثل نفس intent الحالي في الباكند، تستخدم الصلاحية الحالية ولا يخترع اسم جديد
- إذا كانت الشاشة Governance-only ولا يوجد مقابل صريح حاليًا، تستخدم `admin.*`
- إذا كان القطاع لا يملك family واضحة حاليًا مثل `inventory`, تستخدم family جديدة مجمدة ولكن لا تفعل قبل مرحلتها

## Anchors المستخدمة لإظهار مجموعات الـ Sidebar
هذه هي الصلاحيات المرجعية الأولى لإظهار المجموعات العليا:

| المجموعة | Anchor permission |
|---|---|
| مركز القيادة | `admin.panel.access` + `admin.dashboard.view` |
| إدارة النظام والحوكمة | `admin.panel.access` |
| الائتمان | `credit.dashboard.view` |
| المحاسبة والمالية | `accounting.dashboard.view` |
| العقود والمشاريع | `contracts.view_all` أو `projects.view` |
| المبيعات | `sales.dashboard.view` |
| الموارد البشرية | `hr.dashboard.view` أو `hr.reports.view` |
| التسويق | `marketing.dashboard.view` |
| المخزون | `inventory.dashboard.view` لاحقًا، وإلى حينه لا يظهر إلا لأدوار إدارية صريحة |
| الذكاء الاصطناعي والمعرفة | `ai_governance.dashboard.view` لاحقًا أو `manage-ai-knowledge` مرحليًا |
| الطلبات وسير العمل | `workflow.requests.view` لاحقًا |

## ما يمنع من هذه النقطة
- إضافة صلاحية هجينة جديدة مثل `view-all-bookings`
- إضافة أسماء بواصلات جديدة خارج الاستثناءات الموروثة
- إضافة family جديدة لذات المعنى الموجود أصلًا
- إعطاء `admin.panel.access` عبر role-only logic بدون permission صريح
