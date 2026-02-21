# مراجعة معمارية: المبيعات، التسويق، المحاسبة، والتقارير

مراجعة معمارية عالية المستوى لوحدات **المبيعات (Sales)**، **التسويق (Marketing)**، **المحاسبة (Accounting)**، ومقارنتها مع **جميع التقارير (Reports)** في نظام Rakez ERP.

---

## ١. نظرة عامة على الوحدات

| الجانب | المبيعات | التسويق | المحاسبة | التقارير (الكل) |
|--------|----------|---------|----------|------------------|
| **بادئة المسارات** | `sales` | `marketing` | `accounting` | موزعة (انظر أدناه) |
| **الأدوار** | `sales`, `sales_leader`, `admin` (+ `accounting` للتحليلات) | `marketing`, `admin` | `accounting`, `admin` | نفس الوحدة المالكة |
| **كونترولر تقارير مخصص** | لا | نعم (`MarketingReportController`) | لا | HR + التسويق فقط |
| **بيانات شبيهة بالتقارير** | لوحة تحكم + Insights + Analytics API | لوحة تحكم + تقارير + تصدير | لوحة تحكم + قوائم | تقارير HR + تقارير التسويق |

---

## ٢. وحدة المبيعات (Sales)

### ٢.١ الهيكل

```
app/
├── Http/
│   ├── Controllers/Sales/
│   │   ├── SalesDashboardController      # مؤشرات لوحة التحكم
│   │   ├── SalesProjectController        # المشاريع، الوحدات، التعيينات
│   │   ├── SalesReservationController    # الحجوزات، التأكيد، الإلغاء، القسيمة
│   │   ├── SalesTargetController         # الأهداف (خاصتي، الفريق)
│   │   ├── SalesAttendanceController     # جداول الحضور
│   │   ├── SalesInsightsController       # الوحدات المباعة، الودائع، ملخص العمولات
│   │   ├── MarketingTaskController       # مهام التسويق (في سياق المبيعات)
│   │   ├── WaitingListController
│   │   ├── NegotiationApprovalController
│   │   └── PaymentPlanController
│   ├── Requests/Sales/                   # StoreReservation, StoreTarget، إلخ
│   └── Resources/Sales/                  # موارد JSON للـ API
├── Services/Sales/
│   ├── SalesDashboardService
│   ├── SalesAnalyticsService             # مؤشرات، وحدات مباعة، ودائع، عمولات
│   ├── SalesReservationService
│   ├── SalesTargetService
│   ├── SalesProjectService
│   ├── SalesAttendanceService
│   ├── SalesNotificationService
│   ├── MarketingTaskService
│   ├── DepositService, PaymentPlanService, CommissionService
│   ├── NegotiationApprovalService, WaitingListService
│   ├── PdfGeneratorService, ReservationVoucherService
│   └── ...
```

**API (تحت `sales`):**  
`dashboard`, `sold-units`, `deposits/*`, `projects`, `reservations`, `targets`, `attendance`, `marketing-tasks`, `waiting-list`, `negotiation`, `payment-plan`, إلخ.

**التحليلات (نفس البادئة، كونترولر منفصل):**  
`Api\SalesAnalyticsController`: `analytics/dashboard`, `analytics/sold-units`, `analytics/deposits/stats/project/{id}`, `analytics/commissions/stats/employee/{id}`, `analytics/commissions/monthly-report`.

- **لا يوجد** كونترولر مخصص `SalesReportController`.
- بيانات "التقارير" تُعرض عبر **SalesDashboardController**، **SalesInsightsController**، و **SalesAnalyticsController** (مؤشرات، وحدات مباعة، ودائع، عمولات).

---

## ٣. وحدة التسويق (Marketing)

### ٣.١ الهيكل

```
app/
├── Http/
│   ├── Controllers/Marketing/
│   │   ├── MarketingDashboardController
│   │   ├── MarketingProjectController
│   │   ├── DeveloperMarketingPlanController
│   │   ├── EmployeeMarketingPlanController
│   │   ├── ExpectedSalesController
│   │   ├── MarketingBudgetDistributionController
│   │   ├── MarketingTaskController
│   │   ├── TeamManagementController
│   │   ├── LeadController
│   │   ├── MarketingReportController     # ← تقارير مخصصة
│   │   └── MarketingSettingsController
│   ├── Requests/Marketing/
│   └── Resources/Marketing/
├── Services/Marketing/
│   ├── MarketingDashboardService
│   ├── MarketingProjectService
│   ├── DeveloperMarketingPlanService
│   ├── EmployeeMarketingPlanService
│   ├── MarketingBudgetCalculationService
│   ├── ExpectedSalesService
│   ├── MarketingTaskService
│   ├── TeamManagementService
│   ├── MarketingNotificationService
│   └── ...
├── Events/Marketing/
├── Listeners/Marketing/
resources/views/marketing/               # plan_export, developer_plan_export
```

**API (تحت `marketing`):**  
`dashboard`, `projects`, `developer-plans`, `employee-plans`, `expected-sales`, `budget-distributions`, `tasks`, `teams`, `leads`, **`reports/*`**, `settings`.

### ٣.٢ التقارير (التسويق)

**الكونترولر:** `MarketingReportController`  
**الصلاحية:** `marketing.reports.view`

| المسار | الطريقة | الوصف |
|--------|---------|--------|
| `reports/project/{projectId}` | GET | أداء المشروع (الليادات، الحجوزات المتوقعة، التحويل) |
| `reports/budget` | GET | تقرير الميزانية (إجمالي، حسب المشروع، حسب نوع الخطة) |
| `reports/expected-bookings` | GET | إجمالي الحجوزات المتوقعة |
| `reports/employee/{userId}` | GET | أداء الموظف |
| `reports/export/{planId}` | GET | تصدير الخطة (PDF/Excel/CSV) |
| `reports/developer-plan/export/{contractId}` | GET | تصدير خطة المطور |

التقارير **داخل الوحدة** (لا توجد خدمة تقارير منفصلة؛ الكونترولر يستخدم النماذج وخدمات التصدير).

---

## ٤. وحدة المحاسبة (Accounting)

### ٤.١ الهيكل

```
app/
├── Http/
│   └── Controllers/Accounting/
│       ├── AccountingDashboardController   # مقاييس لوحة التحكم (شبيهة بالتقارير)
│       ├── AccountingNotificationController
│       ├── AccountingCommissionController  # وحدات مباعة، عمولات، توزيعات
│       ├── AccountingDepositController     # معلق، تأكيد، متابعة، استرداد
│       ├── AccountingSalaryController      # رواتب، توزيعات، اعتماد، مدفوع
│       └── AccountingConfirmationController # تأكيدات الدفعة الأولى (قديمة)
├── Services/Accounting/
│   ├── AccountingDashboardService          # getDashboardMetrics (نفس المؤشرات كالمبيعات)
│   ├── AccountingCommissionService
│   ├── AccountingDepositService
│   ├── AccountingSalaryService
│   └── AccountingNotificationService
```

**API (تحت `accounting`):**  
`dashboard`, `notifications`, `marketers`, `sold-units`, `commissions`, `deposits/*`, `salaries/*`, `pending-confirmations`, `confirm/*`, `confirmations/history`.

- **لا يوجد** كونترولر مخصص `AccountingReportController`.
- بيانات "التقارير" هي **مقاييس لوحة التحكم** (وحدات مباعة، ودائع، عمولات، إلخ) و**نقاط نهاية القوائم**. الصلاحيات: `accounting.dashboard.view`, `accounting.sold-units.view`, إلخ.

---

## ٥. التقارير (الكل) – مقارنة مع الوحدات الثلاث

**لا توجد وحدة "تقارير موحدة" واحدة.** التقارير **حسب القسم**:

| القسم | كونترولر التقارير / نقطة الدخول | بادئة المسار | الصلاحية |
|-------|----------------------------------|--------------|----------|
| **HR** | `HrReportController` | `hr/reports/*` | `hr.reports.view`, `hr.reports.print` |
| **التسويق** | `MarketingReportController` | `marketing/reports/*` | `marketing.reports.view` |
| **المبيعات** | — | — | لوحة تحكم + insights + analytics (لا مساحة `reports`) |
| **المحاسبة** | — | — | لوحة تحكم + قوائم (لا مساحة `reports`) |

### ٥.١ تقارير الموارد البشرية (HrReportController + HrReportService)

**الخدمة:** `App\Services\HR\HrReportService`  
**الكونترولر:** `App\Http\Controllers\HR\HrReportController`

| المسار | الوصف |
|--------|--------|
| `hr/reports/team-performance` | أداء الفريق الشهري (سنة، شهر) |
| `hr/reports/marketer-performance` | أداء المسوق (اختياري team_id)، JSON |
| `hr/reports/marketer-performance/pdf` | نفس التقرير كـ PDF |
| `hr/reports/employee-count` | تقرير عدد الموظفين |
| `hr/reports/expiring-contracts` | العقود المنتهية قريباً |
| `hr/reports/expiring-contracts/pdf` | نفس التقرير كـ PDF |
| `hr/reports/ended-contracts` | العقود المنتهية |

**عروض PDF:** `resources/views/pdfs/marketer_performance_report.blade.php`, `expiring_contracts_report.blade.php`.

### ٥.٢ تقارير التسويق

انظر **§٣.٢**. كلها تحت `marketing/reports/*`.

### ٥.٣ "التقارير" في المبيعات

- **لوحة المبيعات:** `sales/dashboard` (SalesDashboardController + SalesDashboardService).
- **Insights:** `sales/sold-units`, `sales/deposits/management`, `sales/deposits/follow-up`, ملخص العمولات (SalesInsightsController).
- **Analytics API:** `sales/analytics/dashboard`, `sales/analytics/sold-units`, `sales/analytics/commissions/monthly-report`, إلخ (SalesAnalyticsController + SalesAnalyticsService).

لا توجد مسارات `sales/reports/*`.

### ٥.٤ "التقارير" في المحاسبة

- **لوحة التحكم:** `accounting/dashboard` (AccountingDashboardController + AccountingDashboardService) مع نطاق تاريخ.
- **القوائم:** sold-units, commissions, deposits, salaries (كلّ له كونترولره).

لا توجد مسارات `accounting/reports/*`.

---

## ٦. المقارنة المعمارية (المبيعات، التسويق، المحاسبة مقابل التقارير)

| البعد | المبيعات | التسويق | المحاسبة | التقارير (الكل) |
|-------|----------|---------|----------|------------------|
| **الطبقات** | Controller → Service → Models | Controller → Service / Models + Export | Controller → Service → Models | HR: Controller → HrReportService؛ التسويق: Controller → Models/Export |
| **كونترولر تقارير** | لا | نعم | لا | فقط HR + التسويق |
| **خدمة تقارير** | لا (التحليلات في SalesAnalyticsService) | لا (المنطق في الكونترولر + التصدير) | لا (المقاييس في AccountingDashboardService) | نعم فقط لـ HR (HrReportService) |
| **التصدير** | PDF للقسيمة (الحجز) | PDF/Excel/CSV (الخطط، خطة المطور) | — | HR: PDF (أداء المسوق، عقود منتهية) |
| **نمط الصلاحيات** | `sales.*` (dashboard, projects, reservations، إلخ) | `marketing.*` + `marketing.reports.view` | `accounting.*` | `hr.reports.view` / `marketing.reports.view` |
| **البيانات المشتركة** | الحجوزات، الوحدات، الودائع، العمولات (مشتركة مع المحاسبة/الائتمان) | المشاريع، الخطط، الميزانية، الليادات | نفس الحجوزات/الودائع/العمولات | HR يستخدم Teams, Users, Reservations؛ التسويق يستخدم Plans, ExpectedBooking |

---

## ٧. خلاصة المراجعة المعمارية

- **المبيعات:** تركيز تشغيلي (حجوزات، أهداف، حضور، مهام). "التقارير" = لوحة تحكم + insights + Analytics API؛ لا كونترولر تقارير مخصص.
- **التسويق:** CRUD كامل (مشاريع، خطط، ميزانية، مهام، ليدات) **بالإضافة إلى** كونترولر تقارير مخصص **MarketingReportController** لتقارير المشروع/الميزانية/الموظف وتصدير الخطط.
- **المحاسبة:** تركيز تشغيلي (عمولات، ودائع، رواتب، تأكيدات). "التقارير" = مقاييس لوحة التحكم ونقاط نهاية القوائم؛ لا كونترولر تقارير.
- **التقارير (الكل):** مُنفذة فقط تحت **HR** (`hr/reports/*` مع HrReportService) و**التسويق** (`marketing/reports/*`). المبيعات والمحاسبة تقدم بيانات شبيهة بالتقارير عبر **لوحة التحكم ونقاط التحليلات/القوائم** فقط.

إذا رغبت في مركز تقارير موحد (مثلاً صفحة أدمن أو API تعرض أو تجمع كل أنواع التقارير)، فيجب إضافتها فوق هذه المسارات والصلاحيات الحالية.

---

*تمت المراجعة بناءً على هيكل المشروع في `rakez-erp`.*
