# Visibility / Action / Scope Matrix

## قاعدة التنفيذ
أي عنصر داخل Filament يجب أن يجيب عن 3 أسئلة:
1. `Visibility`: هل يظهر أصلًا في الـ navigation أو الصفحة؟
2. `Action`: هل يسمح بتنفيذ فعل عليه؟
3. `Scope`: ما حدود البيانات التي يراها أو يعدلها؟

## نموذج الـ Scope المعتمد
| Scope | الاستخدام |
|---|---|
| `global` | كل السجلات على مستوى الشركة |
| `section-limited` | كل السجلات داخل قسم محدد فقط |
| `team-limited` | فقط سجلات فريق أو وحدة عمل معينة |
| `ownership-limited` | فقط السجلات المملوكة أو المسندة للمستخدم |
| `stage/status-limited` | فقط السجلات في مراحل أو حالات محددة |

## قواعد الـ Scope حسب الدور
| الدور | Scope الافتراضي |
|---|---|
| `super_admin` | `global` |
| `erp_admin` | `global` في governance، و`global` قراءة على الأقسام ما لم يقيد صراحة |
| `auditor_readonly` | `global` قراءة فقط |
| `credit_admin` | `section-limited` داخل الائتمان |
| `accounting_admin` | `section-limited` داخل المحاسبة |
| `projects_admin` | `section-limited` داخل العقود/المشاريع |
| بقية `section_admin` roles | `section-limited` |

## MVP Matrix
هذه هي العناصر التي يجب أن تكون مقفلة القرار قبل Phase 1/2/3.

| العنصر | النوع | Visibility permission | Open permission | Action permission | Scope | داخل MVP |
|---|---|---|---|---|---|---|
| لوحة Filament بالكامل | Panel | `admin.panel.access` | `admin.panel.access` | لا ينطبق | حسب الدور | نعم |
| مركز القيادة | Navigation Group | `admin.panel.access` + `admin.dashboard.view` | `admin.dashboard.view` | لا ينطبق | حسب الدور | نعم |
| إدارة النظام والحوكمة | Navigation Group | `admin.panel.access` | `admin.panel.access` | لا ينطبق | `global` | نعم |
| لوحة المؤشرات الإدارية | Page | `admin.dashboard.view` | `admin.dashboard.view` | لا توجد أفعال حساسة في MVP | `global` | نعم |
| المستخدمون | Resource | `admin.users.view` | `admin.users.view` | `admin.users.manage` | `global` | نعم |
| الأدوار | Resource | `admin.roles.view` | `admin.roles.view` | `admin.roles.manage` | `global` | نعم |
| الصلاحيات | Resource/Page | `admin.permissions.view` | `admin.permissions.view` | `admin.permissions.manage` | `global` | نعم |
| الصلاحيات المباشرة | Page | `admin.direct_permissions.view` | `admin.direct_permissions.view` | `admin.direct_permissions.manage` | `global` | نعم |
| مستكشف الوصول الفعّال | Page | `admin.effective_access.view` | `admin.effective_access.view` | لا تعديل في MVP | `global` | نعم |
| سجل تغييرات الوصول | Page | `admin.audit.view` | `admin.audit.view` | لا تعديل في MVP | `global` | نعم |
| إعدادات الإدارة الأساسية | Page | `admin.settings.view` | `admin.settings.view` | `admin.settings.manage` لاحقًا فقط | `global` | لا كتحكم كامل |
| مجموعة الائتمان | Navigation Group | `credit.dashboard.view` أو أي صلاحية `credit.*` إدارية مع `admin.panel.access` | نفس الصلاحية | لا ينطبق | `section-limited` | نعم |
| Credit Dashboard | Page | `credit.dashboard.view` | `credit.dashboard.view` | لا إجراءات حساسة | `section-limited` | نعم |
| Credit Booking Review | Review Page | `credit.bookings.view` | `credit.bookings.view` | `credit.bookings.manage` | `section-limited` + `stage/status-limited` | نعم |
| Credit Financing Review | Page | `credit.financing.view` | `credit.financing.view` | `credit.financing.manage` | `section-limited` | نعم |
| Claim Files Review | Review Page | `credit.claim_files.view` | `credit.claim_files.view` | `credit.claim_files.manage` | `section-limited` + `stage/status-limited` | نعم |
| Title Transfer Approval | Approval Page | `credit.title_transfer.manage` | `credit.title_transfer.manage` | `credit.title_transfer.manage` | `section-limited` + `stage/status-limited` | نعم |

## ما بعد MVP
| العنصر | النوع | Visibility permission | Open permission | Action permission | Scope |
|---|---|---|---|---|---|
| المحاسبة والمالية | Navigation Group | `accounting.dashboard.view` | `accounting.dashboard.view` | لا ينطبق | `section-limited` |
| Accounting Dashboard | Page | `accounting.dashboard.view` | `accounting.dashboard.view` | لا إجراءات حساسة | `section-limited` |
| Commission Approvals | Approval Page | `accounting.sold-units.view` | `accounting.sold-units.view` | `accounting.commissions.approve` | `section-limited` + `stage/status-limited` |
| Deposits Oversight | Page | `accounting.deposits.view` | `accounting.deposits.view` | `accounting.deposits.manage` | `section-limited` |
| Salaries Distribution | Page | `accounting.salaries.view` | `accounting.salaries.view` | `accounting.salaries.distribute` | `section-limited` |
| العقود والمشاريع | Navigation Group | `contracts.view_all` أو `projects.view` | نفس الصلاحية | لا ينطبق | `section-limited` |
| Contracts Oversight | Page/Resource | `contracts.view_all` | `contracts.view_all` | `contracts.approve`, `contracts.create` | `section-limited` |
| Project Readiness | Review Page | `projects.view` | `projects.view` | `projects.approve`, `projects.media.approve` | `section-limited` + `stage/status-limited` |
| Exclusive Project Requests | Approval Page | `exclusive_projects.view` | `exclusive_projects.view` | `exclusive_projects.approve` | `section-limited` + `stage/status-limited` |

## قواعد ممنوعة
- لا يوجد عنصر في الـ navigation يعتمد على role name فقط
- لا يسمح بزر `Approve` دون permission تنفيذية منفصلة عن permission الرؤية
- لا يسمح بعرض سجل إذا كان Scope غير معرف
- لا يسمح بأي `Override` أو `Force Action` في MVP
