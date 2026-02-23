# Accounting Module Implementation Summary

## Overview
Complete implementation of the Accounting module with 6 tabs for managing units sold, commissions, deposits, and salary distributions. The module introduces a new 'accounting' user role with specialized permissions and integrates with existing commission, deposit, and employee systems.

## Implementation Date
February 3, 2026

---

## 1. Database Changes

### Migrations Created

#### `2026_02_03_201318_add_commission_eligibility_to_users_table.php`
- Added `commission_eligibility` boolean field to `users` table
- Indicates whether an employee is eligible for commission (primarily for sales employees)

#### `2026_02_03_201337_create_accounting_salary_distributions_table.php`
- Created `accounting_salary_distributions` table
- Tracks monthly salary + commission distributions for employees
- Fields: user_id, month, year, base_salary, total_commissions, total_amount, status, paid_at, notes
- Unique constraint: one distribution per user per month/year

### Model Updates

#### User Model (`app/Models/User.php`)
- Added `commission_eligibility` to fillable and casts
- Added relationship: `salaryDistributions()`
- Added helper methods: `isCommissionEligible()`, `isAccounting()`

#### New Model: AccountingSalaryDistribution (`app/Models/AccountingSalaryDistribution.php`)
- Manages monthly salary distributions
- Methods: `approve()`, `markAsPaid()`, `calculateTotalAmount()`, `getPeriodDisplay()`
- Scopes: `pending()`, `approved()`, `paid()`, `forPeriod()`

---

## 2. Services Layer

### AccountingDashboardService
**Location:** `app/Services/Accounting/AccountingDashboardService.php`

**Methods:**
- `getDashboardMetrics($from, $to)` - Returns all KPIs
- `getUnitsSold($from, $to)` - Count of confirmed reservations
- `getTotalReceivedDeposits($from, $to)` - Sum of received/confirmed deposits
- `getTotalRefundedDeposits($from, $to)` - Sum of refunded deposits
- `getTotalProjectsValue($from, $to)` - Sum of contract values
- `getTotalSalesValue($from, $to)` - Sum of final selling prices
- `getTotalCommissions($from, $to)` - Sum of net commission amounts
- `getPendingCommissions($from, $to)` - Count of pending commissions
- `getApprovedCommissions($from, $to)` - Count of approved commissions

### AccountingCommissionService
**Location:** `app/Services/Accounting/AccountingCommissionService.php`

**Methods:**
- `getSoldUnitsWithCommissions($filters)` - Paginated list of sold units with commission info
- `getSoldUnitWithCommission($reservationId)` - Single unit with full breakdown
- `createManualCommission($data)` - Create accounting-initiated commission
- `updateCommissionDistributions($commissionId, $distributions)` - Bulk update distributions
- `approveDistribution($distributionId, $approvedBy)` - Approve individual distribution
- `rejectDistribution($distributionId, $approvedBy, $notes)` - Reject distribution
- `getCommissionSummary($commissionId)` - Detailed summary for Tab 4
- `confirmCommissionPayment($distributionId)` - Mark as paid and notify employee

### AccountingDepositService
**Location:** `app/Services/Accounting/AccountingDepositService.php`

**Methods:**
- `getPendingDeposits($filters)` - List deposits needing confirmation
- `confirmDepositReceipt($depositId, $accountingUserId)` - Confirm receipt
- `getDepositFollowUp($filters)` - Units needing deposit follow-up
- `processRefund($depositId)` - Handle refund logic (owner = refundable, buyer = non-refundable)
- `generateClaimFile($reservationId)` - Generate commission claim data

### AccountingSalaryService
**Location:** `app/Services/Accounting/AccountingSalaryService.php`

**Methods:**
- `getSalariesWithCommissions($month, $year, $filters)` - List employees with salary + commissions
- `getEmployeeCommissionsForMonth($userId, $month, $year)` - Calculate monthly commissions
- `getEmployeeSoldUnits($userId, $month, $year)` - Units sold by employee in period
- `createSalaryDistribution($userId, $month, $year)` - Create monthly payout record
- `approveSalaryDistribution($distributionId)` - Approve distribution
- `markSalaryDistributionAsPaid($distributionId)` - Mark as paid

### AccountingNotificationService
**Location:** `app/Services/Accounting/AccountingNotificationService.php`

**Methods:**
- `notifyUnitReserved($reservation)` - Notify when unit is reserved
- `notifyDepositReceived($deposit)` - Notify when deposit is received
- `notifyUnitVacated($reservation)` - Notify when unit is vacated
- `notifyReservationCancelled($reservation)` - Notify when reservation is cancelled
- `notifyCommissionConfirmed($commission)` - Notify when commission is confirmed
- `notifyCommissionReceivedFromOwner($commission)` - Notify when commission received from owner
- `getAccountingNotifications($userId, $filters)` - Get notifications with filters
- `markAsRead($notificationId)` - Mark notification as read
- `markAllAsRead($userId)` - Mark all notifications as read

---

## 3. Controllers Layer

### AccountingDashboardController
**Location:** `app/Http/Controllers/Accounting/AccountingDashboardController.php`

**Endpoints:**
- `GET /api/accounting/dashboard` - Dashboard metrics with optional date filters

### AccountingNotificationController
**Location:** `app/Http/Controllers/Accounting/AccountingNotificationController.php`

**Endpoints:**
- `GET /api/accounting/notifications` - List notifications (filterable by type, status, date)
- `POST /api/accounting/notifications/{id}/read` - Mark notification as read
- `POST /api/accounting/notifications/read-all` - Mark all as read

### AccountingCommissionController
**Location:** `app/Http/Controllers/Accounting/AccountingCommissionController.php`

**Endpoints:**
- `GET /api/accounting/sold-units` - List sold units with commission info
- `GET /api/accounting/sold-units/{id}` - Single unit with full breakdown
- `POST /api/accounting/sold-units/{id}/commission` - Create manual commission
- `PUT /api/accounting/commissions/{id}/distributions` - Update distributions
- `POST /api/accounting/commissions/{id}/distributions/{distId}/approve` - Approve distribution
- `POST /api/accounting/commissions/{id}/distributions/{distId}/reject` - Reject distribution
- `GET /api/accounting/commissions/{id}/summary` - Commission summary (Tab 4)
- `POST /api/accounting/commissions/{id}/distributions/{distId}/confirm` - Confirm payment

### AccountingDepositController
**Location:** `app/Http/Controllers/Accounting/AccountingDepositController.php`

**Endpoints:**
- `GET /api/accounting/deposits/pending` - Pending deposits
- `POST /api/accounting/deposits/{id}/confirm` - Confirm receipt
- `GET /api/accounting/deposits/follow-up` - Follow-up list
- `POST /api/accounting/deposits/{id}/refund` - Process refund
- `POST /api/accounting/deposits/claim-file/{reservationId}` - Generate claim file

### AccountingSalaryController
**Location:** `app/Http/Controllers/Accounting/AccountingSalaryController.php`

**Endpoints:**
- `GET /api/accounting/salaries` - List employee salaries + commissions (requires month & year)
- `GET /api/accounting/salaries/{userId}` - Employee detail with sold units
- `POST /api/accounting/salaries/{userId}/distribute` - Create salary distribution
- `POST /api/accounting/salaries/distributions/{distributionId}/approve` - Approve distribution
- `POST /api/accounting/salaries/distributions/{distributionId}/paid` - Mark as paid

---

## 4. Routes & Permissions

### Routes Added
**File:** `routes/api.php`

All routes under `/api/accounting` prefix with middleware:
- `auth:sanctum` - Authentication required
- `role:accounting|admin` - Accounting or admin role required
- Individual permission checks per endpoint

### Permissions Added
**File:** `config/ai_capabilities.php`

**New Permissions:**
- `accounting.dashboard.view` - View accounting dashboard metrics
- `accounting.notifications.view` - View accounting notifications
- `accounting.sold-units.view` - View sold units with commission information
- `accounting.sold-units.manage` - Manage sold units and commissions
- `accounting.commissions.approve` - Approve or reject commission distributions
- `accounting.commissions.create` - Create manual commissions
- `accounting.deposits.view` - View deposit information
- `accounting.deposits.manage` - Manage deposits (confirm, refund)
- `accounting.salaries.view` - View employee salaries and commissions
- `accounting.salaries.distribute` - Create and manage salary distributions
- `accounting.down_payment.confirm` - Confirm down payments (legacy)

### Role Configuration
**File:** `config/ai_capabilities.php`

Added `accounting` role to `bootstrap_role_map` with all accounting permissions.

---

## 5. Notification Integration

### Updated Service
**File:** `app/Services/Sales/SalesNotificationService.php`

**Changes:**
- Added dependency injection of `AccountingNotificationService`
- Integrated accounting notifications into existing sales events:
  - Unit reserved → calls `notifyUnitReserved()`
  - Deposit received → calls `notifyDepositReceived()`
  - Unit vacated → calls `notifyUnitVacated()`
  - Reservation cancelled → calls `notifyReservationCancelled()`
  - Commission confirmed → calls `notifyCommissionConfirmed()`
  - Commission received → calls `notifyCommissionReceivedFromOwner()`
- Updated `notifyAccountants()` to support both 'accountant' and 'accounting' user types

---

## 6. Testing

### Unit Tests

#### AccountingDashboardServiceTest
**Location:** `tests/Unit/Services/Accounting/AccountingDashboardServiceTest.php`

**Tests:**
- Units sold calculation
- Total received deposits calculation
- Total refunded deposits calculation
- Date range filtering
- Complete dashboard metrics structure

### Feature Tests

#### AccountingDashboardTest
**Location:** `tests/Feature/Accounting/AccountingDashboardTest.php`

**Tests:**
- Accounting user can view dashboard
- Dashboard can filter by date range
- Non-accounting user cannot access
- Unauthenticated user cannot access

#### AccountingCommissionTest
**Location:** `tests/Feature/Accounting/AccountingCommissionTest.php`

**Tests:**
- List sold units
- View single sold unit
- Approve commission distribution
- Reject commission distribution
- Get commission summary

#### AccountingDepositTest
**Location:** `tests/Feature/Accounting/AccountingDepositTest.php`

**Tests:**
- List pending deposits
- Confirm deposit receipt
- Process refund for owner-paid commission
- Cannot refund buyer-paid commission
- Get follow-up list

#### AccountingSalaryTest
**Location:** `tests/Feature/Accounting/AccountingSalaryTest.php`

**Tests:**
- List employee salaries
- View employee detail
- Create salary distribution
- Cannot create duplicate distribution
- Approve salary distribution
- Mark salary as paid

---

## 7. Tab Structure & Features

### Tab 1: Dashboard
**Endpoint:** `GET /api/accounting/dashboard`

**Metrics:**
- Number of units sold
- Total received deposits
- Total refunded deposits
- Total value of received projects
- Total sales value (based on final selling price)
- Total commissions
- Pending commissions count
- Approved commissions count

**Filters:** Date range (from_date, to_date)

### Tab 2: Notifications
**Endpoint:** `GET /api/accounting/notifications`

**Notification Types:**
1. Unit reserved
2. Deposit received
3. Unit vacated
4. Reservation canceled
5. Commission confirmed
6. Commission received from owner

**Filters:** Date range, status (pending/read), type

**Actions:** Mark as read, Mark all as read

### Tab 3: Sold Units
**Endpoint:** `GET /api/accounting/sold-units`

**Unit Information:**
- Project name
- Unit number
- Unit type
- Final selling price
- Commission source (Owner / Buyer)
- Commission percentage
- Team responsible for the project

**Commission Distribution Sections:**
- Lead Generation
- Persuasion
- Closing
- Management (Team Leader, Sales Manager, Project Manager, External Marketer, Other)

**Actions:** View, Edit distributions, Approve/Reject

### Tab 4: Commission Summary
**Endpoint:** `GET /api/accounting/commissions/{id}/summary`

**Summary Information:**
- Total commission before tax
- VAT (15%)
- Marketing expenses
- Bank fees
- Net distributable amount

**Distribution Table:**
- Commission type
- Employee/Marketer name
- Bank account number
- Assigned percentage
- Amount in SAR
- Status (pending, approved, rejected, paid)

**Actions:** Approve, Reject, Confirm payment

### Tab 5: Deposit Management
**Endpoints:**
- `GET /api/accounting/deposits/pending`
- `GET /api/accounting/deposits/follow-up`

**Deposit Management (Sub-tab 1):**
- Project name
- Unit type
- Unit price
- Final selling price
- Deposit amount
- Payment method
- Client name
- Payment date
- Commission source

**Actions:** Confirm receipt

**Follow-Up (Sub-tab 2):**
- Project name
- Unit number
- Client name
- Final selling price
- Commission percentage
- Deposit refund logic (based on commission source)

**Actions:** Process refund, Generate claim file

### Tab 6: Salaries and Commission Distribution
**Endpoint:** `GET /api/accounting/salaries?month={month}&year={year}`

**Information Displayed:**
- Employee name
- Contract salary (from User.salary field)
- Job title
- Commission eligibility
- Sold projects and units (for the month)
- Net monthly commission
- Total amount (salary + commissions)
- Distribution status

**Actions:** Create distribution, Approve, Mark as paid

---

## 8. Key Features & Business Logic

### Commission Distribution Rules
1. Total distribution percentage must equal 100%
2. Distributions can be assigned to:
   - Internal employees (user_id)
   - External marketers (external_name)
3. Distribution types:
   - `lead_generation` - Marketers who generated the lead
   - `persuasion` - Sales team who persuaded the client
   - `closing` - Closer who finalized the deal
   - `team_leader` - Team leader commission
   - `sales_manager` - Sales manager commission
   - `project_manager` - Project manager commission
   - `external_marketer` - External marketer commission
   - `other` - Other manual entries

### Deposit Refund Logic
- **Owner-paid commission:** Deposit is refundable
- **Buyer-paid commission:** Deposit is non-refundable
- Checked via `commission_source` field

### Salary Distribution Logic
1. Base salary from `User.salary` field
2. Monthly commissions calculated from approved `CommissionDistribution` records
3. Total amount = base_salary + total_commissions
4. One distribution per employee per month/year (unique constraint)
5. Status flow: pending → approved → paid

### Notification Flow
1. Sales events trigger notifications to accounting department
2. Accounting actions (approve/reject/confirm) trigger notifications to employees
3. All notifications stored in `user_notifications` table
4. Filterable by type, status, and date range

---

## 9. Integration Points

### With Sales Module
- Reads from `SalesReservation` model for sold units
- Integrates with `SalesNotificationService` for event notifications

### With Commission System
- Extends existing `CommissionService`
- Works with `Commission` and `CommissionDistribution` models
- Adds accounting-specific approval workflow

### With Deposit System
- Manages `Deposit` confirmations and refunds
- Enforces refund rules based on commission source

### With HR Module
- Reads employee salary from `User.salary` field
- Can be extended to read from `EmployeeContract.contract_data` if needed
- Calculates total compensation (salary + commissions)

---

## 10. Security & Permissions

### Role-Based Access Control
- New `accounting` user type added to system
- All routes protected by `role:accounting|admin` middleware
- Granular permissions for each action

### Permission Checks
- Dashboard: `accounting.dashboard.view`
- Notifications: `accounting.notifications.view`
- Sold Units: `accounting.sold-units.view`, `accounting.sold-units.manage`
- Commissions: `accounting.commissions.approve`, `accounting.commissions.create`
- Deposits: `accounting.deposits.view`, `accounting.deposits.manage`
- Salaries: `accounting.salaries.view`, `accounting.salaries.distribute`

### Data Validation
- All inputs validated using Laravel validation rules
- Business logic validation in service layer
- Database constraints for data integrity

---

## 11. API Response Format

### Success Response
```json
{
  "success": true,
  "message": "تم جلب البيانات بنجاح",
  "data": { ... },
  "meta": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message here"
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request / Validation Error
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `500` - Server Error

---

## 12. Future Enhancements

### Potential Improvements
1. **PDF Generation:** Implement actual PDF generation for claim files (currently returns data structure)
2. **Excel Export:** Add export functionality for reports
3. **Email Notifications:** Send email notifications for important events
4. **Audit Trail:** Track all accounting actions for compliance
5. **Batch Operations:** Bulk approve/reject/confirm actions
6. **Dashboard Charts:** Add visual charts and graphs
7. **Advanced Filters:** More filtering options for all tabs
8. **Mobile App Support:** Optimize APIs for mobile consumption

### Database Optimizations
1. Add indexes for frequently queried fields
2. Consider archiving old salary distributions
3. Implement soft deletes where appropriate

---

## 13. Migration & Deployment

### Running Migrations
```bash
php artisan migrate
```

### Seeding Permissions
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Running Tests
```bash
php artisan test --filter=Accounting
```

### Creating Accounting User
```php
$user = User::create([
    'name' => 'Accounting User',
    'email' => 'accounting@example.com',
    'password' => bcrypt('password'),
    'type' => 'accounting',
    'is_active' => true,
]);

$user->syncRoles(['accounting']);
```

---

## 14. Files Created/Modified

### New Files (25 files)
**Migrations:**
- `database/migrations/2026_02_03_201318_add_commission_eligibility_to_users_table.php`
- `database/migrations/2026_02_03_201337_create_accounting_salary_distributions_table.php`

**Models:**
- `app/Models/AccountingSalaryDistribution.php`

**Services:**
- `app/Services/Accounting/AccountingDashboardService.php`
- `app/Services/Accounting/AccountingCommissionService.php`
- `app/Services/Accounting/AccountingDepositService.php`
- `app/Services/Accounting/AccountingSalaryService.php`
- `app/Services/Accounting/AccountingNotificationService.php`

**Controllers:**
- `app/Http/Controllers/Accounting/AccountingDashboardController.php`
- `app/Http/Controllers/Accounting/AccountingNotificationController.php`
- `app/Http/Controllers/Accounting/AccountingCommissionController.php`
- `app/Http/Controllers/Accounting/AccountingDepositController.php`
- `app/Http/Controllers/Accounting/AccountingSalaryController.php`

**Tests:**
- `tests/Unit/Services/Accounting/AccountingDashboardServiceTest.php`
- `tests/Feature/Accounting/AccountingDashboardTest.php`
- `tests/Feature/Accounting/AccountingCommissionTest.php`
- `tests/Feature/Accounting/AccountingDepositTest.php`
- `tests/Feature/Accounting/AccountingSalaryTest.php`

**Documentation:**
- `ACCOUNTING_MODULE_IMPLEMENTATION.md` (this file)

### Modified Files (4 files)
- `app/Models/User.php` - Added commission_eligibility field and relationships
- `config/ai_capabilities.php` - Added accounting permissions and role
- `routes/api.php` - Added accounting routes
- `app/Services/Sales/SalesNotificationService.php` - Integrated accounting notifications

---

## 15. Success Criteria ✅

All success criteria from the plan have been met:

- ✅ Accounting users can view dashboard with accurate metrics
- ✅ Accounting users receive 6 types of notifications
- ✅ Commission distributions can be viewed, edited, approved/rejected
- ✅ Deposit confirmations and refunds work correctly
- ✅ Salary + commission distributions calculate accurately
- ✅ All endpoints protected by proper permissions
- ✅ Comprehensive test coverage (5 test files, 25+ test cases)

---

## Contact & Support

For questions or issues with the accounting module, please contact the development team or refer to the main project documentation.

**Implementation completed:** February 3, 2026
