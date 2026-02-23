# Architecture: Sales, Marketing, Accounting & Reports

High-level comparison of the **Sales**, **Marketing**, **Accounting** modules and **Reports** (all report endpoints) in the Rakez ERP.

---

## 1. Module Overview

| Aspect | Sales | Marketing | Accounting | Reports (all) |
|--------|--------|-----------|------------|----------------|
| **Route prefix** | `sales` | `marketing` | `accounting` | Distributed (see below) |
| **Roles** | `sales`, `sales_leader`, `admin` (+ `accounting` for analytics) | `marketing`, `admin` | `accounting`, `admin` | Same as owning module |
| **Dedicated Report controller** | No | Yes (`MarketingReportController`) | No | HR + Marketing only |
| **Report-like data** | Dashboard + Insights + Analytics API | Dashboard + Reports + Exports | Dashboard + list endpoints | HR reports + Marketing reports |

---

## 2. Sales Module

### 2.1 Structure

```
app/
├── Http/
│   ├── Controllers/Sales/
│   │   ├── SalesDashboardController      # Dashboard KPIs
│   │   ├── SalesProjectController        # Projects, units, assignments
│   │   ├── SalesReservationController    # Reservations, confirm, cancel, voucher
│   │   ├── SalesTargetController         # Targets (my, team)
│   │   ├── SalesAttendanceController      # Attendance schedules
│   │   ├── SalesInsightsController       # Sold units, deposits, commission summary
│   │   ├── MarketingTaskController       # Marketing tasks (sales context)
│   │   ├── WaitingListController
│   │   ├── NegotiationApprovalController
│   │   └── PaymentPlanController
│   └── Requests/Sales/                    # StoreReservation, StoreTarget, etc.
│   └── Resources/Sales/                  # JSON API resources
├── Services/Sales/
│   ├── SalesDashboardService
│   ├── SalesAnalyticsService             # KPIs, units sold, deposits, commissions
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

**API (under `sales`):**  
`dashboard`, `sold-units`, `deposits/*`, `projects`, `reservations`, `targets`, `attendance`, `marketing-tasks`, `waiting-list`, `negotiation`, `payment-plan`, etc.

**Analytics (same prefix, separate controller):**  
`Sales\SalesAnalyticsController`: `analytics/dashboard`, `analytics/sold-units`, `analytics/deposits/stats/project/{id}`, `analytics/commissions/stats/employee/{id}`, `analytics/commissions/monthly-report`.

- **No** dedicated `SalesReportController`.  
- “Report” style data is exposed via **SalesDashboardController**, **SalesInsightsController**, and **SalesAnalyticsController** (KPIs, sold units, deposits, commissions).

---

## 3. Marketing Module

### 3.1 Structure

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
│   │   ├── MarketingReportController     # ← Dedicated reports
│   │   └── MarketingSettingsController
│   └── Requests/Marketing/
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

**API (under `marketing`):**  
`dashboard`, `projects`, `developer-plans`, `employee-plans`, `expected-sales`, `budget-distributions`, `tasks`, `teams`, `leads`, **`reports/*`**, `settings`.

### 3.2 Reports (Marketing)

**Controller:** `MarketingReportController`  
**Permission:** `marketing.reports.view`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `reports/project/{projectId}` | GET | Project performance (leads, expected bookings, conversion) |
| `reports/budget` | GET | Budget report (total, by project, by plan type) |
| `reports/expected-bookings` | GET | Expected bookings aggregate |
| `reports/employee/{userId}` | GET | Employee performance |
| `reports/export/{planId}` | GET | Export plan (PDF/Excel/CSV) |
| `reports/developer-plan/export/{contractId}` | GET | Export developer plan |

Reports are **in-module** (no separate Report service; controller uses models and `DeveloperMarketingPlanService` / exports).

---

## 4. Accounting Module

### 4.1 Structure

```
app/
├── Http/
│   └── Controllers/Accounting/
│       ├── AccountingDashboardController   # Dashboard metrics (report-like KPIs)
│       ├── AccountingNotificationController
│       ├── AccountingCommissionController  # Sold units, commissions, distributions
│       ├── AccountingDepositController     # Pending, confirm, follow-up, refund
│       ├── AccountingSalaryController      # Salaries, distributions, approve, paid
│       └── AccountingConfirmationController # Legacy down-payment confirmations
├── Services/Accounting/
│   ├── AccountingDashboardService          # getDashboardMetrics (same KPIs as Sales)
│   ├── AccountingCommissionService
│   ├── AccountingDepositService
│   ├── AccountingSalaryService
│   └── AccountingNotificationService
```

**API (under `accounting`):**  
`dashboard`, `notifications`, `marketers`, `sold-units`, `commissions`, `deposits/*`, `salaries/*`, `pending-confirmations`, `confirm/*`, `confirmations/history`.

- **No** dedicated `AccountingReportController`.  
- “Report” style data is **dashboard metrics** (units sold, deposits, commissions, etc.) and **list endpoints** (sold-units, commissions, deposits, salaries). Permission: `accounting.dashboard.view`, `accounting.sold-units.view`, etc.

---

## 5. Reports (All) – Cross-Module View

There is **no single “all reports” module**. Reports are **per-department**:

| Department | Report controller / entry | Route prefix | Permission |
|------------|---------------------------|--------------|-------------|
| **HR** | `HrReportController` | `hr/reports/*` | `hr.reports.view`, `hr.reports.print` |
| **Marketing** | `MarketingReportController` | `marketing/reports/*` | `marketing.reports.view` |
| **Sales** | — | — | Uses dashboard + insights + analytics (no “reports” namespace) |
| **Accounting** | — | — | Uses dashboard + list endpoints (no “reports” namespace) |

### 5.1 HR Reports (HrReportController + HrReportService)

**Service:** `App\Services\HR\HrReportService`  
**Controller:** `App\Http\Controllers\HR\HrReportController`

| Endpoint | Description |
|----------|-------------|
| `hr/reports/team-performance` | Monthly team performance (year, month) |
| `hr/reports/marketer-performance` | Marketer performance (optional team_id), JSON |
| `hr/reports/marketer-performance/pdf` | Same report as PDF |
| `hr/reports/employee-count` | Employee count report |
| `hr/reports/expiring-contracts` | Expiring contracts |
| `hr/reports/expiring-contracts/pdf` | Same as PDF |
| `hr/reports/ended-contracts` | Ended contracts |

**Views (PDF):** `resources/views/pdfs/marketer_performance_report.blade.php`, `expiring_contracts_report.blade.php`.

### 5.2 Marketing Reports (MarketingReportController)

See **§3.2**. All under `marketing/reports/*`.

### 5.3 Sales “reporting”

- **Sales dashboard:** `sales/dashboard` (SalesDashboardController + SalesDashboardService).  
- **Insights:** `sales/sold-units`, `sales/deposits/management`, `sales/deposits/follow-up`, commission summary (SalesInsightsController).  
- **Analytics API:** `sales/analytics/dashboard`, `sales/analytics/sold-units`, `sales/analytics/commissions/monthly-report`, etc. (SalesAnalyticsController + SalesAnalyticsService).

No `sales/reports/*` routes.

### 5.4 Accounting “reporting”

- **Dashboard:** `accounting/dashboard` (AccountingDashboardController + AccountingDashboardService) with date range.  
- **Lists:** sold-units, commissions, deposits, salaries (each its own controller).  

No `accounting/reports/*` routes.

---

## 6. Architectural Comparison

| Dimension | Sales | Marketing | Accounting | Reports (all) |
|-----------|--------|-----------|------------|----------------|
| **Layering** | Controller → Service → Models | Controller → Service / Models + Export | Controller → Service → Models | HR: Controller → HrReportService; Marketing: Controller → Models/Export |
| **Report controller** | No | Yes | No | Only HR + Marketing |
| **Report service** | No (analytics in SalesAnalyticsService) | No (logic in controller + exports) | No (metrics in AccountingDashboardService) | Yes only for HR (HrReportService) |
| **Exports** | PDF voucher (reservation) | PDF/Excel/CSV (plans, developer plan) | — | HR: PDF (marketer, expiring contracts) |
| **Permission pattern** | `sales.*` (dashboard, projects, reservations, etc.) | `marketing.*` + `marketing.reports.view` | `accounting.*` | `hr.reports.view` / `marketing.reports.view` |
| **Shared data** | Reservations, units, deposits, commissions (shared with Accounting/Credit) | Projects, plans, budget, leads | Same reservations/deposits/commissions | HR uses Teams, Users, Reservations; Marketing uses Plans, ExpectedBooking |

---

## 7. Summary

- **Sales:** Operation-focused (reservations, targets, attendance, tasks). “Reports” = dashboard + insights + analytics API; no dedicated report controller.  
- **Marketing:** Full CRUD (projects, plans, budget, tasks, leads) **plus** a dedicated **MarketingReportController** for project/budget/employee reports and plan exports.  
- **Accounting:** Operation-focused (commissions, deposits, salaries, confirmations). “Reports” = dashboard metrics and list endpoints; no report controller.  
- **Reports (all):** Implemented only under **HR** (`hr/reports/*` with HrReportService) and **Marketing** (`marketing/reports/*`). Sales and Accounting provide report-like data via **dashboard and analytics/list endpoints** only.

If you want a single “all reports” hub (e.g. an admin page or API that lists or aggregates all report types), it would need to be added on top of these existing endpoints and permissions.
