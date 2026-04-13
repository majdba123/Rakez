# Role Mapping

## القرار الحاكم
يوجد تعارض فعلي بين أرقام الأدوار المستخدمة في الـ sidebar المعتمد في التخطيط وبين الخريطة الحالية في `config/user_types.php`.

القرار النهائي لـ Phase 0:
- مصدر الحقيقة المستقبلي هو **الأدوار الاسمية** المخزنة في الباكند (`type` + `is_manager` + أدوار Spatie)
- الأرقام الخام القادمة من الواجهة الحالية تستخدم فقط لفهم التجربة الحالية، ولا تستخدم كأساس هجرة أو حوكمة Filament
- أي تنفيذ في Phase 1 وما بعده يبنى على **Named Roles** وليس على `userRole` الرقمي

## الخريطة الحالية في الباكند
المصدر: `config/user_types.php`

| الرقم الحالي في الباكند | القيمة الاسمية الحالية |
|---|---|
| 1 | `admin` |
| 2 | `project_management` |
| 3 | `editor` |
| 4 | `developer` |
| 5 | `marketing` |
| 6 | `sales` |
| 7 | `sales_leader` |
| 8 | `hr` |
| 9 | `credit` |
| 10 | `accounting` |
| 11 | `inventory` |
| 12 | `default` |
| 13 | `accountant` |

## الخريطة المستقبلية المعتمدة
هذه الخريطة تفصل بين:
- أدوار تشغيلية يومية
- أدوار إدارية خاصة بـ Filament
- أدوار Overlay إدارية تمنح عند الحاجة فقط

| المصدر الحالي | الدليل الحالي | الدور المستقبلي المعتمد | الفئة | دخول Filament افتراضيًا | ملاحظات التنفيذ |
|---|---|---|---|---|---|
| `admin` | `config/user_types.php` + `Gate::before` في `AppServiceProvider` | `erp_admin` | إداري | نعم | هذا هو الدور الإداري الأساسي في MVP |
| subset من `admin` | قرار تنظيمي جديد | `super_admin` | إداري أعلى | نعم | لا يورث تلقائيًا؛ يعين يدويًا فقط |
| دور جديد | قرار تنظيمي جديد | `auditor_readonly` | إداري رقابي | نعم | يقرر في Phase 0 حتى لو لم يستخدم فورًا |
| `project_management` | `config/user_types.php` | `project_management_staff` | تشغيلي | لا | يبقى خارج Filament افتراضيًا |
| `project_management` + `is_manager=true` | `User::isProjectManagementManager()` | `project_management_manager_staff` | تشغيلي | لا | يحصل على `projects_admin` فقط إذا منح panel access صراحة |
| Overlay جديد | قرار تنظيمي جديد | `projects_admin` | إداري قطاعي | نعم | لا يعطى تلقائيًا لكل PM |
| `editor` | `config/user_types.php` | `editor_staff` | تشغيلي | لا | لا panel access افتراضي |
| `developer` | `config/user_types.php` | `developer_staff` | تشغيلي | لا | لا panel access افتراضي |
| `marketing` | `config/user_types.php` | `marketing_staff` | تشغيلي | لا | لا panel access افتراضي |
| Overlay جديد | قرار تنظيمي جديد | `marketing_admin` | إداري قطاعي | نعم | يمنح عند الحاجة الرقابية فقط |
| `sales` | `config/user_types.php` | `sales_staff` | تشغيلي | لا | لا panel access افتراضي |
| `sales_leader` | `config/user_types.php` + `User::isSalesLeader()` | `sales_leader_staff` | تشغيلي | لا | لا panel access افتراضي |
| Overlay جديد | قرار تنظيمي جديد | `sales_admin` | إداري قطاعي | نعم | يستخدم للرقابة لا للتشغيل اليومي |
| `hr` | `config/user_types.php` | `hr_staff` | تشغيلي | لا | لا panel access افتراضي |
| Overlay جديد | قرار تنظيمي جديد | `hr_admin` | إداري قطاعي | نعم | لإدارة الموارد البشرية إداريًا |
| `credit` | `config/user_types.php` | `credit_staff` | تشغيلي | لا | يبقى خارج Filament تشغيليًا |
| Overlay جديد | قرار تنظيمي جديد | `credit_admin` | إداري قطاعي | نعم | هذا أول قطاع أعمال يدخل MVP |
| `accounting` | `config/user_types.php` | `accounting_staff` | تشغيلي | لا | لا panel access افتراضي |
| `accountant` | `config/user_types.php` | `accountant_staff` | تشغيلي | لا | يبقى تشغيليًا |
| Overlay جديد | قرار تنظيمي جديد | `accounting_admin` | إداري قطاعي | نعم | يدخل بعد Credit |
| `inventory` | `config/user_types.php` | `inventory_staff` | تشغيلي | لا | لا panel access افتراضي |
| Overlay جديد | قرار تنظيمي جديد | `inventory_admin` | إداري قطاعي | نعم | يؤجل لما بعد MVP |
| `default` | `config/user_types.php` | `default_staff` | تشغيلي | لا | لا panel access افتراضي |
| Overlay جديد | قرار تنظيمي جديد | `ai_admin` | إداري قطاعي | نعم | لإدارة المعرفة والتدقيق فقط |
| Overlay جديد | قرار تنظيمي جديد | `workflow_admin` | إداري قطاعي | نعم | لمركز الطلبات والاعتمادات لاحقًا |

## قواعد الإسناد
- لا دور تشغيلي يحصل على دخول Filament تلقائيًا
- `admin.panel.access` شرط سابق لأي دور إداري داخل اللوحة
- `erp_admin` هو دور الحوكمة القياسي
- `super_admin` لا يستخدم كاختصار للحوكمة اليومية
- أدوار القطاعات الإدارية (`credit_admin`, `accounting_admin`, ...) أدوار Overlay ولا تستبدل الدور التشغيلي

## قرار حاسم بخصوص أرقام الـ sidebar
- لا يسمح باستخدام `userRole` الرقمي القادم من الواجهة كأساس لصلاحيات Filament
- أي Migration أو Seed أو Authorization mapping في المراحل التالية يبنى على:
  - `type`
  - `is_manager`
  - roles/permissions من Spatie

## مخرجات إلزامية لمرحلة التنفيذ التالية
- Phase 1 يجب أن يتعامل مع `erp_admin` و`super_admin` كدورين منفصلين
- Phase 1 لا يغير الأدوار التشغيلية الحالية إلا بقدر الحاجة للربط الاسمي والتوافق
