# منطق بيانات ملفات المطالبة والوحدات المباعة

## لماذا تظهر "لا توجد وحدات مباعة بدون ملف مطالبة"؟

الواجهة تعرض **الوحدات المباعة (بدون ملف مطالبة)** للمشروع الحالي. هذه القائمة تعتمد على:

1. **مرشحو ملف المطالبة (Candidates)** من الـ API: `GET /api/accounting/claim-files/candidates?per_page=500`
2. المرشح = **حجز مبيعات** (`sales_reservation`) بحالة **مباع** (`credit_status = 'sold'`) و**ليس له ملف مطالبة** (لا سجل في `claim_files` ولا في `claim_file_reservations` للملفات المجمعة).
3. الواجهة تُفلتر المرشحين حسب **المشروع (العقد)** — مثلاً للمسار `developers/8/project/37` تُعرض فقط المرشحون الذين `contract_id = 37`.

---

## الجداول المعنية

| الجدول | الوصف |
|--------|--------|
| `sales_reservations` | الحجوزات؛ العمود `credit_status` = `'sold'` يعني الوحدة مباعة. `contract_id` يربط الحجز بالمشروع (العقد). |
| `claim_files` | ملف مطالبة فردي؛ كل سجل مرتبط بـ `sales_reservation_id` واحد. |
| `claim_file_reservations` | جدول ربط لملفات المطالبة **المجمعة**؛ يربط `claim_file_id` بعدة `sales_reservation_id`. |

**مرشح لإنشاء ملف مطالبة** = حجز حيث:
- `credit_status = 'sold'`
- لا يوجد سجل في `claim_files` حيث `sales_reservation_id = هذا الحجز`
- وهذا الحجز غير موجود في `claim_file_reservations` لأي ملف مجمع.

---

## استعلامات للتحقق من البيانات

يمكنك تشغيلها في `php artisan tinker` أو في قاعدة البيانات:

```php
use App\Models\SalesReservation;
use App\Models\ClaimFile;
use Illuminate\Support\Facades\DB;

// عدد الحجوزات المباعة (sold)
$soldTotal = SalesReservation::where('credit_status', 'sold')->count();

// عدد الحجوزات المباعة التي لها ملف مطالبة فردي
$withClaimFile = SalesReservation::where('credit_status', 'sold')
    ->whereHas('claimFile')->count();

// عدد المرشحين (مباع وبدون ملف مطالبة فردي وليس في ملف مجمع)
$candidates = SalesReservation::where('credit_status', 'sold')
    ->whereDoesntHave('claimFile')
    ->whereNotIn('id', DB::table('claim_file_reservations')->select('sales_reservation_id'))
    ->count();

// نفس الأعداد لكن لمشروع معين (مثلاً contract_id = 37)
$contractId = 37;
$soldForContract = SalesReservation::where('credit_status', 'sold')->where('contract_id', $contractId)->count();
$candidatesForContract = SalesReservation::where('credit_status', 'sold')
    ->where('contract_id', $contractId)
    ->whereDoesntHave('claimFile')
    ->whereNotIn('id', DB::table('claim_file_reservations')->select('sales_reservation_id'))
    ->count();

echo "إجمالي مباع: $soldTotal\n";
echo "لها ملف مطالبة: $withClaimFile\n";
echo "مرشحون (بدون ملف): $candidates\n";
echo "لمشروع $contractId - مباع: $soldForContract، مرشحون: $candidatesForContract\n";
```

---

## السيدر (Seeder)

- **قبل التعديل:** كان `CreditSeeder` ينشئ ملف مطالبة **لكل** حجز مباع، فكان عدد المرشحين = 0 فتبدو الشاشة فارغة.
- **بعد التعديل:** ينشئ السيدر ملف مطالبة لنصف الحجوزات المباعة فقط؛ النصف الآخر يبقى **مرشحاً** (وحدات مباعة بدون ملف مطالبة) حتى تظهر بيانات للاختبار في الواجهة.

بعد إعادة تشغيل السيدر سترى مرشحين في القائمة العامة، وقد تظهر وحدات للمشروع 37 إذا وُجدت حجوزات مباعة لهذا العقد بدون ملف مطالبة.

```bash
php artisan db:seed --class=CreditSeeder
```

أو إعادة البذور الكاملة:

```bash
php artisan migrate:fresh --seed
```
