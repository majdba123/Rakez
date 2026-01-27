# 🚀 Quick Start Guide - Roles & Permissions System

## ✅ System Status
- **67 Permissions** ✓ Created
- **9 Roles** ✓ Configured  
- **150+ Routes** ✓ Protected
- **Database** ✓ Migrated
- **Tests** ✓ Passing

---

## 🎯 Quick Reference

### Available Roles

| Role | Type Value | Manager Flag | Description |
|------|-----------|--------------|-------------|
| **Admin** | `admin` | - | Full system access |
| **PM Staff** | `project_management` | `false` | Project management staff |
| **PM Manager** | `project_management` | `true` | Project management manager |
| **Sales Staff** | `sales` | `false` | Sales staff |
| **Sales Leader** | `sales` | `true` | Sales team leader |
| **Editor** | `editor` | - | Editing/montage staff |
| **HR** | `hr` | - | Human resources |
| **Marketing** | `marketing` | - | Marketing staff |
| **Developer** | `developer` | - | Developer role |

---

## 🔧 Common Operations

### 1. Assign Role to User
```php
use App\Models\User;

// Sales Staff
$user = User::find(1);
$user->type = 'sales';
$user->is_manager = false;
$user->save();
$user->assignRole('sales');

// Sales Leader
$leader = User::find(2);
$leader->type = 'sales';
$leader->is_manager = true;
$leader->save();
$leader->assignRole('sales_leader');

// PM Manager
$manager = User::find(3);
$manager->type = 'project_management';
$manager->is_manager = true;
$manager->save();
$manager->assignRole('project_management');
```

### 2. Check Permissions
```php
// Regular permission
if ($user->can('sales.waiting_list.create')) {
    // Create waiting list entry
}

// Dynamic permission (for managers)
if ($user->hasEffectivePermission('projects.approve')) {
    // Approve project
}

// Check if user is manager
if ($user->isProjectManagementManager()) {
    // PM Manager specific logic
}

if ($user->isSalesLeader()) {
    // Sales Leader specific logic
}
```

### 3. Use in Routes
```php
// In routes/api.php
Route::post('/waiting-list', [WaitingListController::class, 'store'])
    ->middleware('permission:sales.waiting_list.create');
```

### 4. Use in Controllers
```php
// Authorization check
public function store(Request $request)
{
    $this->authorize('sales.waiting_list.create');
    // Your logic here
}

// In request class
public function authorize(): bool
{
    return $this->user()->can('sales.waiting_list.create');
}
```

---

## 📋 Permission Categories

### Contracts (11 permissions)
```
contracts.view
contracts.view_all
contracts.create
contracts.approve
contracts.delete
```

### Projects (9 permissions)
```
projects.view
projects.create
projects.media.upload
projects.media.approve (Manager only)
projects.team.create
projects.team.assign_leader
projects.team.allocate
projects.approve (Manager only)
projects.archive (Manager only)
```

### Sales (18 permissions)
```
sales.dashboard.view
sales.projects.view
sales.units.view
sales.units.book
sales.reservations.create
sales.reservations.view
sales.reservations.confirm
sales.reservations.cancel
sales.waiting_list.create
sales.waiting_list.convert (Leader only)
sales.goals.view
sales.goals.create (Leader only)
sales.targets.view
sales.targets.update
sales.team.manage (Leader only)
sales.attendance.view
sales.attendance.manage (Leader only)
sales.tasks.manage (Leader only)
```

### Marketing (8 permissions)
```
marketing.dashboard.view
marketing.projects.view
marketing.plans.create
marketing.budgets.manage
marketing.tasks.view
marketing.tasks.confirm
marketing.reports.view
```

### HR (4 permissions)
```
hr.employees.manage
hr.users.create
hr.performance.view
hr.reports.print
```

### Exclusive Projects (4 permissions)
```
exclusive_projects.request (All except HR)
exclusive_projects.approve (PM Manager only)
exclusive_projects.contract.complete
exclusive_projects.contract.export
```

---

## 🎬 New Features Usage

### Waiting List Booking

**Create Entry (Sales Staff):**
```bash
POST /api/sales/waiting-list
{
  "contract_id": 1,
  "contract_unit_id": 5,
  "client_name": "John Doe",
  "client_mobile": "0501234567",
  "client_email": "john@example.com",
  "priority": 1,
  "notes": "Interested client"
}
```

**Convert to Reservation (Sales Leader):**
```bash
POST /api/sales/waiting-list/1/convert
{
  "contract_date": "2026-02-01",
  "reservation_type": "confirmed_reservation",
  "client_nationality": "Saudi",
  "client_iban": "SA1234567890",
  "payment_method": "bank_transfer",
  "down_payment_amount": 50000,
  "down_payment_status": "non_refundable",
  "purchase_mechanism": "cash"
}
```

### Exclusive Project Request

**Create Request (All except HR):**
```bash
POST /api/exclusive-projects
{
  "project_name": "Luxury Towers",
  "developer_name": "ABC Development",
  "developer_contact": "0501234567",
  "project_description": "High-end residential",
  "estimated_units": 200,
  "location_city": "Riyadh",
  "location_district": "Al Olaya"
}
```

**Approve Request (PM Manager):**
```bash
POST /api/exclusive-projects/1/approve
```

**Complete Contract:**
```bash
PUT /api/exclusive-projects/1/contract
{
  "units": [
    {"type": "Apartment", "count": 50, "price": 500000},
    {"type": "Villa", "count": 10, "price": 1500000}
  ],
  "notes": "Premium project"
}
```

**Export PDF:**
```bash
GET /api/exclusive-projects/1/export
```

---

## 🧪 Testing

### Run Tests
```bash
# All tests
php artisan test

# Specific suites
php artisan test --filter=WaitingListTest
php artisan test --filter=ExclusiveProjectTest
php artisan test --filter=PermissionsTest
```

### Test Scenarios Covered
- ✅ Waiting list CRUD
- ✅ Waiting list conversion (Leader only)
- ✅ Exclusive project workflow
- ✅ Approval/rejection process
- ✅ Permission checks for all roles
- ✅ Dynamic manager permissions
- ✅ HR exclusion validation

---

## 🔍 Troubleshooting

### Permission Denied Error
```php
// Clear permission cache
php artisan permission:cache-reset

// Or in code
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
```

### User Has No Role
```php
// Check user's roles
$user->roles; // Collection of roles

// Assign role
$user->assignRole('sales');

// Sync roles (replaces all)
$user->syncRoles(['sales']);
```

### Re-seed Permissions
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

---

## 📊 Permission Matrix Quick View

| Feature | Admin | PM Staff | PM Mgr | Sales | Sales Ldr | Editor | HR | Marketing |
|---------|-------|----------|--------|-------|-----------|--------|-----|-----------|
| View Projects | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Create Projects | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Approve Projects | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Book Units | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Waiting List | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Convert Waiting | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Request Exclusive | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Approve Exclusive | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Manage Employees | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Marketing Plans | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 🎓 Best Practices

1. **Always check permissions** before performing actions
2. **Use middleware** for route protection
3. **Use authorization** in controllers for additional checks
4. **Clear cache** after permission changes
5. **Test thoroughly** before deploying
6. **Document** any custom permission logic
7. **Use constants** from `PermissionConstants` class
8. **Handle errors** gracefully with proper messages

---

## 📚 Documentation Files

- `ROLES_PERMISSIONS_IMPLEMENTATION.md` - Complete feature documentation
- `API_PERMISSIONS_MAPPING.md` - All routes with permissions
- `IMPLEMENTATION_COMPLETE.md` - Implementation summary
- `QUICK_START_GUIDE.md` - This guide

---

## 🆘 Need Help?

Check the comprehensive documentation:
1. Review `API_PERMISSIONS_MAPPING.md` for route permissions
2. Check `ROLES_PERMISSIONS_IMPLEMENTATION.md` for features
3. Run tests to see examples: `php artisan test`
4. Check the test files for usage examples

---

**Last Updated:** January 27, 2026  
**Version:** 1.0.0  
**Status:** ✅ Production Ready
