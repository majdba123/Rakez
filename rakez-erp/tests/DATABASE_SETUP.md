# Database Setup for Testing

## قاعدة البيانات: MySQL

المشروع يستخدم **MySQL** كقاعدة بيانات أساسية. الاختبارات حالياً تستخدم **SQLite** بشكل افتراضي (أسرع)، لكن يمكن تغييرها لاستخدام MySQL.

---

## إعداد MySQL للاختبارات

### 1. إنشاء قاعدة بيانات للاختبارات

```sql
CREATE DATABASE rakez_erp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. إعداد متغيرات البيئة

#### Option A: استخدام `.env.testing` (مُوصى به)

أنشئ ملف `.env.testing` في جذر المشروع:

```env
APP_NAME="Rakez ERP Testing"
APP_ENV=testing
APP_KEY=base64:your-test-key-here
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rakez_erp_test
DB_USERNAME=root
DB_PASSWORD=your-password

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

Laravel سيقرأ `.env.testing` تلقائياً عند تشغيل الاختبارات.

#### Option B: تعديل `phpunit.xml`

في ملف `phpunit.xml`، غيّر:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="rakez_erp_test"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value="your-password"/>
```

### 3. تشغيل Migrations

```bash
# مع .env.testing
php artisan migrate --env=testing

# أو مباشرة
php artisan migrate --database=mysql --env=testing
```

---

## استخدام SQLite للاختبارات (افتراضي - أسرع)

الاختبارات حالياً تستخدم SQLite بشكل افتراضي (في `phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**مميزات SQLite:**
- ✅ أسرع بكثير
- ✅ لا يحتاج إعداد
- ✅ يعمل في الذاكرة (لا يحتاج ملفات)

**عيوب SQLite:**
- ⚠️ بعض migrations قد لا تعمل (مثل `MODIFY COLUMN`)
- ⚠️ قد يكون هناك اختلافات في السلوك عن MySQL

**ملاحظة:** تم إصلاح migration `2025_12_31_000005_add_ready_status_to_contracts.php` ليدعم كلا النظامين.

---

## تشغيل الاختبارات

### مع MySQL:

```bash
# 1. أنشئ .env.testing مع إعدادات MySQL
# 2. شغّل migrations
php artisan migrate --env=testing

# 3. شغّل الاختبارات
php artisan test --filter AI
```

### مع SQLite (افتراضي):

```bash
# مباشرة بدون إعداد
php artisan test --filter AI
```

---

## ملاحظات مهمة

1. **Migration Compatibility**: 
   - ✅ Migration `2025_12_31_000005_add_ready_status_to_contracts.php` يدعم:
     - MySQL: يستخدم `MODIFY COLUMN`
     - SQLite: يتخطى migration (enum handled at app level)

2. **Test Isolation**: 
   - جميع الاختبارات تستخدم `RefreshDatabase` لضمان العزل الكامل
   - كل test يعيد إنشاء قاعدة البيانات من الصفر

3. **Performance**: 
   - **MySQL**: أبطأ (~6-12 ثانية لجميع الاختبارات) لكن أكثر واقعية
   - **SQLite**: أسرع (~1-2 ثانية) لكن قد يكون هناك اختلافات

---

## استكشاف الأخطاء

### خطأ: "SQLSTATE[HY000]: General error: 1 near "MODIFY": syntax error"

**السبب:** استخدام SQLite مع migration يستخدم `MODIFY COLUMN`

**الحل:** 
- ✅ تم إصلاح migration ليتخطى SQLite تلقائياً
- أو استخدم MySQL للاختبارات

### خطأ: "Access denied for user"

**السبب:** بيانات اعتماد MySQL غير صحيحة

**الحل:** 
- تحقق من `.env.testing`
- أو تحقق من `phpunit.xml`
- تأكد من أن MySQL يعمل وأن المستخدم لديه صلاحيات

### خطأ: "Database connection [${DB_TEST_CONNECTION:-sqlite}] not configured"

**السبب:** phpunit.xml يحتوي على syntax غير مدعوم

**الحل:** 
- ✅ تم إصلاحه - استخدم قيمة مباشرة في `phpunit.xml`

---

## التوصيات

### ✅ للإنتاج والـ Development:
- استخدم **MySQL** للاختبارات لمطابقة بيئة الإنتاج
- أنشئ `.env.testing` مع إعدادات MySQL

### ✅ للـ CI/CD:
- يمكن استخدام **SQLite** للسرعة
- أو استخدم MySQL في Docker container

### ✅ للـ Local Development:
- **SQLite**: أسرع، جيد للتطوير السريع
- **MySQL**: أكثر واقعية، جيد قبل الـ deployment

---

## الإعداد السريع لـ MySQL

```bash
# 1. أنشئ قاعدة البيانات
mysql -u root -p -e "CREATE DATABASE rakez_erp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. أنشئ .env.testing
cp .env .env.testing
# عدّل DB_CONNECTION=mysql و DB_DATABASE=rakez_erp_test

# 3. شغّل migrations
php artisan migrate --env=testing

# 4. شغّل الاختبارات
php artisan test --filter AI
```
