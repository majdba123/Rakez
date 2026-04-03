# 1) التقرير العربي

## 1. الملخص التنفيذي
الشفرة الحالية تمثل نظام ERP عقاري API-first غني بالنطاقات التجارية، وليس مجرد API صغير. الدليل واضح في `routes/api.php` و`app/Services` و`app/Models` و`database/migrations`. تم العثور فعليًا على أقسام: إدارة المشاريع/العقود، الوحدات/المخزون، المبيعات، التسويق، الموارد البشرية، الائتمان، المحاسبة، المشاريع الحصرية، الذكاء الاصطناعي/المعرفة/الاتصالات، والإعلانات.

Filament مناسب جدًا لبناء لوحة الإدارة المستقبلية، لكن ليس كبديل لطبقة الأعمال الحالية. التوصية المعمارية هي إبقاء Laravel API + Services كعمود فقري، ثم إضافة Filament كطبقة إدارة وتشغيل فوقها. من ناحية الصلاحيات، النظام الحالي ليس RBAC خالصًا ولا ABAC مكتملًا؛ هو مزيج غير متسق: Spatie Permissions كأساس RBAC، مع سياسات وصفقات row-level متناثرة داخل Policies وServices وControllers، ومع طبقة سياقية أكثر نضجًا فقط داخل وحدة AI.

أكبر المخاطر قبل اعتماد Filament ليست في Filament نفسه، بل في توحيد الصلاحيات، وتنظيف التسمية، ومعالجة التناقضات الحالية مثل ازدواج تسجيل الـ routes بين `bootstrap/app.php` و`AppServiceProvider.php`، والتباين بين `team` و`team_id`، وعدم وجود Soft Deletes حقيقية للمستخدمين رغم أن خدمة التسجيل تتعامل معها كما لو كانت موجودة في `app/Services/registartion/register.php` مقابل `database/migrations/0001_01_01_000000_create_users_table.php`.

## 2. نظرة عامة على النظام الحالي
النظام Monolithic Laravel حديث نسبيًا، لكنه منظم وظيفيًا حول Services أكثر من كونه Modular Package Architecture كاملة. أغلب منطق الأعمال موجود في الخدمات، والـ controllers في الغالب طبقة توجيه/تنسيق. يوجد نمط DDD أو Ports/Adapters بشكل أوضح فقط في وحدة الإعلانات عبر `app/Providers/AdsServiceProvider.php` و`app/Domain/Ads` و`app/Application/Ads`.

المرجع الأساسي الذي بُني عليه التحليل: `composer.json`، `composer.lock`، `routes/api.php`، `routes/web.php`، `config/auth.php`، `config/permission.php`، `config/user_types.php`، `config/ai_capabilities.php`، `app/Models/User.php`، `app/Providers/AppServiceProvider.php`، `bootstrap/app.php`، `app/Http/Kernel.php`.

واجهة الويب الحالية ليست Admin Panel حقيقيًا. الموجود فعليًا صفحات Blade محدودة للاختبار/التقارير/PDF مثل `routes/web.php` و`resources/views`. Filament: `Not found in codebase`.

## 3. التقنيات والإصدارات المكتشفة من المشروع

| العنصر | المكتشف من المستودع |
|---|---|
| PHP | `^8.2` في `composer.json` |
| Laravel | `laravel/framework ^12.0` ومقفول على `v12.37.0` في `composer.lock` |
| Auth API | `laravel/sanctum ^4.2` ومقفول على `v4.2.1` |
| Permissions | `spatie/laravel-permission ^6.24` ومقفول على `6.24.0` |
| Realtime | `laravel/reverb v1.6.3` + `laravel-echo` + `pusher-js` |
| AI | `openai-php/laravel v0.18.0` |
| Export/PDF | `maatwebsite/excel 3.1.67` + `barryvdh/laravel-dompdf v3.1.1` + `mpdf` |
| Frontend tooling | `vite ^7.0.7`, `tailwindcss ^4.0.0` في `package.json` |
| Filament | `Not found in codebase` |
| Tenancy package | `Not found in codebase` |
| Audit package عام | `Not found in codebase`، مع وجود جداول/Listeners خاصة بالـ AI فقط |

## 4. الوحدات/الأقسام الفعلية المكتشفة في الكود

| القسم | دلائل الكود | ملاحظات |
|---|---|---|
| إدارة المشاريع والعقود | `ContractController`, `ContractService`, `Contract`, `ContractUnit`, `SecondPartyData` | نواة النظام العقاري |
| المبيعات | `SalesProjectController`, `SalesReservationController`, `SalesTargetController`, `SalesDashboardService` | حجز، أهداف، انتظار، تفاوض، دفعات |
| التسويق | `MarketingProjectController`, `LeadController`, `MarketingReportController`, `MarketingDashboardService` | خطط، حملات، Leads، مهام، تقارير |
| الموارد البشرية | `HrUserController`, `HrTeamController`, `EmployeeContractController`, `HrDashboardService` | موظفون، فرق، عقود، إنذارات، أداء |
| المخزون | مسارات `inventory/*` + `InventoryDashboardService` + `InventoryAgencyOverviewService` | يعتمد بقوة على العقود والوحدات |
| الائتمان | `CreditBookingController`, `CreditFinancingController`, `TitleTransferController`, `ClaimFileController` | تمويل، إفراغ، ملفات مطالبة |
| المحاسبة | `AccountingCommissionController`, `AccountingDepositController`, `AccountingSalaryController` | عمولات، عربون، رواتب، تأكيدات |
| المشاريع الحصرية | `ExclusiveProjectController`, `ExclusiveProjectRequest` | طلب/اعتماد/إكمال/تصدير عقد |
| الذكاء الاصطناعي | `AiV2Controller`, `AssistantChatController`, `AiCallController`, `AssistantKnowledgeController` | Assistant, RAG, calls, audits |
| الإعلانات | `AdsInsightsController`, `AdsLeadsController`, `AdsOutcomeController` | Meta/Snap/TikTok analytics/outcomes |
| وحدات مشتركة | `NotificationController`, `ChatController`, `MyTasksController`, `CityController`, `DistrictController` | إشعارات، دردشة، مهام، بيانات مرجعية |

## 5. تفصيل كل قسم:

| القسم | الغرض التجاري | الكيانات المرتبطة | أهم العمليات الحالية | ما الذي ينبغي أن يظهر في لوحة Filament لهذا القسم |
|---|---|---|---|---|
| إدارة المشاريع والعقود | إدخال المشروع العقاري واعتماده وتجهيزه للتسويق/البيع | `Contract`, `ContractInfo`, `ContractUnit`, `SecondPartyData`, `BoardsDepartment`, `PhotographyDepartment`, `MontageDepartment`, `Team` | إنشاء/تعديل عقد، اعتماد إداري، جاهزية PM، رفع وحدات CSV، بيانات الطرف الثاني، تتبع اللوحات/التصوير/المونتاج، ربط الفرق | Resources للعقود والوحدات والطرف الثاني، Pages للجاهزية والتتبع، Widgets للعقود المعلقة/المعتمدة/الجاهزة |
| المبيعات | تحويل المشاريع الجاهزة إلى حجوزات ومتابعة أداء الفرق | `SalesReservation`, `SalesTarget`, `SalesAttendanceSchedule`, `SalesProjectAssignment`, `SalesWaitingList`, `NegotiationApproval`, `ReservationPaymentInstallment` | عرض المشاريع والوحدات، إنشاء/تأكيد/إلغاء حجز، انتظار، تفاوض، أهداف، حضور، دفعات | Resources للحجوزات والانتظار والأهداف، Pages للمشاريع والتفاوض، Widgets للحجوزات المؤكدة/التفاوض/الودائع |
| التسويق | تشغيل المشاريع التسويقية وإدارة الخطط والـ leads والتقارير | `MarketingProject`, `DeveloperMarketingPlan`, `EmployeeMarketingPlan`, `MarketingCampaign`, `MarketingBudgetDistribution`, `ExpectedBooking`, `MarketingTask`, `Lead`, `ProjectMedia`, `MarketingSetting` | إنشاء الخطط، التوزيع، توقع الحجوزات، المهام، ربط الفريق بالمشروع، Leads، تقارير وتصدير | Resources للمشاريع والخطط والـ leads والمهام والإعدادات، Pages للتقارير وتوليد الخطة، Widgets للـ leads والقيمة المتاحة والتكلفة |
| الموارد البشرية | إدارة الموظفين والفرق والعقود والتحذيرات والتقارير | `User`, `Team`, `EmployeeContract`, `EmployeeWarning`, `ManagerEmployeeReview`, `Task` | CRUD موظفين، ملفات موظف، فرق، عقود موظفين PDF، تحذيرات، تقييمات، تقارير أداء | Resources للموظفين والفرق والعقود والتحذيرات، Pages للتقارير، Widgets للعقود المنتهية/النشطة/الأداء |
| المخزون | رؤية العقود والوحدات وتغطية المواقع والوكالات | `Contract`, `ContractUnit`, `SecondPartyData` | مواقع العقود، نظرة الوكالة، Dashboard للمخزون | Pages/Wizards للمخزون، Widgets للوحدات حسب الحالة والتغطية الجغرافية |
| الائتمان | متابعة الحجز بعد البيع حتى التمويل ونقل الملكية وملفات المطالبة | `SalesReservation`, `CreditFinancingTracker`, `TitleTransfer`, `ClaimFile` | تبويبات الحجوزات، تحديث بيانات العميل، مراحل التمويل 1-5، title transfer، claim files | Resources للحجوزات الائتمانية والتمويل والإفراغ والمطالبات، Widgets للمراحل المتأخرة والطلبات المنتظرة |
| المحاسبة | احتساب وتوزيع العمولات ومتابعة العربون والرواتب | `Commission`, `CommissionDistribution`, `Deposit`, `AccountingSalaryDistribution` | الوحدات المباعة، عمولات يدوية، تحديث التوزيعات، اعتماد/رفض/دفع، تأكيد/استرجاع عربون، رواتب | Resources للعمولات والتوزيعات والعربون والرواتب، Pages للتأكيدات، Widgets للأرصدة والحالات |
| المشاريع الحصرية | طلب مشروع حصري ثم اعتماده وتحويله إلى عقد | `ExclusiveProjectRequest` | طلب، موافقة/رفض، إكمال عقد، تصدير PDF | Resource للطلبات، Pages للمراجعة والتصدير، Widget لطلبات الانتظار |
| الذكاء الاصطناعي | مساعدة داخلية، قاعدة معرفة، مكالمات AI، تدقيق AI | `AiDocument`, `AiChunk`, `AiAuditEntry`, `AiCall`, `AiCallScript`, `AssistantKnowledgeEntry`, `AssistantConversation` | Assistant chat، RAG، إدارة المعرفة، مكالمات AI، analytics، audits | Admin-only pages للمعرفة والسجلات والـ prompts والاتصالات، Widgets للصحة والاستخدام |
| الإعلانات | مزامنة حسابات وحملات وInsights وربط النتائج | `AdsPlatformAccount` ونماذج البنية التحتية في Ads | مزامنة insights، leads export، outcomes | Pages تشغيلية للإعلانات والتحليلات، Widgets للـ CPL/ROAS حسب المشروع |
| الوحدات المشتركة | تشغيل النظام اليومي غير القطاعي | `UserNotification`, `AdminNotification`, `Conversation`, `Message`, `Task`, `City`, `District` | إشعارات عامة/خاصة، دردشة، مهام، مدن وأحياء | Admin resources للبيانات المرجعية والإشعارات والمهام، لا حاجة لتحويل الدردشة كلها إلى CRUD تقليدي |

ملاحظات تشغيلية مهمة من الكود:
- جاهزية المشروع التسويقية موثقة صراحة في `app/Models/Contract.php` عبر `checkMarketingReadiness()`، وتستخدمها `app/Services/Contract/ContractService.php` عند `ready`.
- قائمة مشاريع المبيعات حاليًا أوسع من التخصيص المتوقع؛ `app/Services/Sales/SalesProjectService.php` يعرض جميع العقود المكتملة للمبيعات، مع منطق narrowing غير مستخدم فعليًا في `index`.
- تحويل الـ lead في `app/Http/Controllers/Marketing/LeadController.php` يغيّر الحالة إلى `converted` فقط؛ إنشاء reservation/customer فعلي `Not found in codebase`.

## 6. تحليل المعمارية الحالية

### نقاط القوة
- تغطية ERP واسعة ومباشرة لواقع شركة عقارية: مشاريع، وحدات، حجوزات، تمويل، عمولات، HR، تقارير.
- وجود Service Layer واضح في أغلب الأقسام، ما يجعل إعادة استخدام منطق الأعمال داخل Filament ممكنًا.
- وجود Dashboards وتقارير حالية في المبيعات والتسويق والموارد البشرية والائتمان والمحاسبة.
- وجود Events/Listeners/Jobs مفيد، خاصة في AI والإعلانات.
- وحدة الإعلانات أكثر نضجًا بنيويًا من بقية الوحدات، ويمكن اعتبارها مرجعًا للتصميم الطبقي.

### نقاط الضعف
- خطر ازدواج تسجيل الـ routes: `bootstrap/app.php` يستخدم `withRouting()` بينما `app/Providers/AppServiceProvider.php` يعيد `mapApiRoutes()` و`mapWebRoutes()`. هذا Smell قوي ويحتاج تأكيدًا وقت التشغيل.
- تسجيل الـ middleware مزدوج أيضًا بين `bootstrap/app.php` و`app/Http/Kernel.php`.
- ملفات Middleware المشار إليها في `bootstrap/app.php` مثل `AdminMiddleware`, `ProjectManagementMiddleware`, `EnsureSalesLeader`, `EditorMiddleware` لم أجدها داخل `app/Http/Middleware`. `Needs confirmation`.
- `routes/api.php` كبير جدًا ومكدّس domain-wise، ما يضعف discoverability والصيانة.
- تناقض `team` النصي القديم مع `team_id` المفتاح الخارجي الجديد موجود في المهاجرات وفي عدة خدمات.
- خدمة التسجيل تتعامل مع Soft Deletes (`onlyTrashed`, `deleted_at`) بينما نموذج/جدول المستخدم لا يثبت ذلك.
- فلاتر غير مستخدمة أو ناقصة، مثل `include_public_status_contracts` في `app/Http/Controllers/Contract/ContractController.php` ولا يوجد استهلاك لها في `ContractService`.
- بعض الـ flows غير مكتملة: `LeadController::convert`, و`TeamManagementController::assignCampaign` موجودة دون route API فعّال.
- Naming drift ملحوظ: `accounting` مقابل `accountant`, `sales_leader` مقابل `sales_manager`, وملفات `registartion` المكتوبة بخطأ إملائي.

### التعقيد
- التعقيد الحالي `متوسط إلى مرتفع`.
- السبب ليس فقط كثرة الجداول، بل كثرة الاعتمادات المتبادلة: العقد -> المشروع التسويقي -> الحجز -> الائتمان -> الإفراغ/المطالبة -> العمولة/العربون/الرواتب.

### مشاكل التسمية أو التنظيم
- `team` و`team_id`.
- `scopeMarketers()` في `User` يعيد نوع `sales` فقط، وهذا اسم مضلل.
- Permission names داخل بعض أدوات AI لا تطابق السجل المركزي في `config/ai_capabilities.php`، مثل `app/Services/AI/Tools/SearchRecordsTool.php` و`app/Services/AI/Tools/GetLeadSummaryTool.php` و`app/Services/AI/Tools/HiringAdvisorTool.php`.
- Dashboard HR مكرر عبر Controller/Service مختلفين.

### جاهزية المشروع للتحول إلى Filament Admin
- جاهزية الأعمال: عالية.
- جاهزية طبقة الخدمة: جيدة.
- جاهزية الصلاحيات: متوسطة إلى ضعيفة.
- الجاهزية الكلية: جيدة بعد مرحلة normalization قبل البدء بالـ UI.

## 7. تحليل الصلاحيات الحالي

| الجانب | كيف يعمل الآن |
|---|---|
| Authentication | تسجيل الدخول عبر `email/password` ثم Sanctum token في `LoginController` وخدمة `login` |
| Guard | `web` فقط في `config/auth.php`، وSanctum يستخدم `guard => ['web']` |
| Roles | مبنية حول `user_types` + Spatie Roles/Permissions |
| Permission source | السجل المركزي في `config/ai_capabilities.php`، ويزرع عبر `database/seeders/RolesAndPermissionsSeeder.php` |
| Role count | 13 أدوار bootstrap |
| Permission count | 121 صلاحية bootstrap |
| Super admin | `Gate::before` يمنح `admin` كل شيء |
| Dynamic manager permissions | `User::hasEffectivePermission()` يضيف صلاحيات لسيناريوهات PM manager |
| Route protection | `auth:sanctum` + `role:` + `permission:` بكثافة في `routes/api.php` |
| Policies | موجودة جزئيًا فقط، ومسجلة يدويًا في `app/Providers/AppServiceProvider.php` |
| Teams in Spatie | `teams => false` في `config/permission.php` |
| Tenancy/branch/company | `Not found in codebase` |
| Row-level filtering | موجود لكن متشظٍ بين Policies وServices وControllers |

### كيف يعمل الآن
- Coarse access يتم غالبًا عبر middleware على المسارات.
- الوصول على مستوى السجل يتم أحيانًا عبر policies وأحيانًا عبر services/controllers.
- `admin` لديه bypass شامل.
- منطق الفريق موجود جزئيًا في `ContractPolicy` و`SalesReservationPolicy` و`SalesTargetPolicy` وبعض خدمات المبيعات.
- AI يملك أكثر طبقة سياقية نضجًا عبر `config/ai_sections.php` و`RakizAiPolicyContextBuilder`.

### هل هو RBAC أم ABAC أم مزيج
- الأساس الحالي RBAC.
- توجد طبقات ABAC-like محدودة في `ContractPolicy`, `SalesReservationPolicy`, `SalesTargetPolicy`, وبعض خدمات المبيعات.
- أكثر طبقة سياقية ناضجة موجودة في AI.
- التقييم النهائي: `Hybrid but inconsistent`.

### الثغرات والمشاكل
- Coverage جزئي: لا توجد Policies شاملة لكل الكيانات الحساسة.
- بعض السياسات Permission-only بلا assignment scoping، خاصة في التسويق.
- `SalesProjectService` يوسّع رؤية العقود المكتملة لمستخدمي المبيعات أكثر من المتوقع.
- `LeadPolicy` لا يطبّق owner/project/team scoping.
- Spatie teams معطّل رغم أن الأعمال تعتمد على `team_id`.
- mismatch بين `accounting` و`accountant`, وبين `sales_leader` و`sales_manager`.
- Permission drift داخل AI tools: أسماء صلاحيات غير موجودة في المصدر المركزي.
- OTP/Status middleware موجودان في `Kernel`، لكن لم أجد استخدامًا واضحًا لهما على مسارات API. `Needs confirmation`.

## 8. التصميم المقترح المستقبلي للـ Admin Panel باستخدام Filament

### شكل اللوحة
- `Admin Panel`: Users, Roles, Permissions, Settings, Cities/Districts, Notifications, Audit, AI Knowledge, Ads Ops, Health/Backups.
- `Operations Panel`: PM, Inventory, Sales, Marketing, HR, Credit, Accounting, Exclusive Projects, Shared Tasks.

### هل هي Panel واحدة أم أكثر
- الأفضل `Panelين`: `admin` و`operations`.
- `admin` للنظام، الأمن، الضبط، المرجعيات، المراقبة.
- `operations` للتشغيل اليومي لكل الأقسام.

### Groups / Clusters / Resources / Pages / Widgets
- Groups: `Project Management`, `Sales`, `Marketing`, `HR`, `Credit`, `Accounting`, `Inventory`, `System`.
- Clusters: العقود والوحدات، الحجوزات والتفاوض، الخطط والحملات، التمويل والإفراغ، العمولات والعربون.
- Resources: للكيانات المستقرة CRUD-heavy.
- Pages: للـ workflows المركبة مثل readiness review, negotiation approval, claim generation review, reports.
- Widgets: KPIs القسمية الحالية الموجودة أصلًا في الخدمات.

### توزيع الأقسام

| القسم | Panel | شكل التمثيل في Filament |
|---|---|---|
| System/Admin | admin | resources + settings + audit + health |
| AI/Knowledge/Ads | admin أو admin/ops حسب الحساسية | admin pages/resources |
| PM/Contracts/Inventory | operations | grouped resources/pages/widgets |
| Sales | operations | project pages + reservation resources + KPI widgets |
| Marketing | operations | plans/leads/tasks/resources + reporting pages |
| HR | operations | employee/team/contract resources + reports |
| Credit | operations | booking/financing/title transfer resources |
| Accounting | operations | finance resources + confirmation pages |

## 9. تصميم الصلاحيات المقترح

### نموذج RBAC الأساسي
- احتفظ في البداية بعائلات الأدوار الحالية نفسها: `admin`, `project_management`, `editor`, `marketing`, `sales`, `sales_leader`, `hr`, `credit`, `accounting`, `inventory`, `default`.
- لا تُحوّل كل قيد أعمال إلى Permission منفصلة.
- استخدم RBAC فقط لـ:
  - دخول الـ panel.
  - ظهور المجموعة الرئيسية في الـ navigation.
  - السماح المبدئي بـ view/create/update/delete/approve/export/import.

### طبقة ABAC المقترحة
- تبنى فوق RBAC داخل `Policies + Query Scoping + Action Gates + UI visibility`.
- لا يوجد package سحري يمنح “Full ABAC” لهذا المشروع.

| المجال | Attributes مقترحة من الكود الحالي | مثال قاعدة |
|---|---|---|
| العقود | `user_id`, `status`, `team_id`, `contract_team`, `city_id`, `district_id` | sales يرى العقود المخصصة لفريقه أو قيادته فقط، لا كل `completed` |
| الوحدات | `contract_id`, `status`, `price`, `active reservation` | إتاحة الحجز فقط لوحدة متاحة ومشروع صالح للبيع |
| الحجوزات | `marketing_employee_id`, `status`, `reservation_type`, `purchase_mechanism`, `credit_status`, `down_payment_confirmed` | sales يؤكد/يلغي حجزه فقط، credit يفتح مراحل التمويل إذا كان booking مناسبًا |
| الأهداف | `leader_id`, `marketer_id`, `team_id`, `contract-team linkage` | القائد ينشئ هدفًا فقط لموظف من نفس الفريق وعلى مشروع مرتبط بفريقه |
| التسويق | `project_id`, `assigned_to`, `team assignments`, `lead status` | marketer يرى Leads وTasks الموكلة له أو لمشروعه |
| HR | `type`, `team_id`, `is_manager`, `is_active` | manager يرى موظفي فريقه فقط إلا إن كان HR/Admin |
| المحاسبة | `commission status`, `distribution status`, `deposit status` | approval/payment actions مرتبطة بمرحلة السجل |
| المشاريع الحصرية | `requested_by`, `approval status`, `linked contract` | الطالب يرى طلبه، والمعتمد فقط يرى الجميع |
| AI | `section`, `requested context`, `capability`, `record access` | لا يسمح بإجابات بياناتية خارج context المصرح به |

### أمثلة على attributes والقواعد
- `team_id` يجب أن يصبح السمة التنظيمية الرسمية بدل `team`.
- `status` و`approval stage` و`workflow state` يجب أن تصبح سمات تفويض لا مجرد بيانات عرض.
- `assigned_to`, `leader_id`, `marketing_employee_id` يجب أن تستخدم في row-level access بانتظام.
- `purchase_mechanism`, `credit_status`, `down_payment_confirmed` مهمة لمسارات Credit/Accounting.

### كيف تنعكس على الواجهة والبيانات والعمليات
- الواجهة: إخفاء navigation/action/button بناءً على RBAC أولًا ثم ABAC للسياق الحساس.
- البيانات: جميع `table queries`, `global search`, `relation managers`, `widgets` يجب أن تستخدم نفس scope service.
- العمليات: كل Action حساسة يجب أن تمر عبر Policy أو Decision service واحد.
- التصدير/الاستيراد: Filament يوفر Import/Export، لكن التصدير يحتاج row scoping يدويًا؛ لا تعتمد على ظهور الزر فقط.
- الـ override: اسمح به فقط لـ `admin` مع تسجيل سببي وإجباري في audit log.
- الاستثناء الأعلى: حافظ على `super-admin` bypass، لكن مع audit واضح للعمليات المالية/الاعتمادية.

## 10. الحزم والمكتبات المقترحة

| الحزمة | التصنيف | الغرض | لماذا تناسب هذا ERP | دعمها للصلاحيات | مخاطر/ملاحظات |
|---|---|---|---|---|---|
| `filament/filament` | أساسية | Admin Panels / Resources / Pages / Widgets | الأنسب لـ internal ERP admin | لا يدعم RBAC/ABAC مباشرة | Filament 5 هو current line وفق سياسة الدعم الرسمية، لكن Packagist يشير إلى متطلب PHP `^8.2.8`; المشروع يثبت فقط `^8.2` لذا patch level `Needs confirmation` |
| `spatie/laravel-permission` | أساسية | RBAC baseline | موجود أصلًا ومندمج مع `User::HasRoles` | RBAC فقط | لا تستخدمه كبديل عن ABAC؛ وميزة teams معطّلة حاليًا |
| `spatie/laravel-activitylog` | موصى بها بقوة | audit/activity log | حاسم للعمولات، العربون، الاعتمادات، overrides | لا يدعم ABAC مباشرة لكنه يدعم auditability | أحدث major `5.0.0` يتطلب PHP `^8.4`; على الوضع الحالي استخدم latest compatible 4.12.x أو أجّل الترقية |
| `spatie/laravel-medialibrary` + `filament/spatie-laravel-media-library-plugin` | موصى بها | إدارة مرفقات ووسائط | المشروع يحتوي فعليًا على `ProjectMedia` وملفات موظفين وPDFs ومرفقات عقود | لا يدعم الصلاحيات مباشرة | يلزم تصميم migration path من الحقول الحالية `*_path` و`*_url` |
| `spatie/laravel-settings` + `filament/spatie-laravel-settings-plugin` | موصى بها | typed settings قابلة للإدارة | مناسبة لـ marketing settings, conversion rate, thresholds, system knobs | لا يدعم الصلاحيات مباشرة | فرّق بين settings عامة وrecords تشغيلية مثل `MarketingSetting` |
| `maatwebsite/excel` | أساسية موجودة | import/export متقدم | موجود أصلًا ويخدم التصدير والتقارير | لا يدعم الصلاحيات مباشرة | لا حاجة Package إضافية مبدئيًا لأن Filament لديه Import/Export actions |
| `flowframe/laravel-trend` | موصى بها | trend aggregation للـ widgets والتقارير | مناسب لـ KPIs الحالية في sales/marketing/accounting/credit | لا يدعم الصلاحيات مباشرة | استخدمه فوق queries scoped فقط |
| `spatie/laravel-health` | اختيارية مفيدة | health checks | مناسب لنظام داخلي حساس تشغيليًا | لا يدعم الصلاحيات مباشرة | متوافق من حيث Laravel 12/PHP 8.2 حسب Packagist |
| `spatie/laravel-backup` | اختيارية | backups | مناسب تشغيليًا لكن ليس blocker لبدء Filament | لا يدعم الصلاحيات مباشرة | أحدث line تتطلب PHP `^8.4` و`illuminate/console ^12.40`; غير متوافق مع `v12.37.0` المقفول حاليًا |
| `bezhansalleh/filament-shield` | اختيارية | مساعدة RBAC داخل Filament | مفيد فقط إذا أردت توليد/manage permissions على مستوى الموارد | RBAC helper فقط | لا يعطي ABAC، وقد يزيد فوضى أسماء الصلاحيات إن استُخدم قبل توحيد naming |

### الأساسية
- `filament/filament`
- `spatie/laravel-permission`
- الاستمرار على `maatwebsite/excel` وطبقة PDF الحالية

### الموصى بها
- `spatie/laravel-activitylog`
- `spatie/laravel-medialibrary`
- `spatie/laravel-settings`
- `flowframe/laravel-trend`

### الاختيارية
- `spatie/laravel-health`
- `spatie/laravel-backup`
- `bezhansalleh/filament-shield`

### سبب الاختيار
- لأنها تدعم متطلبات ERP داخلية: dashboards, auditability, document handling, settings governance, operational reporting.

## 11. خريطة ربط بين أقسام ERP الحالية ومكونات Filament المستقبلية

| قسم ERP | Panel | Navigation Group/Cluster | مكونات Filament المقترحة | نموذج الصلاحيات |
|---|---|---|---|---|
| العقود والوحدات | ops | Project Management / Contracts | Resources: Contracts, Units, Second Parties. Pages: readiness review, team assignment. Widgets: pending/ready gaps | RBAC + ABAC على team/status/ownership |
| أقسام اللوحات/التصوير/المونتاج | ops | Project Delivery | Relation Managers أو Resources منفصلة + approval actions | RBAC + ABAC على المرحلة والحالة |
| المبيعات | ops | Sales | Resources: Reservations, Targets, Waiting List. Pages: Projects, Negotiations, Payment Plans. Widgets: KPIs | RBAC + ABAC على team/leader/owner/status |
| التسويق | ops | Marketing | Resources: Marketing Projects, Plans, Leads, Tasks, Settings, Media. Pages: reports, budget simulation | RBAC + ABAC على project assignment/lead assignee |
| HR | ops | HR | Resources: Users, Teams, Employee Contracts, Warnings, Reviews. Pages: reports | RBAC + ABAC على team/department/manager |
| Inventory | ops | Inventory | Pages/Widgets أكثر من CRUD: coverage, agency overview, inventory dashboard | RBAC + ABAC على scope الجغرافي/الفرق |
| Credit | ops | Credit | Resources: Bookings, Financing Tracker, Title Transfers, Claim Files | RBAC + ABAC على booking stage/purchase mechanism |
| Accounting | ops | Accounting | Resources: Commissions, Distributions, Deposits, Salary Distributions, Confirmations | RBAC + ABAC على status/distribution state |
| AI/Knowledge/Calls | admin | AI & Automation | Pages/Resources للمعرفة، scripts، calls، audit | Admin-only أو role-limited مع context policies |
| Ads/Attribution | admin أو ops marketing | Ads & Insights | Pages للحسابات والحملات والنتائج | RBAC + ABAC على marketing/admin فقط |
| System/Admin | admin | System | Users, Roles, Permissions, Cities, Districts, Notifications, Settings, Audit, Health | RBAC أساسي + admin overrides logged |

## 12. المخاطر والتحديات
- تسريب بيانات محتمل إذا نُقلت الموارد إلى Filament قبل توحيد row-level scoping.
- تضارب naming الحالي قد ينتج عنه Permissions غير عاملة أو Buttons ظاهرة بالخطأ.
- ازدواج route/middleware registration قد يسبب سلوكًا غير متوقع في bootstrap الجديد.
- `team` مقابل `team_id` سيكسر أي ABAC نظيف إذا لم يُحسم مبكرًا.
- عدم وجود Soft Deletes حقيقية للمستخدمين سيصطدم مع شاشات HR الإدارية.
- بعض الـ flows الحالية ناقصة، ما يعني أن Filament لا يجب أن يفترض اكتمالها.
- حزم التوصية ليست كلها متوافقة فورًا مع المنصة المقفلة الحالية؛ بعضُها يحتاج ترقية PHP/Laravel patch.
- هذا التقرير مبني على static analysis؛ لا يتضمن تشغيلًا وظيفيًا أو اختبارًا runtime.

## 13. خارطة طريق تحليلية للتنفيذ لاحقًا

### Phase 1
- توحيد نموذج الصلاحيات والتسمية قبل أي UI.
- تثبيت permission dictionary واحد.
- إزالة drift.
- حسم `team_id` كمصدر رسمي.
- مراجعة duplicate routing.

### Phase 2
- تأسيس Filament foundation.
- ابدأ بـ `admin panel` وموارد النظام: users/roles/permissions/settings/cities/districts/notifications/audit/media.

### Phase 3
- إطلاق `operations panel` تدريجيًا حسب أولوية الأعمال: Contracts/PM ثم Sales ثم Marketing ثم Credit/Accounting ثم HR/Inventory.
- كل مرحلة يجب أن تعتمد على policy coverage كاملة وscoped widgets/export actions.

## 14. القرار النهائي والتوصية المعمارية
التوصية النهائية: اعتمد Filament كطبقة إدارة جديدة فوق الـ API الحالي، وليس كإعادة كتابة لمنطق الأعمال. ابدأ بـ Filament 5 ما لم يمنعك شرط بيئة PHP patch أو توافق package حرج؛ واجعل `spatie/laravel-permission` هو RBAC baseline الحالي، ثم ابنِ ABAC الحقيقي داخل Policies وquery scoping وFilament action/widget visibility. لا تبدأ ببناء الموارد التشغيلية قبل توحيد naming والصلاحيات الحالية، لأن المخاطرة الأكبر اليوم هي اتساق الوصول إلى البيانات، لا نقص أدوات الواجهة.

# 2) English Report

## 1. Executive Summary
This repository is a substantial Laravel ERP codebase for a real-estate business, not a thin API. The codebase clearly contains project/contract management, units/inventory, sales, marketing, HR, credit, accounting, exclusive projects, AI/knowledge/calls, ads/attribution, and shared operational services. The primary evidence is in `routes/api.php`, `app/Services`, `app/Models`, and `database/migrations`.

Filament is a strong fit for the future administration layer, but it should sit on top of the existing API/service layer rather than replace it. The current authorization model is not purely RBAC and not full ABAC; it is a hybrid with a Spatie-based RBAC baseline, partial row-level checks in policies/services, and a more mature context-aware model only in the AI module. The main architectural blockers are authorization normalization, naming consistency, and a few structural inconsistencies, not the lack of an admin UI framework.

## 2. Current System Overview
The application is an API-first Laravel monolith with a service-heavy architecture. Business logic is concentrated in services, controllers are mostly orchestration layers, and the ads module is the clearest example of a layered/ports-and-adapters design through `app/Providers/AdsServiceProvider.php`, `app/Domain/Ads`, and `app/Application/Ads`.

Primary evidence set: `composer.json`, `composer.lock`, `routes/api.php`, `routes/web.php`, `config/auth.php`, `config/permission.php`, `config/user_types.php`, `config/ai_capabilities.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `app/Http/Kernel.php`.

There is no real admin panel today. Web views exist, but they are mainly test pages, PDF templates, and limited support pages. Filament: `Not found in codebase`.

## 3. Detected Technologies and Versions

| Item | Detected from repository |
|---|---|
| PHP | `^8.2` in `composer.json` |
| Laravel | `laravel/framework ^12.0`, locked to `v12.37.0` |
| API auth | `laravel/sanctum v4.2.1` |
| Roles/permissions | `spatie/laravel-permission 6.24.0` |
| Realtime | `laravel/reverb v1.6.3`, `laravel-echo`, `pusher-js` |
| AI | `openai-php/laravel v0.18.0` |
| Export/PDF | `maatwebsite/excel 3.1.67`, `barryvdh/laravel-dompdf v3.1.1`, `mpdf` |
| Frontend tooling | `vite ^7.0.7`, `tailwindcss ^4.0.0` |
| Filament | `Not found in codebase` |
| Tenancy package | `Not found in codebase` |
| General audit package | `Not found in codebase`, except AI-specific audit artifacts |

## 4. Actual ERP Modules/Sections Found in the Codebase

| Section | Code evidence | Notes |
|---|---|---|
| Project/contract management | `ContractController`, `ContractService`, `Contract`, `ContractUnit`, `SecondPartyData` | Core real-estate backbone |
| Sales | `SalesProjectController`, `SalesReservationController`, `SalesTargetController`, `SalesDashboardService` | Reservations, targets, attendance, waiting list |
| Marketing | `MarketingProjectController`, `LeadController`, `MarketingReportController`, `MarketingDashboardService` | Plans, campaigns, leads, tasks, reporting |
| HR | `HrUserController`, `HrTeamController`, `EmployeeContractController`, `HrDashboardService` | Employees, teams, warnings, contracts |
| Inventory | `inventory/*` routes + inventory services | Built on contracts/units |
| Credit | `CreditBookingController`, `CreditFinancingController`, `TitleTransferController`, `ClaimFileController` | Financing and post-sale operations |
| Accounting | `AccountingCommissionController`, `AccountingDepositController`, `AccountingSalaryController` | Commissions, deposits, salaries |
| Exclusive projects | `ExclusiveProjectController`, `ExclusiveProjectRequest` | Request/approve/contract/export |
| AI | `AiV2Controller`, `AssistantChatController`, `AiCallController`, `AssistantKnowledgeController` | Assistant, RAG, AI calls, AI audit |
| Ads | `AdsInsightsController`, `AdsLeadsController`, `AdsOutcomeController` | Attribution and marketing analytics |
| Shared ops | `NotificationController`, `ChatController`, `MyTasksController`, `CityController`, `DistrictController` | Notifications, chat, tasks, master data |

## 5. Per-section analysis:

| Section | business purpose | key entities | current workflows | what should appear in the future Filament admin for that section |
|---|---|---|---|---|
| Project Management / Contracts | Register, approve, and prepare real-estate projects for marketing and sales | `Contract`, `ContractInfo`, `ContractUnit`, `SecondPartyData`, department trackers, `Team` | Contract CRUD, admin approval, PM readiness, CSV unit upload, second-party documents, boards/photography/montage processing, team linkage | Resources for contracts/units/second parties, readiness review pages, assignment pages, PM widgets |
| Sales | Turn ready projects into reservations and manage team performance | `SalesReservation`, `SalesTarget`, `SalesAttendanceSchedule`, `SalesProjectAssignment`, `SalesWaitingList`, `NegotiationApproval`, installments | Project browsing, reservation create/confirm/cancel, voucher, waiting list conversion, negotiation approval, targets, attendance, payment plans | Reservation resources, project pages, negotiation pages, targets/attendance resources, KPI widgets |
| Marketing | Operate marketing projects, plans, leads, and reporting | `MarketingProject`, plan models, `MarketingCampaign`, `MarketingTask`, `Lead`, `ProjectMedia`, `MarketingSetting` | Developer/employee plans, budget calculation, expected bookings, team assignment, leads, reports, exports | Resources for projects/plans/leads/tasks/settings/media, reporting pages, budget widgets |
| HR | Manage people, teams, HR contracts, warnings, and performance | `User`, `Team`, `EmployeeContract`, `EmployeeWarning`, `ManagerEmployeeReview`, `Task` | Employee CRUD, files, activation, contracts PDF, warnings, teams, reports | Resources for employees/teams/contracts/warnings, HR report pages, HR widgets |
| Inventory | Operational inventory view of contracts, units, and coverage | `Contract`, `ContractUnit`, `SecondPartyData` | Locations, agency overview, inventory dashboard | Inventory pages/widgets rather than CRUD-heavy resources |
| Credit | Process reservations after sale through financing/title transfer/claim files | `SalesReservation`, `CreditFinancingTracker`, `TitleTransfer`, `ClaimFile` | Booking tabs, contact logs, financing stages, title transfer, claim file generation/download | Credit booking resources, financing/title transfer resources, claim-file pages/widgets |
| Accounting | Control commissions, deposits, salary distributions, and confirmations | `Commission`, `CommissionDistribution`, `Deposit`, `AccountingSalaryDistribution` | Sold-unit views, manual commission creation, distribution approval/rejection/payment, deposit confirm/refund, salaries | Accounting resources for commissions/distributions/deposits/salaries, finance widgets |
| Exclusive Projects | Handle exclusive project requests and convert approved ones into contracts | `ExclusiveProjectRequest` | Request, approve/reject, complete contract, export PDF | Resource + approval/export pages |
| AI / Knowledge / Calls | Internal AI assistant, knowledge base, call workflows, AI auditing | AI document/call/assistant models | Assistant chat, RAG ingestion, knowledge CRUD, call scripts/calls/analytics | Admin pages/resources for knowledge, prompts, calls, AI audit |
| Ads / Attribution | Sync campaigns, ingest insights, export leads, store outcomes | Ads infra models/services | Accounts, campaigns, insights, lead export, sync, outcomes | Marketing/admin analytics pages, campaign widgets |
| Shared Operations | Common operational tooling across departments | notifications, chat, tasks, cities, districts | Notifications, internal tasks, chat, reference data | System/admin resources for master data, notifications, task center |

Operational evidence highlights:
- Contract readiness is explicitly encoded in `app/Models/Contract.php` and enforced during PM transition in `app/Services/Contract/ContractService.php`.
- Sales project visibility is broader than team assignment suggests in `app/Services/Sales/SalesProjectService.php`.
- Lead conversion in `app/Http/Controllers/Marketing/LeadController.php` only flips status to `converted`; downstream CRM/reservation creation is `Not found in codebase`.

## 6. Current Architecture Assessment

### strengths
- Strong real-estate ERP domain coverage.
- Clear service layer across most modules.
- Existing dashboards and exports in several departments.
- Event/listener/job infrastructure already exists.
- Ads module demonstrates better layering than the rest of the monolith.

### weaknesses
- Potential duplicate route registration: `bootstrap/app.php` uses `withRouting()`, while `app/Providers/AppServiceProvider.php` also maps API and web routes. Runtime impact `Needs confirmation`, but this is a concrete design smell.
- Middleware aliases are defined in both bootstrap and `Kernel`.
- `bootstrap/app.php` references middleware classes not found in `app/Http/Middleware`: `AdminMiddleware`, `ProjectManagementMiddleware`, `EnsureSalesLeader`, `EditorMiddleware`. `Needs confirmation`.
- `routes/api.php` is oversized and heavily coupled.
- Legacy `team` string and newer `team_id` FK coexist.
- User soft-delete behavior is assumed in `app/Services/registartion/register.php`, but not supported by `database/migrations/0001_01_01_000000_create_users_table.php`.
- Some workflows are incomplete or partially wired: `LeadController::convert`, `TeamManagementController::assignCampaign`, `include_public_status_contracts` unused in services.
- Naming drift is significant: `accounting` vs `accountant`, `sales_leader` vs `sales_manager`, `registartion` typo namespace.

### complexity
- Overall complexity is `medium-high`.
- The difficulty comes from cross-module workflow coupling, not just model count.

### organization/naming issues
- `team` vs `team_id`.
- `scopeMarketers()` on `User` returns `sales` users, which is misleading.
- AI tools reference permissions not present in the central permission dictionary, including `app/Services/AI/Tools/SearchRecordsTool.php`, `app/Services/AI/Tools/GetLeadSummaryTool.php`, and `app/Services/AI/Tools/HiringAdvisorTool.php`.
- HR dashboard logic is duplicated.

### readiness for Filament adoption
- Domain readiness: high.
- Service-layer readiness: good.
- Authorization readiness: moderate-to-weak.
- Overall: Filament-ready after normalization, not before.

## 7. Current Authorization Assessment

| Aspect | Current state |
|---|---|
| Authentication | Email/password login, then Sanctum token issuance |
| Guard | Only `web` is defined in `config/auth.php`; API relies on Sanctum bearer tokens |
| Role model | `user_types` + Spatie roles/permissions |
| Permission source | Central dictionary in `config/ai_capabilities.php` |
| Bootstrap roles | 13 |
| Bootstrap permissions | 121 |
| Super-admin handling | `Gate::before` grants `admin` full access |
| Dynamic permissions | `User::hasEffectivePermission()` adds PM-manager-like behavior |
| Route protection | Widespread `auth:sanctum`, `role`, `permission` middleware |
| Policy coverage | Partial, manually registered in `app/Providers/AppServiceProvider.php` |
| Spatie teams | Disabled |
| Tenant/branch/company model | `Not found in codebase` |
| Row-level restrictions | Present, but inconsistent and decentralized |

### how it works now
- Coarse access is mostly route-driven.
- Record-level access is sometimes policy-driven and sometimes service/controller-driven.
- `admin` bypass is global.
- Sales/PM team rules are partially encoded in policies and services.
- AI has the most context-aware authorization via `config/ai_sections.php` and `RakizAiPolicyContextBuilder`.

### whether it is RBAC, ABAC-like, hybrid, or inconsistent
- Current model: `hybrid, partially ABAC-like, but inconsistent`.

### gaps and risks
- Policy coverage is incomplete.
- Marketing policies are mostly permission-only, not assignment-aware.
- Sales project visibility is broader than the assignment model implies.
- `LeadPolicy` does not enforce assignment/team/project scoping.
- Spatie team support is disabled even though the domain relies on `team_id`.
- Legacy role names remain in financial policies.
- AI permission naming drift indicates the current permission dictionary is not the single source of truth.
- OTP/status middleware exists in the codebase but active route usage was not proven. `Needs confirmation`.

## 8. Proposed Future Filament Admin Architecture

### panel structure
- `Admin Panel`: users, roles, permissions, settings, cities/districts, notifications, AI knowledge, ads ops, health, backups, audit.
- `Operations Panel`: PM, inventory, sales, marketing, HR, credit, accounting, exclusive projects, shared tasks.

### one panel vs multiple panels
- Prefer 2 panels: `admin` and `operations`.
- `admin` for system/security/configuration/master data/oversight.
- `operations` for daily departmental execution.

### navigation groups/clusters/resources/pages/widgets
- Groups: `Project Management`, `Sales`, `Marketing`, `HR`, `Credit`, `Accounting`, `Inventory`, `System`.
- Clusters: contracts/units, reservations/negotiations, marketing plans/campaigns, financing/title transfer, commissions/deposits.
- Resources: stable CRUD-centric records.
- Pages: compound workflows and review screens.
- Widgets: KPI surfaces already backed by current services.

### section distribution

| Section | Panel | Filament shape |
|---|---|---|
| System/Admin | admin | resources + settings + audit + health |
| AI/Knowledge/Ads | admin or split as needed | admin pages/resources |
| PM/Contracts/Inventory | operations | grouped resources/pages/widgets |
| Sales | operations | project pages + reservation resources + KPI widgets |
| Marketing | operations | plans/leads/tasks/resources + reporting pages |
| HR | operations | employee/team/contract resources + reports |
| Credit | operations | booking/financing/title transfer resources |
| Accounting | operations | finance resources + confirmation pages |

## 9. Proposed Authorization Architecture

### RBAC foundation
- Keep the current role families initially: `admin`, `project_management`, `editor`, `marketing`, `sales`, `sales_leader`, `hr`, `credit`, `accounting`, `inventory`, `default`.
- Use RBAC only for panel access, navigation visibility, and coarse CRUD/approve/export/import abilities.
- Do not explode business logic into hundreds of fine-grained permissions.

### ABAC layer
- Implement ABAC in `policies + scoped queries + action gates + Filament UI visibility`.
- There is no single package that will deliver full ABAC for this repository.

| Domain | Candidate attributes grounded in codebase | Example rule |
|---|---|---|
| Contracts | `user_id`, `status`, `team_id`, `contract_team`, `city_id`, `district_id` | sales sees only assigned/team-scoped contracts, not every completed contract |
| Units | `contract_id`, `status`, active reservation, pricing | reserve only when unit and project state permit it |
| Reservations | `marketing_employee_id`, `status`, `reservation_type`, `purchase_mechanism`, `credit_status`, `down_payment_confirmed` | sales confirms/cancels own reservation; credit acts only on eligible bookings |
| Targets | `leader_id`, `marketer_id`, `team_id`, PM team linkage | leader creates targets only for same-team marketers on allowed projects |
| Marketing | `project_id`, `assigned_to`, team assignment, lead status | marketers see only assigned leads/tasks or project-scoped records |
| HR | `type`, `team_id`, `is_manager`, `is_active` | managers see their team; HR/admin see wider scope |
| Accounting | commission/deposit/distribution status | approval/payment actions limited by workflow stage |
| Exclusive projects | requester, approval status, linked contract | requester sees own request; approver sees all |
| AI | section, requested context, capability, record access | deny contextual data access outside authorized scope |

### example attributes and rules
- `team_id` should become the canonical organizational attribute instead of legacy `team`.
- `status`, `approval stage`, and `workflow state` should become authorization attributes, not just display fields.
- `assigned_to`, `leader_id`, and `marketing_employee_id` should be consistently used for row-level enforcement.
- `purchase_mechanism`, `credit_status`, and `down_payment_confirmed` should drive credit/accounting decision access.

### UI/data/action enforcement strategy
- Panel access: coarse RBAC.
- Navigation visibility: coarse RBAC plus context-aware hide/show where needed.
- Resource queries: central scoped query logic reused in Filament `getEloquentQuery()`, relation managers, widgets, and global search.
- Record actions: policy-backed checks for approve/reject/confirm/pay/export/import.
- Bulk actions/exports: same scoped query rules as tables.
- Widgets: aggregated only from the same scoped datasets.
- Super-admin: keep bypass, but log all sensitive overrides.
- Auditability: approvals, payment confirmations, refunds, title transfer, claim-file generation, and role/permission overrides should all be audit logged.

## 10. Recommended Libraries and Packages

| Package | Priority | Purpose | Why it fits this ERP | RBAC/ABAC relevance | Caveats |
|---|---|---|---|---|---|
| `filament/filament` | Essential | Admin panel framework | Best fit for internal ERP operations UI | Neither directly | Filament 5 is the current line per official support policy. Packagist indicates PHP `^8.2.8`; repo only proves `^8.2`, so exact runtime patch level `Needs confirmation` |
| `spatie/laravel-permission` | Essential | RBAC baseline | Already installed and integrated | RBAC only | Not a substitute for ABAC; teams are currently disabled |
| `spatie/laravel-activitylog` | Strongly recommended | Activity/audit log | Critical for finance, approvals, and overrides | Neither directly, but essential for auditable ABAC | Latest major `5.0.0` requires PHP `^8.4`; use latest compatible 4.12.x line until platform upgrade |
| `spatie/laravel-medialibrary` + `filament/spatie-laravel-media-library-plugin` | Recommended | Media/document management | Strong fit for project media, employee files, claim files, contract documents | Neither directly | Requires migration strategy from current path/url columns |
| `spatie/laravel-settings` + `filament/spatie-laravel-settings-plugin` | Recommended | Typed admin-managed settings | Good fit for marketing settings, conversion rates, operational thresholds | Neither directly | Must distinguish global settings from operational records |
| `maatwebsite/excel` | Essential, already present | Advanced import/export | Already installed and useful for heavy ERP exports | Neither directly | No extra import/export package is required initially because Filament provides import/export actions |
| `flowframe/laravel-trend` | Recommended | Trend aggregation | Good fit for sales/marketing/accounting/credit widgets | Neither directly | Use only on scoped queries |
| `spatie/laravel-health` | Optional but useful | Health checks | Operationally valuable for an internal ERP | Neither directly | Appears compatible with Laravel 12/PHP 8.2 |
| `spatie/laravel-backup` | Optional | Application backups | Good operational package | Neither directly | Current latest line requires PHP `^8.4` and `illuminate/console ^12.40`; not compatible with locked `v12.37.0` today |
| `bezhansalleh/filament-shield` | Optional | Filament-side permission helper | Useful only if you want resource/page/widget permission management in Filament | RBAC helper only | Not an ABAC engine; can worsen permission sprawl if adopted before naming cleanup |

### essential
- `filament/filament`
- `spatie/laravel-permission`
- keep `maatwebsite/excel` and the current PDF stack

### recommended
- `spatie/laravel-activitylog`
- `spatie/laravel-medialibrary`
- `spatie/laravel-settings`
- `flowframe/laravel-trend`

### optional
- `spatie/laravel-health`
- `spatie/laravel-backup`
- `bezhansalleh/filament-shield`

### rationale
- They align with internal ERP needs: dashboards, auditability, document handling, settings governance, and operational reporting.

## 11. ERP-to-Filament Mapping Matrix

| ERP section | Panel | Navigation group/cluster | Proposed Filament components | Authorization pattern |
|---|---|---|---|---|
| Contracts & units | ops | Project Management / Contracts | Contract, Unit, Second Party resources; readiness pages; PM widgets | RBAC + ABAC on team/status/ownership |
| Boards/photography/montage | ops | Project Delivery | Department resources or relation managers; approval actions | RBAC + ABAC on workflow state |
| Sales | ops | Sales | Reservation/Target/Waiting List resources; project and negotiation pages; KPI widgets | RBAC + ABAC on team/leader/owner/status |
| Marketing | ops | Marketing | Project/Plan/Lead/Task/Setting/Media resources; report pages | RBAC + ABAC on assignment/project scope |
| HR | ops | HR | User/Team/Contract/Warning/Review resources; report pages | RBAC + ABAC on department/team/manager |
| Inventory | ops | Inventory | Dashboard-style pages and widgets | RBAC + ABAC on geography and operational scope |
| Credit | ops | Credit | Booking/Financing/Title Transfer/Claim File resources | RBAC + ABAC on booking stage/mechanism |
| Accounting | ops | Accounting | Commission/Distribution/Deposit/Salary resources | RBAC + ABAC on financial workflow state |
| Exclusive projects | ops | Project Development | Request resource; review/export pages | RBAC + ABAC on requester/approval |
| AI/Knowledge/Calls | admin | AI & Automation | Knowledge, prompts, calls, audit pages/resources | Admin-only or tightly role-limited plus context policies |
| Ads/Attribution | admin or ops-marketing | Ads & Insights | Accounts/campaigns/insights/outcomes pages | RBAC + limited ABAC |
| System | admin | System | Users, roles, permissions, settings, reference data, notifications, audit, health | RBAC baseline + logged admin overrides |

## 12. Risks and Challenges
- Data leakage risk if Filament resources are introduced before row-level access is normalized.
- Permission-name drift can cause broken access checks and misleading UI visibility.
- Duplicate routing/middleware registration is a structural risk.
- `team` vs `team_id` will undermine clean ABAC if not resolved first.
- Missing real soft-delete support for users will conflict with HR admin expectations.
- Some current flows are incomplete, so the future admin must not assume they are fully implemented.
- Not every recommended package is immediately compatible with the currently locked PHP/Laravel baseline.
- This report is based on static analysis only; no runtime behavior was validated.

## 13. Phased Implementation Roadmap (analysis-level only, no code)

### Phase 1
- Authorization and naming normalization.
- Consolidate the permission dictionary.
- Remove naming drift.
- Make `team_id` canonical.
- Resolve duplicate routing/bootstrap concerns.

### Phase 2
- Filament foundation.
- Start with the `admin` panel and low-risk/high-value system areas: users, roles, permissions, settings, master data, notifications, audit, media.

### Phase 3
- Roll out the `operations` panel by business priority: contracts/PM, then sales, then marketing, then credit/accounting, then HR/inventory.
- Each rollout should require complete policy coverage and scoped widgets/export actions first.

## 14. Final Architectural Recommendation
Adopt Filament as a new administration layer on top of the existing Laravel API and service layer, not as a rewrite of business logic. Keep `spatie/laravel-permission` as the RBAC foundation, then implement real ABAC through policies, scoped queries, and Filament UI/action enforcement. Start with system/admin capabilities, then move into operational modules only after authorization normalization. The biggest current architectural risk is inconsistent access control, not the absence of an admin framework.
