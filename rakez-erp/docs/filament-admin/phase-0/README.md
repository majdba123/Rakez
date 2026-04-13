# Phase 0 Implementation Packet

هذه الحزمة هي المرجع الرسمي المقفل القرار قبل أي كتابة كود تخص لوحة `Filament Admin`.

الحالة:
- الفرع المعتمد للتنفيذ: `fila`
- المرحلة: `Phase 0`
- الحالة: `Approved baseline`
- نوع المخرجات: وثائق تنفيذية فقط، بدون كود

مصادر الحقيقة المعتمدة داخل المستودع:
- `config/user_types.php`
- `config/ai_capabilities.php`
- `config/permission.php`
- `app/Constants/PermissionConstants.php`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Middleware/CheckDynamicPermission.php`
- `routes/api.php`
- `docs/VIEW-TO-API-PERMISSIONS.md`

مصدر الأعمال المعتمد خارج المستودع:
- بنية `AppSidebar.vue` الحالية كما تم اعتمادها في التخطيط، وتستخدم هنا كمرجع business/navigation baseline

القرارات المغلقة في هذه الحزمة:
- لوحة Filament واحدة فقط
- `admin.panel.access` هي بوابة الدخول الوحيدة
- لا دخول للوحة اعتمادًا على الدور فقط
- الأرقام الخام القادمة من الـ sidebar ليست مصدر الحقيقة المستقبلي؛ الأدوار الاسمية في الباكند هي الأساس
- كل صلاحية Filament تعرف على 3 مستويات: `Visibility`, `Action`, `Scope`
- لا `Override` ولا `Temporary Grants` داخل MVP
- لا نقل لأي workflow تشغيلي يومي إلى Filament

مخرجات الحزمة:
1. [Role Mapping](./01_ROLE_MAPPING.md)
2. [Permission Dictionary Freeze](./02_PERMISSION_DICTIONARY_FREEZE.md)
3. [Role Permission Matrix](./03_ROLE_PERMISSION_MATRIX.md)
4. [Visibility / Action / Scope Matrix](./04_VISIBILITY_ACTION_SCOPE_MATRIX.md)
5. [In Filament / Out of Filament Matrix](./05_IN_FILAMENT_OUT_OF_FILAMENT_MATRIX.md)
6. [MVP Boundary Sheet](./06_MVP_BOUNDARY_SHEET.md)

شروط الخروج من Phase 0:
- لا توجد صلاحية جديدة خارج القاموس المعتمد
- لا يوجد `userRole` أو `type` بدون مقابل اسمي واضح
- لا يوجد tab مرشح لـ Filament بدون تمثيل إداري واضح
- لا يوجد عنصر داخل MVP بدون `Visibility` و`Action` واضحين
- لا يوجد عنصر أعمال داخل MVP بدون Scope افتراضي واضح

الممنوعات قبل Phase 1:
- إنشاء `Filament Resources`
- إنشاء `Filament Pages`
- إنشاء `Widgets`
- إدخال أقسام أعمال إضافية خارج حدود MVP
- اختراع أسماء صلاحيات جديدة أثناء التنفيذ بدون مراجعة معمارية
