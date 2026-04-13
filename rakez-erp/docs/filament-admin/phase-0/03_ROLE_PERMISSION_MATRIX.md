# Role Permission Matrix

## قواعد عامة
- الدخول إلى Filament لا يورث من أي دور تشغيلي
- كل دور داخل اللوحة يحتاج `admin.panel.access`
- الأدوار الإدارية Overlay roles وليست بديلًا كاملًا عن الأدوار التشغيلية
- `super_admin` لا يستخدم كدور يومي للإدارة، بل كدور استثنائي محدود العدد

## الأدوار التشغيلية
هذه الأدوار تبقى مسؤولة عن تشغيل النظام الحالي خارج Filament.

| الدور | Panel access | Families الأساسية | الملاحظة |
|---|---|---|---|
| `project_management_staff` | لا | `contracts.*`, `units.*`, `projects.*`, `departments.*`, `exclusive_projects.*` | تشغيلي فقط |
| `project_management_manager_staff` | لا | نفس ما سبق + dynamic effective permissions الحالية | لا panel access افتراضي |
| `editor_staff` | لا | `editing.*`, `departments.*`, `contracts.view*` | تشغيلي فقط |
| `developer_staff` | لا | `contracts.create`, `contracts.view*` | تشغيلي فقط |
| `marketing_staff` | لا | `marketing.*`, `hr.users.view`, `contracts.view_all` | تشغيلي فقط |
| `sales_staff` | لا | `sales.*` الأساسية | تشغيلي فقط |
| `sales_leader_staff` | لا | `sales.*` الأساسية + leader extras | تشغيلي فقط |
| `hr_staff` | لا | `hr.*` | تشغيلي فقط |
| `credit_staff` | لا | `credit.*`, `contracts.view*` | تشغيلي فقط |
| `accounting_staff` | لا | `accounting.*`, `contracts.view*` | تشغيلي فقط |
| `accountant_staff` | لا | `accounting.*` المحددة | تشغيلي فقط |
| `inventory_staff` | لا | `contracts.view*`, `units.view`, `second_party.view` | تشغيلي فقط |
| `default_staff` | لا | `contracts.view*`, `notifications.view`, `tasks.create` | تشغيلي فقط |

## الأدوار الإدارية الخاصة بـ Filament

| الدور | Panel access | Governance families | Business oversight families | Default scope | يدخل MVP |
|---|---|---|---|---|---|
| `super_admin` | نعم | كل `admin.*` | كل families الرقابية | `global` | نعم، لكن التعيين يدوي جدًا |
| `erp_admin` | نعم | `admin.dashboard.view`, `admin.users.*`, `admin.roles.*`, `admin.permissions.*`, `admin.direct_permissions.*`, `admin.effective_access.view`, `admin.audit.view` | قراءة عامة للأقسام، وبدون override | `global` | نعم |
| `auditor_readonly` | نعم | `admin.dashboard.view`, `admin.effective_access.view`, `admin.audit.view` | `*.view`, `*.list`, `*.export` فقط | `global` | يثبت الآن، ويمكن تفعيله لاحقًا |
| `credit_admin` | نعم | `admin.dashboard.view`, `admin.audit.view` | `credit.dashboard.view`, `credit.bookings.view`, `credit.bookings.manage`, `credit.financing.view`, `credit.financing.manage`, `credit.title_transfer.manage`, `credit.claim_files.view`, `credit.claim_files.manage`, `credit.payment_plan.manage`, `contracts.view_all`, `second_party.view` | `section-limited` | نعم |
| `accounting_admin` | نعم | `admin.dashboard.view`, `admin.audit.view` | `accounting.dashboard.view`, `accounting.notifications.view`, `accounting.claim_files.view`, `accounting.claim_files.manage`, `accounting.sold-units.view`, `accounting.sold-units.manage`, `accounting.commissions.approve`, `accounting.commissions.create`, `accounting.deposits.view`, `accounting.deposits.manage`, `accounting.salaries.view`, `accounting.salaries.distribute`, `accounting.down_payment.confirm`, `contracts.view_all` | `section-limited` | بعد MVP المباشر |
| `projects_admin` | نعم | `admin.dashboard.view`, `admin.audit.view` | `contracts.view_all`, `contracts.approve`, `contracts.create`, `units.view`, `units.edit`, `units.csv_upload`, `second_party.view`, `second_party.edit`, `projects.view`, `projects.create`, `projects.media.approve`, `projects.team.*`, `exclusive_projects.approve` | `section-limited` | لاحقًا |
| `sales_admin` | نعم | `admin.dashboard.view` | `sales.dashboard.view`, `sales.projects.view`, `sales.reservations.view`, `sales.targets.view`, `sales.targets.update`, `sales.team.manage`, `sales.attendance.view`, `sales.negotiation.approve`, `sales.payment-plan.manage` | `section-limited` | لاحقًا |
| `hr_admin` | نعم | `admin.dashboard.view` | `hr.dashboard.view`, `hr.teams.manage`, `hr.employees.manage`, `hr.users.create`, `hr.performance.view`, `hr.warnings.manage`, `hr.contracts.manage`, `hr.reports.view`, `hr.reports.print` | `section-limited` | لاحقًا |
| `marketing_admin` | نعم | `admin.dashboard.view` | `marketing.dashboard.view`, `marketing.projects.view`, `marketing.plans.create`, `marketing.budgets.manage`, `marketing.tasks.view`, `marketing.tasks.confirm`, `marketing.reports.view`, `marketing.teams.view`, `marketing.teams.manage`, `marketing.ads.view`, `marketing.ads.manage` | `section-limited` | لاحقًا |
| `inventory_admin` | نعم | `admin.dashboard.view` | `inventory.dashboard.view`, `inventory.overview.view`, `inventory.export`, مع الاحتفاظ مؤقتًا بالحاجة إلى `contracts.view_all` حتى فصل inventory family برمجيًا | `section-limited` | لاحقًا |
| `ai_admin` | نعم | `admin.dashboard.view`, `admin.audit.view` | `ai_governance.*` + `manage-ai-knowledge` + `ai-calls.manage` | `section-limited` | لاحقًا |
| `workflow_admin` | نعم | `admin.dashboard.view`, `admin.audit.view` | `workflow.requests.*` + `exclusive_projects.*` عند الحاجة | `section-limited` | لاحقًا |

## قواعد التركيب
- يجوز للمستخدم حمل دور تشغيلي + دور إداري Overlay معًا
- `erp_admin` ليس بديلًا عن `super_admin`
- لا يمنح أي `section_admin` صلاحيات حوكمة المستخدمين أو الأدوار أو الصلاحيات
- لا يمنح `auditor_readonly` أي `manage` أو `approve`

## قرار خاص بـ MVP
داخل MVP يسمح فقط بالأدوار التالية داخل Filament:
- `super_admin`
- `erp_admin`
- `credit_admin`
- `auditor_readonly` إذا تم تفعيله تنظيميًا

وكل ما عدا ذلك يؤجل إلى ما بعد تثبيت MVP.
