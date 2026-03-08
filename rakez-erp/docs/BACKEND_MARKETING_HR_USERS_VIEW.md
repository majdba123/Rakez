# صلاحية عرض الموظفين للماركيتنغ (Backend)

## الهدف
السماح لدور **marketing** (أو لأي مستخدم يملك صلاحية `hr.users.view`) بتنفيذ **GET فقط** على:
- `GET /api/hr/users` — قائمة الموظفين
- `GET /api/hr/users/{id}` — تفاصيل موظف واحد

بدون منحه POST/PUT/DELETE على نفس المسار (إنشاء/تعديل/حذف يبقى لـ HR فقط).

## ما تم تطبيقه في الباكند

### 1. صلاحية جديدة
- **المفتاح:** `hr.users.view`
- **الثابت:** `PermissionConstants::HR_USERS_VIEW`
- **الوصف:** View employees list (for plans and team selection).

### 2. التعريف في الإعدادات
- تمت إضافة `hr.users.view` في `config/ai_capabilities.php` ضمن `definitions`.
- تمت إضافتها لدور **marketing** في `bootstrap_role_map`.

### 3. الـ Middleware
في `App\Http\Middleware\HrMiddleware`:
- المستخدمون من نوع `hr` أو `admin` يمرون لكل مسارات HR كما هو.
- إذا كان المستخدم ليس hr ولا admin:
  - يُسمح بالطلب فقط إذا كان:
    - الطلب **GET**، و
    - المسار `api/hr/users` أو `api/hr/users/{id}` (رقم)، و
    - المستخدم يملك صلاحية `hr.users.view`.
- غير ذلك يُرجع 403.

### 4. تشغيل الـ Seeder
بعد تعديل الصلاحيات أو الـ role map يجب إعادة تشغيل:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

حتى تُنشأ صلاحية `hr.users.view` في الجدول وتُربط بدور marketing.

## الفرونت إند
في `src/constants/permissions.js`:
- صلاحية `HR_USERS_VIEW: 'hr.users.view'`
- منحها لدور marketing في `BOOTSTRAP_ROLE_MAP`

بهذا يمكن للماركيتنغ استدعاء `GET /api/hr/users` (أو `GET /api/hr/users/{id}`) من الواجهة دون 403، وعرض قائمة الموظفين في خطط الموظفين واختيار الفريق.
