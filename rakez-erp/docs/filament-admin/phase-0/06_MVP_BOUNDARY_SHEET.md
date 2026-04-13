# MVP Boundary Sheet

## تعريف الـ MVP الرسمي
الـ MVP الناجح للوحة `Filament Admin` يعني اكتمال العناصر التالية مجتمعة، وليس بعضها:

1. Panel واحدة فقط داخل Filament
2. صلاحية دخول مستقلة: `admin.panel.access`
3. إدارة Users
4. إدارة Roles
5. إدارة Permissions
6. إدارة Direct Permissions
7. Effective Access Explorer v1
8. Access Audit Log v1
9. Sidebar إداري موحد للأقسام
10. Dashboard إداري أساسي
11. Credit Oversight بصيغة `Review / Approval` فقط

## ما يدخل في MVP
| العنصر | الحالة |
|---|---|
| `admin.panel.access` | داخل MVP |
| `admin.dashboard.view` | داخل MVP |
| `admin.users.*` | داخل MVP |
| `admin.roles.*` | داخل MVP |
| `admin.permissions.*` | داخل MVP |
| `admin.direct_permissions.*` | داخل MVP |
| `admin.effective_access.view` | داخل MVP |
| `admin.audit.view` | داخل MVP |
| Credit Dashboard | داخل MVP |
| Credit Booking Review | داخل MVP |
| Claim Files Review | داخل MVP |
| Title Transfer Approval | داخل MVP |

## ما يستبعد صراحة من MVP
| العنصر | القرار |
|---|---|
| `Temporary Grants` | خارج MVP |
| `Override` | خارج MVP |
| `Force Actions` | خارج MVP |
| Cross-section Approval Center | خارج MVP |
| Accounting Oversight | خارج MVP المباشر |
| Contracts / Projects Oversight | خارج MVP المباشر |
| Sales / HR / Marketing / Inventory / AI admin sections الكاملة | خارج MVP المباشر |
| أي workflow تشغيلي يومي | خارج MVP |
| أي صفحات شخصية (`Profile`, `My Requests`, `My Attendance`, `My Performance`) | خارج MVP |

## Ready-for-Phase-1 Gate
لا يبدأ Phase 1 إلا إذا كانت الوثائق الخمس الأخرى معتمدة ويغطي كل منها الآتي:
- Role mapping نهائي
- Permission dictionary مجمد
- Role permission matrix واضح
- Visibility / Action / Scope محددة
- In/Out boundary مكتمل

## Ready-for-Phase-3 Gate
لا يبدأ إدخال Credit إلى Filament إلا إذا كانت العناصر التالية موجودة:
- `admin.panel.access`
- إدارة roles/permissions/direct permissions
- Effective Access Explorer v1
- Access Audit v1
- Sidebar جاهز
- لا توجد قرارات مفتوحة تخص Scope الائتمان

## Acceptance Checklist
| الشرط | يجب أن يتحقق |
|---|---|
| لا دخول للوحة بدون permission | نعم |
| لا اعتماد على role-only access | نعم |
| لا page بلا visibility permission | نعم |
| لا action بلا action permission | نعم |
| لا data view بلا scope واضح | نعم |
| لا `Override` | نعم |
| لا `Temporary Grants` | نعم |
| لا business rewrite داخل Filament | نعم |
| التنفيذ يبدأ من `fila` فقط | نعم |

## قائمة الممنوعات قبل وما داخل MVP
- لا إنشاء `Temporary Grants` model أو UI
- لا منح أي قدرة `Override`
- لا بناء مركز اعتمادات موحد متعدد الأقسام
- لا نقل صفحات التشغيل اليومية
- لا بناء CRUD تشغيلي للمبيعات أو الموارد البشرية أو المشاريع
- لا إضافة permission جديدة خارج القاموس المجمد

## معنى اكتمال Phase 0 عمليًا
إذا اعتمدت هذه الوثيقة وبقية الحزمة، يصبح Phase 1 قابلًا للتنفيذ مباشرة بدون قرارات معمارية جديدة تخص:
- الأدوار
- الصلاحيات
- حدود اللوحة
- حدود MVP
