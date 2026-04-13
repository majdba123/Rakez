# خطة Phase 1 — أساس حوكمة الوصول (Access Governance Foundation)

> **المرجع:** `docs/phase-0-governance-package.md` (مُعتمَد كمدخل ثابت)
> **النطاق:** النظام / الإدارة فقط — لا أقسام تشغيلية
> **الحالة:** خطة تنفيذ — لا كود بعد
> **تاريخ الإصدار:** 2026-04-04

---

## 1) هدف Phase 1

تحويل البنية التحتية الحالية للحوكمة من حالة **"موجودة ومبنية"** إلى حالة **"مُتحقّق منها ومُحصّنة ومُتسقة"** — بحيث تكون جاهزة كأساس آمن لفتح الأقسام التشغيلية الحسّاسة (الائتمان، المحاسبة) في Phase 2.

**ليس الهدف بناء شيء جديد من الصفر.** معظم المكونات موجودة فعلياً. الهدف هو:

| الفعل | التوضيح |
|-------|---------|
| **تحصين** | سد الثغرات المعمارية المكتشفة في Phase 0 |
| **توحيد** | إزالة التناقضات بين المكونات |
| **تجميد** | تفعيل حراسة تلقائية على القاموس والحدود |
| **تعطيل** | إخفاء ما هو مؤجَّل لما بعد MVP |
| **تحقّق** | إثبات كل شيء باختبارات قابلة للتكرار |

---

## 2) النطاق التنفيذي

### 2.1 — داخل النطاق (مسموح)

| المكوّن | الوصف | الحالة الحالية |
|---------|-------|---------------|
| `admin.panel.access` | بوابة الدخول الإجبارية | ✅ يعمل — يحتاج تحصيناً |
| `UserResource` + `UserGovernanceService` | إدارة المستخدمين (CRUD + أدوار) | ✅ يعمل — يحتاج توحيداً |
| `RoleResource` + `RoleGovernanceService` | إدارة أدوار الحوكمة | ✅ يعمل — يحتاج توحيداً |
| `PermissionResource` | عرض القاموس المجمّد (قراءة فقط) | ✅ يعمل |
| `DirectPermissionResource` + `DirectPermissionGovernanceService` | صلاحيات مباشرة (استثنائية) | ✅ يعمل — يحتاج إصلاح soft-delete |
| `EffectiveAccessResource` + `EffectiveAccessSnapshotService` | لقطة الوصول الفعلي | ✅ يعمل — يحتاج إصلاح filter |
| `GovernanceAuditLogResource` + `GovernanceAuditLogger` | سجل التدقيق | ✅ يعمل |
| `AdminHome` + 3 Widgets | لوحة الحوكمة الرئيسية | ✅ يعمل |
| `GovernanceAccessService` | خدمة الوصول المركزية | ✅ يعمل — يحتاج تحصيناً |
| `GovernanceCatalog` | قاموس الصلاحيات والأدوار | ✅ يعمل |
| `FilamentNavigationPolicy` | رؤية مجموعات التنقل | ✅ يعمل — يحتاج إصلاح حالة حافة |
| 3 Traits (Concerns) | Visibility / Action / Scope | ✅ يعمل |
| `AdminPanelProvider` | تسجيل اللوحة الوحيدة | ✅ يعمل |
| فصل `super_admin` عن `erp_admin` | حماية التصعيد | ✅ جزئياً — يحتاج تحصيناً |
| اختبار تجميد القاموس | حراسة آلية | ❌ غير موجود |
| مفتاح `enabled_sections` | تدرج الإطلاق | ❌ غير موجود |
| تعطيل الصلاحيات المؤقتة | قرار Phase 0 | ❌ لم يُنفَّذ |

### 2.2 — خارج النطاق (ممنوع في Phase 1)

| المحظور | السبب |
|---------|-------|
| أي Resource تحت Credit Oversight | Phase 2 |
| أي Resource تحت Accounting & Finance | Phase 3 |
| أي Resource تحت Contracts & Projects | Phase 4 |
| أي Resource للأقسام التشغيلية (Sales, HR, Marketing, Inventory, AI, Workflow) | التوسع المتحكَّم |
| إضافة صلاحيات جديدة | القاموس مُجمَّد |
| إنشاء لوحات Filament إضافية | قرار معماري ثابت |
| تفعيل الصلاحيات المؤقتة | ما بعد MVP |
| أوامر تجاوز قسري (Override / Force) | ما بعد MVP |
| تعديل API routes أو Services أو Policies التشغيلية | خارج Filament |

---

## 3) ترتيب البناء الداخلي

> **12 خطوة تنفيذية بترتيب صارم — لا تُبدأ خطوة قبل إكمال سابقتها.**

### الطبقة A: متطلبات Phase 0 (البوابات)

هذه بنود مطلوبة من Phase 0 لم تُنفَّذ بعد. يجب إكمالها أولاً لأنها شروط دخول Phase 1.

---

#### الخطوة 1: اختبار تجميد قاموس الصلاحيات

**المشكلة:** لا يوجد اختبار آلي يمنع إضافة/حذف صلاحيات بدون مراجعة.

**المطلوب:**
- إنشاء اختبار `PermissionDictionaryFreezeTest` يتحقق من:
  - `array_keys(config('ai_capabilities.definitions'))` تطابق قائمة مجمّدة حرفياً (137 مفتاح)
  - أي إضافة أو حذف يُفشل الاختبار
  - رسالة الفشل تُوجّه لمراجعة معمارية

**الملفات المتأثرة:**
- `tests/Feature/Governance/PermissionDictionaryFreezeTest.php` ← جديد

**معيار القبول:** الاختبار يمر بالقائمة الحالية (137) ويفشل عند إضافة/حذف أي مفتاح.

---

#### الخطوة 2: إضافة مفتاح `enabled_sections` وتفعيل التدرج

**المشكلة:** كل أقسام Filament مرئية الآن بلا تحكم مرحلي.

**المطلوب:**
- إضافة `'enabled_sections'` في `config/governance.php`
- القيمة الابتدائية في Phase 1:
  ```
  'enabled_sections' => [
      'Overview',
      'Access Governance',
      'Governance Observability',
  ],
  ```
- تعديل `ChecksFilamentNavigationGroupGate::canAccess()` ليفحص `enabled_sections` **قبل** فحص الصلاحيات
- المجموعات غير المدرجة في `enabled_sections` → مخفية بالكامل بغض النظر عن الصلاحيات

**الملفات المتأثرة:**
- `config/governance.php` ← تعديل
- `app/Filament/Admin/Concerns/ChecksFilamentNavigationGroupGate.php` ← تعديل

**معيار القبول:**
- مستخدم `erp_admin` يرى فقط Overview + Access Governance + Governance Observability
- لا تظهر أقسام Credit/Accounting/Projects/Sales/HR/Marketing/Inventory/AI/Workflow
- اختبار يتحقق من ذلك

---

#### الخطوة 3: تعطيل `GovernanceTemporaryPermissionResource` في MVP

**المشكلة:** المورد مبني ومفعّل — يخالف قرار Phase 0.

**المطلوب:**
- إضافة `canAccess()` يُرجع `false` دائماً في `GovernanceTemporaryPermissionResource`
- أو: إزالة تسجيله من `AdminPanelProvider::discoverResources` عبر `except`
- الخدمة (`GovernanceTemporaryPermissionService`) تبقى موجودة لكن غير مكشوفة في UI
- `GovernanceAccessService::hasPermission` يبقى كما هو (لا نكسر الطبقة الداخلية)

**الملفات المتأثرة:**
- `app/Filament/Admin/Resources/GovernanceTemporaryPermissions/GovernanceTemporaryPermissionResource.php` ← تعديل

**معيار القبول:** الرابط `/admin/governance-temporary-permissions` يُرجع 403 أو لا يظهر في التنقل لأي مستخدم.

---

#### الخطوة 4: إصلاح مرجع `sales_manager` الميت

**المشكلة:** `CommissionPolicy` و `DepositPolicy` تشيران إلى دور `sales_manager` غير موجود في `bootstrap_role_map`.

**المطلوب:**
- فحص كل `hasAnyRole` / `hasRole` في Policy files
- استبدال `sales_manager` بـ `sales_leader` (الاسم الفعلي في النظام)
- التحقق من عدم وجود مراجع ميتة أخرى

**الملفات المتأثرة:**
- `app/Policies/CommissionPolicy.php` ← تعديل
- `app/Policies/DepositPolicy.php` ← تعديل

**معيار القبول:** `grep -r "sales_manager" app/` يُرجع صفر نتائج.

---

### الطبقة B: تحصين خدمات الحوكمة الأساسية

بعد إكمال البوابات، نبدأ بتقوية الخدمات المركزية.

---

#### الخطوة 5: تحصين `GovernanceAccessService`

**المشكلة:** الخدمة تعمل لكن تحتاج حراسة إضافية ضد حالات حافة.

**المطلوب:**

**(أ) قرار `Gate::before`:**
- الوضع الحالي: دور `admin` (التشغيلي) يتجاوز كل شيء عبر `Gate::before` في `AppServiceProvider`
- المطلوب: تحديد سلوك واضح:
  - **الخيار المُوصى:** `Gate::before` لا يتجاوز الصلاحيات التي بادئتها `admin.*` أو `governance.*` — هذه تخضع فقط لخدمة الحوكمة
  - التنفيذ: إضافة فحص `Str::startsWith($ability, ['admin.', 'governance.'])` في `Gate::before` → يُرجع `null` (لا يتجاوز) للصلاحيات الحوكمية

**(ب) تحصين `canAccessPanel`:**
- إضافة فحص أن المستخدم `is_active` (موجود حالياً ✅)
- إضافة فحص أن المستخدم ليس soft-deleted (حالياً لا يُفحص صراحة — `Auth` يستبعدهم عادةً لكن يجب التأكد)

**الملفات المتأثرة:**
- `app/Providers/AppServiceProvider.php` ← تعديل `Gate::before`
- `app/Services/Governance/GovernanceAccessService.php` ← مراجعة + تحصين

**معيار القبول:**
- مستخدم بدور `admin` التشغيلي (بدون `erp_admin`) لا يستطيع دخول `/admin`
- مستخدم بدور `admin` التشغيلي يستمر في تجاوز صلاحيات API التشغيلية
- اختبار يتحقق من الفصل

---

#### الخطوة 6: إصلاح `FilamentNavigationPolicy` — حالة الحافة

**المشكلة:** إذا كانت قيمة الإعداد ليست مصفوفة غير فارغة، تُمنح الرؤية افتراضياً (open access).

**المطلوب:**
- تعديل المنطق: إذا كان المفتاح موجوداً في الإعدادات لكن قيمته ليست مصفوفة أو فارغة → **رفض** (fail-closed) بدلاً من سماح
- المجموعات غير المدرجة أصلاً في الإعدادات → تبقى مفتوحة لأي مستخدم لوحة (كما هو الحال حالياً لـ Overview, Access Governance, Governance Observability)

**الملفات المتأثرة:**
- `app/Services/Governance/FilamentNavigationPolicy.php` ← تعديل

**معيار القبول:** اختبار يتحقق من أن إعداد خاطئ (string بدل array) يؤدي لرفض الوصول.

---

### الطبقة C: توحيد طبقة Filament Resources

بعد تحصين الخدمات، نوحّد سلوك Resources.

---

#### الخطوة 7: توحيد نمط التصريح في Governance Resources

**المشكلة المكتشفة:**
- `AdminHome` والـ Widgets تستخدم `canAccessGovernancePage('Overview', 'admin.dashboard.view')` ← يفحص Navigation Group + Permission
- `UserResource` و `RoleResource` و `PermissionResource` و `DirectPermissionResource` و `EffectiveAccessResource` و `GovernanceAuditLogResource` تستخدم `canGovernance('admin.*.view')` فقط ← يفحص Permission بدون Navigation Group
- النتيجة: **تناقض** — يمكن نظرياً أن يرى المستخدم resource بدون أن يكون مجموعة التنقل مرئية له

**المطلوب:**
- توحيد كل Governance Resources لاستخدام `canAccessGovernancePage` أو ما يعادله
- بالتحديد: `canViewAny` يجب أن يفحص Navigation Group + Permission
- لا تُستخدم `ChecksFilamentNavigationGroupGate` (هذه للأقسام التشغيلية)
- الحل: تعديل `canViewAny` في كل governance resource ليستخدم:
  ```
  canAccessGovernancePage('Access Governance', 'admin.users.view')
  canAccessGovernancePage('Access Governance', 'admin.roles.view')
  canAccessGovernancePage('Access Governance', 'admin.permissions.view')
  canAccessGovernancePage('Access Governance', 'admin.direct_permissions.view')
  canAccessGovernancePage('Governance Observability', 'admin.effective_access.view')
  canAccessGovernancePage('Governance Observability', 'admin.audit.view')
  ```

**الملفات المتأثرة:**
- `app/Filament/Admin/Resources/Users/UserResource.php` ← تعديل `canViewAny`
- `app/Filament/Admin/Resources/Roles/RoleResource.php` ← تعديل
- `app/Filament/Admin/Resources/Permissions/PermissionResource.php` ← تعديل
- `app/Filament/Admin/Resources/DirectPermissions/DirectPermissionResource.php` ← تعديل
- `app/Filament/Admin/Resources/EffectiveAccess/EffectiveAccessResource.php` ← تعديل
- `app/Filament/Admin/Resources/GovernanceAuditLogs/GovernanceAuditLogResource.php` ← تعديل

**معيار القبول:**
- كل governance resource يفحص Navigation Group + Permission
- الاختبارات الحالية تمر
- اختبار جديد يتحقق من أن مستخدم بالصلاحية الصحيحة لكن بدون رؤية المجموعة → لا يرى المورد

---

#### الخطوة 8: إصلاح `DirectPermissionResource` — معالجة Soft Deletes

**المشكلة:** `UserResource` و `EffectiveAccessResource` يزيلان `SoftDeletingScope` من Query. `DirectPermissionResource` لا يفعل ذلك.

**النتيجة:** مستخدم محذوف بـ soft-delete لا يظهر في Direct Permissions لكنه قد يملك صلاحيات مباشرة لم تُسحب.

**المطلوب:**
- توحيد سلوك `DirectPermissionResource::getEloquentQuery()` مع `UserResource`
- إضافة `withoutGlobalScope(SoftDeletingScope::class)` + مؤشر بصري (badge أو لون) للمستخدمين المحذوفين

**الملفات المتأثرة:**
- `app/Filament/Admin/Resources/DirectPermissions/DirectPermissionResource.php` ← تعديل

**معيار القبول:** مستخدم soft-deleted يظهر في قائمة Direct Permissions مع مؤشر بصري.

---

#### الخطوة 9: إصلاح فلتر `panel_eligible` في `EffectiveAccessResource`

**المشكلة:** العمود `panel_eligible` يستخدم `GovernanceAccessService::canAccessPanel($record)` (صحيح). لكن `TernaryFilter::make('panel_eligible')` يستخدم `whereHas`/`whereDoesntHave` على أدوار الحوكمة فقط — لا يراعي `is_active` أو `admin.panel.access` أو `super_admin`.

**المطلوب:**
- توحيد منطق الفلتر مع منطق العرض
- استخدام subquery أو scope يُطابق المنطق الكامل في `GovernanceAccessService::canAccessPanel`
- أو: إزالة الفلتر والاكتفاء بالعمود (أبسط وأصح)

**الملفات المتأثرة:**
- `app/Filament/Admin/Resources/EffectiveAccess/EffectiveAccessResource.php` ← تعديل

**معيار القبول:** الفلتر يُطابق العمود — لا تناقض بين ما يُعرض وما يُصفّى.

---

### الطبقة D: تعزيز حماية التصعيد (`super_admin` vs `erp_admin`)

---

#### الخطوة 10: تحصين فصل `super_admin` عن `erp_admin`

**الحالة الحالية (من الاختبارات):**
- ✅ `erp_admin` لا يستطيع منح `super_admin` لمستخدم آخر
- ✅ `erp_admin` لا يستطيع تصعيد نفسه إلى `super_admin`
- ✅ `super_admin` يستطيع منح `super_admin`
- ✅ `GovernanceCatalog::assignableGovernanceRoleOptions` يُخفي `super_admin` عن غير `super_admin`
- ✅ `RoleGovernanceService` يرفض تعديل الأدوار التشغيلية

**المطلوب إضافياً:**
- **(أ)** اختبار أن `erp_admin` لا يستطيع **تعديل** صلاحيات دور `super_admin` عبر `RoleResource` (تعديل `RoleGovernanceService::syncPermissions` أو `RoleResource::canEdit` لمنع ذلك)
- **(ب)** اختبار أن `erp_admin` لا يستطيع **حذف** أو **إلغاء تفعيل** مستخدم يملك `super_admin`
- **(ج)** تدقيق: كل عملية حوكمة على مستخدم `super_admin` تُسجَّل بتفصيل إضافي في `GovernanceAuditLogger`

**الملفات المتأثرة:**
- `app/Services/Governance/RoleGovernanceService.php` ← مراجعة + تحصين
- `app/Services/Governance/UserGovernanceService.php` ← مراجعة + تحصين
- `app/Filament/Admin/Resources/Roles/RoleResource.php` ← مراجعة
- `tests/Feature/Governance/GovernanceEscalationGuardTest.php` ← إضافة اختبارات

**معيار القبول:**
- `erp_admin` لا يستطيع تعديل دور `super_admin`
- `erp_admin` لا يستطيع حذف/إلغاء تفعيل مستخدم `super_admin`
- كل الاختبارات الحالية (9 في `GovernanceEscalationGuardTest`) تمر + اختبارات جديدة

---

### الطبقة E: الاختبارات والتحقق النهائي

---

#### الخطوة 11: تعزيز تغطية الاختبارات لنطاق Phase 1

**الحالة الحالية:** 55+ اختبار في 13 ملف. تغطية جيدة لكن فيها فجوات محددة.

**الاختبارات المطلوبة الجديدة:**

| الاختبار | ماذا يتحقق | الملف |
|----------|-----------|-------|
| تجميد القاموس | 137 مفتاح ثابتة | `PermissionDictionaryFreezeTest.php` ← جديد |
| `enabled_sections` | الأقسام المعطّلة مخفية | `SidebarNavigationMatrixTest.php` ← إضافة |
| تعطيل الصلاحيات المؤقتة | المورد يُرجع 403 | `GovernanceTemporaryPermissionTest.php` ← إضافة |
| `Gate::before` لا يتجاوز الحوكمة | `admin` التشغيلي لا يدخل `/admin` | `AdminPanelAccessTest.php` ← تعديل |
| توحيد Navigation Group | resource بدون رؤية مجموعة → مخفي | `GovernanceObservabilityAccessTest.php` ← إضافة |
| `DirectPermissionResource` soft-delete | مستخدم محذوف يظهر | `DirectPermissionGovernanceTest.php` ← إضافة |
| `panel_eligible` filter | الفلتر يطابق العمود | `GovernanceObservabilityAccessTest.php` ← إضافة |
| حماية `super_admin` من `erp_admin` | لا تعديل/حذف | `GovernanceEscalationGuardTest.php` ← إضافة |
| `FilamentNavigationPolicy` fail-closed | إعداد خاطئ → رفض | `FilamentNavigationPolicyTest.php` ← إضافة |

**معيار القبول:** جميع الاختبارات الحالية (55+) + الاختبارات الجديدة (9+) تمر.

---

#### الخطوة 12: تشغيل مجموعة الاختبارات الكاملة والتحقق

**المطلوب:**
- تشغيل `php artisan test --filter=Governance` — كل الاختبارات تمر
- تشغيل `php artisan test --filter=Auth` — لا regregssion في اختبارات الصلاحيات التشغيلية
- فحص يدوي: تسجيل دخول بـ `erp_admin` → التنقل يعرض فقط Overview + Access Governance + Governance Observability
- فحص يدوي: تسجيل دخول بـ `super_admin` → نفس المجموعات (الأقسام التشغيلية معطّلة بـ `enabled_sections`)
- فحص يدوي: مستخدم `admin` التشغيلي بدون دور حوكمة → `/admin` يُرجع 403

---

## 4) مكونات الحوكمة التي يجب بناؤها / تعديلها / تركها

### 4.1 — ملخص كامل

| المكوّن | الإجراء | الخطوة |
|---------|---------|--------|
| `PermissionDictionaryFreezeTest` | **إنشاء** | 1 |
| `config/governance.php` | **تعديل** (إضافة `enabled_sections`) | 2 |
| `ChecksFilamentNavigationGroupGate` | **تعديل** (فحص `enabled_sections`) | 2 |
| `GovernanceTemporaryPermissionResource` | **تعديل** (تعطيل `canAccess`) | 3 |
| `CommissionPolicy` | **تعديل** (`sales_manager` → `sales_leader`) | 4 |
| `DepositPolicy` | **تعديل** (`sales_manager` → `sales_leader`) | 4 |
| `AppServiceProvider` | **تعديل** (`Gate::before` لا يتجاوز `admin.*`/`governance.*`) | 5 |
| `GovernanceAccessService` | **مراجعة** + تحصين soft-delete | 5 |
| `FilamentNavigationPolicy` | **تعديل** (fail-closed للإعداد الخاطئ) | 6 |
| `UserResource` | **تعديل** (`canViewAny` → `canAccessGovernancePage`) | 7 |
| `RoleResource` | **تعديل** (نفس التوحيد) | 7 |
| `PermissionResource` | **تعديل** (نفس التوحيد) | 7 |
| `DirectPermissionResource` | **تعديل** (توحيد + soft-delete) | 7, 8 |
| `EffectiveAccessResource` | **تعديل** (توحيد + filter) | 7, 9 |
| `GovernanceAuditLogResource` | **تعديل** (نفس التوحيد) | 7 |
| `RoleGovernanceService` | **تحصين** (حماية `super_admin`) | 10 |
| `UserGovernanceService` | **تحصين** (حماية `super_admin`) | 10 |
| اختبارات (9+ اختبارات جديدة) | **إنشاء** | 11 |

### 4.2 — ما لا يُلمَس

| المكوّن | السبب |
|---------|-------|
| `GovernanceTemporaryPermissionService` | الخدمة الداخلية تبقى — فقط UI يُعطّل |
| `GovernanceAuditLogger` | يعمل بشكل صحيح — لا تغيير |
| `GovernanceCatalog` | يعمل بشكل صحيح — لا تغيير |
| `EffectiveAccessSnapshotService` | يعمل بشكل صحيح — لا تغيير |
| `AdminPanelProvider` | يعمل بشكل صحيح — لا تغيير |
| `AdminHome` | يعمل بشكل صحيح — لا تغيير |
| 3 Widgets | تعمل بشكل صحيح — لا تغيير |
| كل الـ Resources خارج Access Governance و Governance Observability | خارج النطاق |
| `config/ai_capabilities.php` | مُجمَّد — لا تعديل |
| `config/user_types.php` | مُجمَّد — لا تعديل |
| `routes/api.php` | تشغيلي — لا يُلمَس |
| كل Controllers/Services/Policies التشغيلية (باستثناء Commission + Deposit Policies) | تشغيلي — لا يُلمَس |

---

## 5) الاعتماديات

### 5.1 — اعتماديات تقنية بين الخطوات

```
الخطوة 1 (تجميد القاموس)
    │  مستقل — لا اعتماديات
    ▼
الخطوة 2 (enabled_sections)
    │  يعتمد على: فهم ChecksFilamentNavigationGroupGate
    ▼
الخطوة 3 (تعطيل temp permissions)
    │  مستقل — لكن يجب بعد الخطوة 2 (لضمان عدم ظهور القسم)
    ▼
الخطوة 4 (إصلاح sales_manager)
    │  مستقل — لكن يجب تشغيل اختبارات بعده
    ▼
الخطوة 5 (تحصين GovernanceAccessService + Gate::before)
    │  يعتمد على: الخطوات 1-4 مكتملة
    │  سبب: تغيير Gate::before يؤثر على كل فحوصات can()
    ▼
الخطوة 6 (إصلاح FilamentNavigationPolicy)
    │  يعتمد على: الخطوة 5 (GovernanceAccessService محصّن)
    ▼
الخطوة 7 (توحيد Resources)
    │  يعتمد على: الخطوات 5+6 (الخدمات محصّنة أولاً)
    ▼
الخطوة 8 (DirectPermissionResource soft-delete)
    │  يعتمد على: الخطوة 7 (التوحيد أولاً)
    ▼
الخطوة 9 (EffectiveAccessResource filter)
    │  يعتمد على: الخطوة 7
    ▼
الخطوة 10 (حماية super_admin)
    │  يعتمد على: الخطوات 5+7 (الخدمات + Resources متسقة)
    ▼
الخطوة 11 (اختبارات جديدة)
    │  يعتمد على: كل الخطوات 1-10
    ▼
الخطوة 12 (تحقق نهائي)
    │  يعتمد على: كل شيء
```

### 5.2 — اعتماديات خارجية

| الاعتمادية | الحالة |
|-----------|--------|
| Filament 5.4 | ✅ مثبّت |
| Spatie Permission | ✅ مثبّت |
| `config/ai_capabilities.php` (137 صلاحية) | ✅ مُجمَّد |
| `config/governance.php` | ✅ موجود — سيُعدَّل |
| `config/user_types.php` | ✅ مُجمَّد |
| قاعدة بيانات مع جداول Spatie | ✅ موجودة |
| `governance_audit_logs` table | ✅ موجود |
| `governance_temporary_permissions` table | ✅ موجود |
| `users` table مع soft deletes | ✅ موجود |

### 5.3 — لا هجرات جديدة في Phase 1

Phase 1 لا يتطلب أي migration. كل الجداول المطلوبة موجودة.

---

## 6) معايير القبول

### 6.1 — معايير آلية (يجب أن تمر كلها)

| # | المعيار | أمر التحقق |
|---|---------|-----------|
| 1 | اختبار تجميد القاموس يمر | `php artisan test --filter=PermissionDictionaryFreezeTest` |
| 2 | اختبارات `enabled_sections` تمر | `php artisan test --filter=SidebarNavigationMatrixTest` |
| 3 | الصلاحيات المؤقتة معطّلة | `php artisan test --filter=GovernanceTemporaryPermissionTest` |
| 4 | لا مراجع `sales_manager` | `grep -r "sales_manager" app/` = 0 نتائج |
| 5 | `Gate::before` لا يتجاوز الحوكمة | `php artisan test --filter=AdminPanelAccessTest` |
| 6 | `FilamentNavigationPolicy` fail-closed | `php artisan test --filter=FilamentNavigationPolicyTest` |
| 7 | Resources موحّدة | `php artisan test --filter=GovernanceObservabilityAccessTest` |
| 8 | حماية `super_admin` | `php artisan test --filter=GovernanceEscalationGuardTest` |
| 9 | لا regression | `php artisan test --filter=Governance` — كل 64+ اختبار يمر |
| 10 | لا regression تشغيلي | `php artisan test --filter=Auth` — كل الاختبارات تمر |

### 6.2 — معايير يدوية (قبل إغلاق Phase 1)

| # | المعيار |
|---|---------|
| 1 | `erp_admin` يرى: Overview, Access Governance, Governance Observability فقط |
| 2 | `erp_admin` لا يرى: Credit, Accounting, Projects, Sales, HR, Marketing, Inventory, AI, Workflow |
| 3 | `super_admin` يرى نفس المجموعات (لأن `enabled_sections` يحكم) |
| 4 | `auditor_readonly` يرى: Overview, Access Governance, Governance Observability (قراءة فقط) |
| 5 | مستخدم `admin` تشغيلي بدون دور حوكمة → `/admin` = 403 |
| 6 | `erp_admin` لا يستطيع تعديل دور/حذف مستخدم `super_admin` |
| 7 | سجل التدقيق يسجّل كل عملية |
| 8 | لا تظهر "Temporary Permissions" في التنقل لأي مستخدم |

---

## 7) ما يجب منعه في هذه المرحلة

| # | الممنوع | السبب | عاقبة الانتهاك |
|---|---------|-------|---------------|
| 1 | إضافة أي صلاحية جديدة إلى `definitions` | القاموس مُجمَّد (Phase 0) | اختبار `PermissionDictionaryFreezeTest` يفشل |
| 2 | تفعيل أي قسم تشغيلي في `enabled_sections` | خارج نطاق Phase 1 | يجب مراجعة وإزالة |
| 3 | إنشاء Resource جديد لأي قسم تشغيلي | خارج النطاق | يُرفض في المراجعة |
| 4 | تفعيل الصلاحيات المؤقتة | ما بعد MVP | يُرفض |
| 5 | إنشاء أوامر Override / Force | ما بعد MVP | يُرفض |
| 6 | تعديل `routes/api.php` | تشغيلي — خارج Filament | يُرفض |
| 7 | تعديل Controllers/Services التشغيلية | خارج Filament | يُرفض (باستثناء Policies في الخطوة 4) |
| 8 | إنشاء لوحة Filament ثانية | قرار معماري ثابت | يُرفض فوراً |
| 9 | تعديل `config/ai_capabilities.php` | مُجمَّد | يُرفض |
| 10 | إنشاء هجرات جديدة | لا حاجة لها في Phase 1 | يُرفض إلا بمبرر استثنائي |

---

## 8) المخاطر وضوابطها

### 8.1 — مخاطر تقنية

| # | الخطر | الاحتمال | التأثير | الضابط |
|---|-------|---------|---------|--------|
| R1 | تغيير `Gate::before` يكسر الصلاحيات التشغيلية | **متوسط** | **عالي** | تشغيل `php artisan test --filter=Auth` فوراً بعد التعديل — أي فشل = تراجع |
| R2 | توحيد `canViewAny` يُخفي resources عن مستخدمين مصرّح لهم | **منخفض** | **متوسط** | مراجعة المنطق قبل التطبيق + اختبارات HTTP لكل resource |
| R3 | `enabled_sections` يُخفي أقساماً يحتاجها المستخدمون حالياً | **منخفض** | **عالي** | Phase 1 يُخفي فقط الأقسام التشغيلية — المستخدمون التشغيليون لا يستخدمون `/admin` أصلاً |
| R4 | تعطيل الصلاحيات المؤقتة يكسر `GovernanceAccessService::hasPermission` | **منخفض** | **منخفض** | الخدمة تبقى — فقط UI معطّل. المنطق الداخلي لا يتأثر |
| R5 | إصلاح `sales_manager` يكشف مشاكل أخرى في Policies | **متوسط** | **منخفض** | grep كامل قبل الإصلاح + اختبارات |

### 8.2 — مخاطر تنظيمية

| # | الخطر | الضابط |
|---|-------|--------|
| R6 | فقدان قرار `Gate::before` بدون توثيق | توثيق القرار في هذا المستند + تعليق في الكود |
| R7 | الانزلاق نحو بناء أقسام تشغيلية | قائمة الممنوعات في القسم 7 + مراجعة الكود |
| R8 | إضافة صلاحيات "بالتسلل" | اختبار التجميد يمنع ذلك آلياً |

---

## 9) القرار الجاهز للانتقال إلى Phase 2

### 9.1 — شروط إغلاق Phase 1

> **لا يُفتح Phase 2 (الائتمان) حتى تتحقق كل الشروط التالية:**

| # | الشرط | التحقق |
|---|-------|--------|
| 1 | كل معايير القبول الآلية (القسم 6.1) تمر | `php artisan test` |
| 2 | كل معايير القبول اليدوية (القسم 6.2) مؤكدة | فحص يدوي موثّق |
| 3 | لا تغييرات معلّقة في الملفات المحددة في القسم 4.1 | `git status` نظيف |
| 4 | سجل التدقيق يُسجّل كل عملية حوكمة | فحص يدوي |
| 5 | `enabled_sections` يحتوي فقط على المجموعات الثلاث الحوكمية | فحص `config/governance.php` |
| 6 | لا مراجع ميتة (`sales_manager`) | `grep -r` |
| 7 | وثيقة Phase 1 مُحدَّثة بالنتائج الفعلية | هذا المستند |

### 9.2 — ماذا يفتح Phase 2

عند نجاح Phase 1:
- يُضاف `'Credit Oversight'` إلى `enabled_sections`
- تُفعَّل Resources: `CreditBookingResource`, `TitleTransferResource`, `ClaimFileResource`, `CreditNotificationResource`
- تُفعَّل Page: `CreditOverview`
- تُفعَّل Widget: `CreditOverviewStatsWidget`
- الصلاحيات المستخدمة: 10 صلاحيات من `credit.*` (مجمّدة في القاموس)
- الدور المستهدف: `credit_admin`
- **لا صلاحيات جديدة. لا هجرات. لا Resources جديدة.** فقط تفعيل ما هو مبني ومعطّل.

### 9.3 — الجدول الزمني المقترح

| الطبقة | الخطوات | الجهد التقديري |
|--------|---------|---------------|
| A: بوابات Phase 0 | 1–4 | جلسة عمل واحدة |
| B: تحصين الخدمات | 5–6 | جلسة عمل واحدة |
| C: توحيد Resources | 7–9 | جلسة عمل واحدة |
| D: حماية التصعيد | 10 | جلسة عمل واحدة |
| E: اختبارات + تحقق | 11–12 | جلسة عمل واحدة |
| **الإجمالي** | **12 خطوة** | **~5 جلسات عمل** |

---

> **نهاية خطة Phase 1**
> **لا كود. لا هياكل. لا هجرات.**
> **هذه خطة تنفيذ جاهزة — تنتظر الأمر بالبدء.**
