# مرجع: الواجهة ← صلاحيات الـ API المطلوبة (لتجنّب 403)

**الهدف:** عند منح مستخدم وصولاً لواجهة معيّنة، امنحه في الباكند **كل** الصلاحيات المدرجة لهذه الواجهة حتى لا يحصل 403 على أي من الـ APIs المستدعاة.

**ملاحظة:** المسارات كما في الباكند (بدون بادئة `/api` — الـ SPA تضيفها حسب baseURL). التحقق الفعلي قد يكون عبر `permission` على الـ route أو عبر Policy في الـ Controller.

---

## تطابق المسارات (الوثيقة مقابل الباكند)

| الواجهة | مسار في الوثيقة | المسار الفعلي في الباكند |
|---------|------------------|---------------------------|
| AiAssistantView | GET /ai/conversations | GET /ai/conversations |
| KnowledgeManagementView | GET /ai/assistant/knowledge | GET /ai/knowledge |
| ChatView (قائمة مستخدمين) | GET /hr/users | GET /chat/list_user أو GET /hr/users (حسب الفرونت) |
| ContractsView (تحديث حالة) | PATCH /admin/contracts/adminUpdateStatus/:id | PATCH /admin/contracts/adminUpdateStatus/{id} (داخل prefix admin) أو PATCH contracts/update-status/{id} (داخل project_management) |
| CommissionDepositsView | GET /sales/commissions, GET /sales/deposits | GET /sales/sold-units، GET /sales/deposits/management، GET /sales/deposits/follow-up، GET /sales/analytics/* |
| TeamManagementView | GET /hr/teams/:teamId/members | GET /hr/teams/{id}/members |

---

## 1. واجهات مستقلة (صفحة واحدة = مجموعة APIs)

### LoginView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| POST | /login | لا (عام) |
| GET | /user | auth:sanctum |

**الصلاحيات الدنيا للواجهة:** لا يلزم صلاحية (مصادقة فقط بعد الدخول).

---

### DashboardView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /user | auth:sanctum |
| GET | /contracts/admin-index | role:project_management\|admin + **contracts.view_all** |
| GET | /editor/contracts/index | role:editor\|admin + **contracts.view_all** |
| GET | /contracts/index | auth + ContractPolicy (عقود المستخدم أو حسب الدور) |

**الصلاحيات الدنيا للواجهة:** حسب التاب — إما **contracts.view_all** (مع دور project_management أو editor أو admin) أو عقود المستخدم فقط (contracts.view عبر السياسة).

---

### AiAssistantView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /ai/conversations | auth + **use-ai-assistant** (التحقق قد يكون في الـ Controller) |
| GET | /ai/sections | auth |
| DELETE | /ai/conversations/{sessionId} | auth |
| POST | /ai/chat | auth |
| POST | /ai/ask | auth |

**الصلاحيات الدنيا للواجهة:** **use-ai-assistant** (والمسارات تحت throttle:ai-assistant).

---

### ChatView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /chat/conversations | auth:sanctum |
| GET | /chat/conversations/{userId} | auth |
| PATCH | /chat/conversations/{conversationId}/read | auth |
| GET | /chat/conversations/{conversationId}/messages | auth |
| POST | /chat/conversations/{conversationId}/messages | auth |
| DELETE | /chat/messages/{messageId} | auth |
| GET | /chat/list_user | auth (استدعاء list_employees) |
| GET | /hr/users | role:hr\|admin أو **hr.users.view** (للمسوقين) |

**الصلاحيات الدنيا للواجهة:** مصادق. إن استُخدم GET /hr/users لملء قائمة المستخدمين: دور hr أو admin أو **hr.users.view**.

---

### KnowledgeManagementView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /ai/knowledge | role:admin |
| POST | /ai/knowledge | role:admin |
| PUT | /ai/knowledge/{id} | role:admin |
| DELETE | /ai/knowledge/{id} | role:admin |

**الصلاحيات الدنيا للواجهة:** دور **admin** فقط (لا صلاحية منفصلة على الـ route).

---

### ContractsView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /user | auth |
| GET | /contracts/admin-index | role:project_management\|admin + **contracts.view_all** |
| GET | /editor/contracts/index | role:editor\|admin + **contracts.view_all** |
| GET | /contracts/index | auth + سياسة |
| GET | /editor/contracts/show/{id} أو /contracts/show/{id} | **contracts.view** أو سياسة view |
| PATCH | /contracts/update-status/{id} | role:project_management\|admin + **contracts.approve** |
| PATCH | /admin/contracts/adminUpdateStatus/{id} | role:admin + **contracts.approve** |

**الصلاحيات الدنيا للواجهة:** **contracts.view_all**، **contracts.view**، **contracts.approve** (حسب الإجراء)، مع دور project_management أو editor أو admin.

---

### ContractFormView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /contracts/show/{id} | ContractPolicy::view |
| POST | /contracts/store/info/{id} | auth + ملكية العقد أو صلاحية |

**الصلاحيات الدنيا للواجهة:** **contracts.view** (أو view_all)؛ إنشاء/تحديث معلومات العقد حسب السياسة.

---

### DevelopersView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /developers | auth + **contracts.view** أو **contracts.view_all** (ContractPolicy::viewAny) |

**الصلاحيات الدنيا للواجهة:** **contracts.view** أو **contracts.view_all**.

---

### DeveloperDetailView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /developers/{id} | auth + **contracts.view** أو **contracts.view_all** |
| GET | /second-party-data/contracts-by-email | role:project_management\|admin + **second_party.view** |

**الصلاحيات الدنيا للواجهة:** **contracts.view** (أو view_all)، **second_party.view** (لـ contracts-by-email) مع دور project_management أو admin.

---

### DeveloperProjectUnitsView (وحدات مشروع المطور — محاسبة/ائتمان)
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /accounting/claim-files/sold-units | role:accounting\|admin + **accounting.claim_files.view** |
| GET | /accounting/claim-files/candidates | **accounting.claim_files.view** |
| GET | /accounting/claim-files/download-for-reservation/{id} | **accounting.claim_files.manage** |
| GET | /developers/{id} | **contracts.view** أو **contracts.view_all** |
| POST | /credit/claim-files/combined أو generate-bulk | role:credit\|admin + **credit.claim_files.manage** |

**الصلاحيات الدنيا للواجهة:** إن كانت الواجهة تحت المحاسبة: **accounting.claim_files.view**، **accounting.claim_files.manage**، **contracts.view** (للمطور). إن كانت تحت الائتمان: **credit.claim_files.view**، **credit.claim_files.manage**، **contracts.view**.

---

### NotificationsView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /notifications | auth |
| GET | /accounting/notifications | role:accounting\|admin + **accounting.dashboard.view** |
| GET | /admin/notifications | role:admin + **notifications.view** |
| PATCH | /notifications/{id}/read | auth |
| PATCH | /notifications/mark-all-read | auth |
| POST | /accounting/notifications/{id}/read | **accounting.dashboard.view** |
| POST | /accounting/notifications/read-all | **accounting.dashboard.view** |

**الصلاحيات الدنيا للواجهة:** مصادق. للإشعارات العامة: **notifications.view**. لإشعارات المحاسبة: **accounting.dashboard.view** + دور accounting أو admin.

---

### ExclusiveProjectView (طلب المشروع الحصري)
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /developers | **contracts.view** أو **contracts.view_all** (Policy) |
| GET | /second-party-data/second-parties | role:project_management\|admin + **second_party.view** |
| POST | /exclusive-projects | auth (أي مصادق) |
| GET | /exclusive-projects | auth |
| GET | /exclusive-projects/{id} | auth |

**الصلاحيات الدنيا للواجهة:** **contracts.view** (أو view_all) لملء قائمة المطورين؛ **second_party.view** إن استُخدمت second-parties؛ صلاحيات exclusive_projects.* حسب الإجراء (request، approve، contract.complete، export).

---

### MyRequestsView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /contracts/index | auth + سياسة |
| GET | /contracts/show/{id} | ContractPolicy::view |

**الصلاحيات الدنيا للواجهة:** **contracts.view** أو عقود المستخدم عبر السياسة.

---

### ProjectTrackerView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /user | auth |
| GET | /editor/contracts/show/{id} | role:editor\|admin + **contracts.view** |
| GET | /sales/projects/{id} | role:sales\|sales_leader\|admin + **sales.projects.view** |
| GET | /contracts/show/{id} | ContractPolicy::view |
| GET | /editor/contracts/index | role:editor\|admin + **contracts.view_all** |
| GET | /contracts/index | auth + سياسة |
| GET | /second-party-data/show/{id} | ContractPolicy::view (مثلاً second_party.view للـ sales) |

**الصلاحيات الدنيا للواجهة:** **contracts.view**، **contracts.view_all**، **sales.projects.view**، **second_party.view**؛ مع أدوار editor أو sales أو sales_leader أو admin حسب التاب.

---

### ReservationsView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /sales/reservations | **sales.reservations.view** |
| GET | /sales/waiting-list | **sales.waiting_list.create** |
| GET | /sales/negotiations/pending | **sales.negotiation.approve** |
| GET | /sales/reservations/{id}/voucher | **sales.reservations.view** |
| POST | /sales/reservations/{id}/confirm | **sales.reservations.confirm** |
| POST | /sales/reservations/{id}/cancel | **sales.reservations.cancel** |
| POST | /sales/waiting-list/{id}/convert | **sales.waiting_list.convert** |
| DELETE | /sales/waiting-list/{id} | **sales.waiting_list.create** |
| POST | /sales/negotiations/{id}/approve | **sales.negotiation.approve** |
| POST | /sales/negotiations/{id}/reject | **sales.negotiation.approve** |

**الصلاحيات الدنيا للواجهة:** **sales.reservations.view**، **sales.reservations.confirm**، **sales.reservations.cancel**، **sales.waiting_list.create**، **sales.waiting_list.convert**، **sales.negotiation.approve**؛ دور sales أو sales_leader أو admin.

---

### ProfileView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /user | auth |
| POST | /logout | auth |

**الصلاحيات الدنيا للواجهة:** مصادق فقط.

---

### TasksView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /user | auth |
| GET | /tasks/sections | auth |
| GET | /tasks/sections/{section}/users | auth |
| GET | /project_management/teams/index | auth |
| GET | /hr/users | role:hr\|admin أو **hr.users.view** |
| GET | /my-tasks | auth |
| GET | /requested-tasks | auth |
| POST | /tasks | auth (وقد يُتحقق من **tasks.create**) |
| PATCH | /my-tasks/{id}/status | auth |

**الصلاحيات الدنيا للواجهة:** مصادق؛ **tasks.create** لإنشاء المهام؛ **hr.users.view** أو دور hr إن استُخدمت قائمة الموظفين من /hr/users.

---

### TeamsView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /hr/users | role:hr\|admin أو **hr.users.view** |

**الصلاحيات الدنيا للواجهة:** **hr.users.view** أو دور hr أو admin.

---

### TeamManagementView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /project_management/teams/index | role:project_management\|admin |
| POST | /project_management/teams/store | role:project_management\|admin |
| PUT | /project_management/teams/update/{id} | role:project_management\|admin |
| DELETE | /project_management/teams/delete/{id} | role:project_management\|admin |
| GET | /hr/teams/{id}/members | role:hr\|admin |
| GET | /hr/teams/contracts/{teamId} | role:hr\|admin |
| GET | /hr/list_employees | role:hr\|admin |
| POST | /hr/teams/{id}/members | role:hr\|admin |
| DELETE | /hr/teams/{id}/members/{userId} | role:hr\|admin |

**الصلاحيات الدنيا للواجهة:** دور **project_management** أو **admin** لمسارات project_management/teams؛ دور **hr** أو **admin** لمسارات /hr/teams و /hr/list_employees.

---

### ImageApprovalView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /contracts/index | auth + سياسة |
| GET | /photography-department/show/{contractId} | ضمن project_management أو auth فقط — **departments.photography.view** أو ContractPolicy::view |
| PATCH | /photography-department/approve/{contractId} | role:project_management\|admin؛ في editor: **departments.photography.edit** |

**الصلاحيات الدنيا للواجهة:** **contracts.view** أو **contracts.view_all**، **departments.photography.view**، **departments.photography.edit** (للموافقة)؛ مع دور project_management أو editor أو admin.

---

### CommissionDepositsView (عمولات والودائع — مبيعات)
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /user | auth |
| GET | /sales/sold-units | **sales.dashboard.view** |
| GET | /sales/sold-units/{unitId}/commission-summary | **sales.dashboard.view** |
| GET | /sales/deposits/management | **sales.dashboard.view** |
| GET | /sales/deposits/follow-up | **sales.dashboard.view** |
| GET | /sales/analytics/dashboard | **sales.dashboard.view** |
| GET | /sales/analytics/sold-units | **sales.dashboard.view** |
| GET | /sales/commissions/stats/employee/{userId} | **sales.dashboard.view** |
| GET | /sales/commissions/monthly-report | **sales.dashboard.view** |

**الصلاحيات الدنيا للواجهة:** **sales.dashboard.view**؛ دور sales أو sales_leader أو admin.

---

### BoardsView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /contracts/index | auth + سياسة |

**الصلاحيات الدنيا للواجهة:** **contracts.view** أو **contracts.view_all** أو عقود المستخدم.

---

### EditorView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /editor/contracts/index | **contracts.view_all** |
| GET | /editor/contracts/show/{id} | **contracts.view** |
| GET | /editor/developers | **contracts.view_all** |
| GET | /editor/developers/{developer_number} | **contracts.view** |
| GET | /editor/second-party-data/show/{id} | **second_party.view** |
| GET | /editor/contracts/units/show/{contractId} | **units.view** |
| GET | /editor/montage-department/show/{contractId} | **departments.montage.view** |
| POST | /editor/montage-department/store/{contractId} | **departments.montage.edit** |
| PUT | /editor/montage-department/update/{contractId} | **departments.montage.edit** |
| GET | /editor/photography-department/show/{contractId} | **departments.photography.view** |
| POST | /editor/photography-department/store/{contractId} | **departments.photography.edit** |
| PUT | /editor/photography-department/update/{contractId} | **departments.photography.edit** |
| PATCH | /editor/photography-department/approve/{contractId} | **departments.photography.edit** |
| GET | /editor/boards-department/show/{contractId} | **departments.boards.view** |
| POST | /editor/boards-department/store/{contractId} | **departments.boards.edit** |
| PUT | /editor/boards-department/update/{contractId} | **departments.boards.edit** |

**الصلاحيات الدنيا للواجهة:** **contracts.view_all**، **contracts.view**، **second_party.view**، **units.view**، **departments.montage.view**، **departments.montage.edit**، **departments.photography.view**، **departments.photography.edit**، **departments.boards.view**، **departments.boards.edit**؛ دور **editor** أو **admin**.

---

### EditorDetailView
| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /editor/contracts/show/{id} | **contracts.view** |
| GET | /editor/developers/{id} | **contracts.view** |

**الصلاحيات الدنيا للواجهة:** **contracts.view**؛ دور editor أو admin.

---

### AgentsView
لا استدعاء API خادم (البيانات من localStorage). **الصلاحيات الدنيا:** لا يلزم.

---

## 2. واجهات بتبويبات (الاستدعاءات من التبويبات والمكونات الفرعية)

### HRView (جميع التابات)
**الدور المطلوب:** hr أو admin (middleware `hr` على prefix /hr).

**الصلاحيات الدنيا (حسب التاب):** hr.dashboard.view، hr.teams.manage، hr.employees.manage أو hr.users.create، hr.performance.view، hr.warnings.manage، hr.contracts.manage، hr.reports.view، hr.reports.print. مسارات /hr/* لا تحمل permission على كل route؛ الدور hr أو admin يفتح الوصول.

---

### MarketingView (جميع التابات)
**الدور المطلوب:** marketing أو admin.

**الصلاحيات الدنيا:** marketing.dashboard.view، marketing.projects.view، marketing.plans.create، marketing.budgets.manage، marketing.tasks.view، marketing.tasks.confirm، marketing.reports.view، marketing.teams.view، marketing.teams.manage، marketing.ads.view، marketing.ads.manage، hr.users.view (لـ GET /marketing/users).

---

### CreditView (جميع التابات)
**الدور المطلوب:** credit أو admin.

**الصلاحيات الدنيا:** credit.dashboard.view، credit.bookings.view، credit.bookings.manage، credit.financing.view، credit.financing.manage، credit.title_transfer.manage، credit.claim_files.view، credit.claim_files.manage، credit.payment_plan.manage.

---

### AccountingView (جميع التابات)
**الدور المطلوب:** accounting أو admin.

**الصلاحيات الدنيا:** accounting.dashboard.view، accounting.notifications.view، accounting.sold-units.view، accounting.sold-units.manage، accounting.commissions.approve، accounting.commissions.create، accounting.deposits.view، accounting.deposits.manage، accounting.salaries.view، accounting.salaries.distribute، accounting.down_payment.confirm، accounting.claim_files.view، accounting.claim_files.manage.

---

### SalesViewExtended (جميع التابات)
**الدور المطلوب:** sales أو sales_leader أو admin.

**الصلاحيات الدنيا:** sales.dashboard.view، sales.projects.view، sales.units.view، sales.units.book، sales.reservations.create، sales.reservations.view، sales.reservations.confirm، sales.reservations.cancel، sales.waiting_list.create، sales.waiting_list.convert، sales.goals.view، sales.goals.create، sales.schedule.view، sales.targets.view، sales.targets.update، sales.team.manage، sales.attendance.view، sales.attendance.manage، sales.tasks.manage، sales.tasks.create_for_marketing، sales.projects.allocate_shifts، sales.negotiation.approve، sales.payment-plan.manage، contracts.view (للمشاريع الحصرية/المطورين)، second_party.view. تعيين المشاريع من admin: **sales.team.manage** مع دور admin.

---

## 3. إدارة المستخدمين (UserManagement)

| Method | Backend path | دور/صلاحية مطلوبة |
|--------|--------------|-------------------|
| GET | /hr/users | role:hr\|admin أو **hr.users.view** |
| POST | /hr/users | role:hr\|admin |
| PUT | /hr/users/{id} | role:hr\|admin |
| PATCH | /hr/users/{id}/status | role:hr\|admin |
| DELETE | /hr/users/{id} | role:hr\|admin |
| GET | /admin/employees/roles | role:admin + **employees.manage** |
| POST | /admin/employees/add_employee | role:admin + **employees.manage** |
| POST | /hr/add_employee | role:hr\|admin |
| GET | /hr/list_employees | role:hr\|admin |

**الصلاحيات الدنيا:** دور **hr** أو **admin**؛ إن استُخدمت مسارات admin/employees: **employees.manage** مع دور admin.

---

## 4. خلاصة للاستخدام

1. **للواجهة X:** امنح المستخدم كل الصلاحيات المدرجة تحت "الصلاحيات الدنيا للواجهة" لهذه الواجهة، إضافة إلى الدور المطلوب إن وُجد.
2. **لل واجهات بتبويبات (HR، Marketing، Credit، Accounting، Sales):** إما منح كل صلاحيات القسم دفعة واحدة، أو ربط كل تاب بصلاحيات الـ endpoints الخاصة بذلك التاب فقط.
3. **مطابقة الباكند:** تأكد أن أسماء الصلاحيات في الباكند (مثلاً في `config/ai_capabilities.php`) تطابق ما يتحقق منه الـ middleware والـ Policies.

تم استخراج الربط من `routes/api.php` و`app/Policies/ContractPolicy.php` و`config/ai_capabilities.php`.

---

## 5. تدقيق الأدوار (مراجعة النواقص)

مقارنة صلاحيات كل دور في `config/ai_capabilities.php` مع الواجهات التي يفترض أن يصل لها:

| الدور | الواجهات المفترضة | صلاحيات ناقصة (قد تسبب 403) | التوصية |
|-------|-------------------|------------------------------|---------|
| credit | CreditView (بما فيها تاب ملفات المطالبات) | دور credit لا يملك **credit.claim_files.view** ولا **credit.claim_files.manage** (التعليق في الـ config يقول "claim_files: moved to accounting only" لكن مسارات /credit/claim-files موجودة وتطلب هذه الصلاحيات) | إضافة credit.claim_files.view و credit.claim_files.manage لدور credit إن كان قسم الائتمان يستخدم تاب ملفات المطالبات. |
| sales / sales_leader | TasksView مع قائمة موظفين من /hr/users | لا يملكان hr.users.view — إن كانت المهام تستدعي /hr/users للتعيين قد يحدث 403. مسار /tasks/sections و /my-tasks لا يطلبان hr.users؛ إن استُخدم /hr/users في واجهة المهام لاختيار معيّن فيُفضّل استخدام /project_management/teams أو /marketing/users حسب السياق أو منح hr.users.view. | مراجعة الفرونت: إن استُدعي GET /hr/users من تاب المهام لغير HR فإما استخدام مسار بديل أو منح hr.users.view للدور المناسب. |
| editor | EditorView (موافقة التصوير) | editor يملك departments.photography.edit؛ مسار PATCH approve تحت editor موجود. لا نقص واضح. | — |
| default | MyRequestsView، ProfileView، AiAssistantView، TasksView (محدود) | default يملك contracts.view، notifications.view، use-ai-assistant، tasks.create. كافٍ للواجهات المحدودة. | — |

**خلاصة التدقيق:** الثغرة المؤكدة هي دور **credit** ومسارات **credit/claim-files**: الباكند يطلب credit.claim_files.view و credit.claim_files.manage وهما غير ممنوحين لدور credit في bootstrap_role_map. يُوصى بإضافتهما لدور credit.
