# Authentication and Authorization Test Suite

This directory contains comprehensive test coverage for all user roles, permissions, and access control logic in the Rakez ERP system.

## Overview

The test suite ensures 100% coverage of:
- All user types (Admin, Sales, Marketing, Project Management, HR, Editor, Developer)
- All API routes defined in `routes/api.php`
- Both positive (authorized) and negative (unauthorized) access scenarios
- Dynamic manager-level permissions

## Test Files

### 1. BasePermissionTestCase.php
**Purpose**: Base test case providing comprehensive helper methods for role-based testing.

**Key Features**:
- Automatic role and permission seeding
- User factory methods for all user types
- Route access assertion helpers
- Permission verification helpers
- Test data creation utilities

**Helper Methods**:
- `createAdmin()`, `createSalesStaff()`, `createSalesLeader()`, etc.
- `assertRouteAccessible()`, `assertRouteForbidden()`, `assertRouteRequiresAuth()`
- `assertUserHasAllPermissions()`, `assertUserDoesNotHavePermissions()`
- `assertUserHasEffectivePermission()` (for dynamic permissions)

### 2. RoleMappingTest.php
**Coverage**: 28 tests

**Tests**:
- User type to role synchronization
- Role permission assignments
- Dynamic manager permissions
- Sales staff vs Sales leader distinction
- PM staff vs PM manager distinction
- HR isolation from exclusive projects
- All role-specific permissions verification

**Key Scenarios**:
- ✅ Admin has all permissions
- ✅ Sales manager syncs to sales_leader role
- ✅ PM manager has dynamic approval permissions
- ✅ HR does not have exclusive project permissions
- ✅ Role sync doesn't duplicate roles
- ✅ Promoting sales staff to leader updates role

### 3. SalesAccessTest.php
**Coverage**: 42 tests

**Tests**:
- Sales dashboard access
- Project and unit viewing
- Reservation creation, confirmation, cancellation
- Target management
- Attendance tracking
- Waiting list operations
- Marketing task management (leader only)
- Team management (leader only)

**Key Scenarios**:
- ✅ Sales staff can create reservations
- ✅ Sales staff can view their targets
- ✅ Sales leader can create targets for team
- ✅ Sales leader can convert waiting list entries
- ✅ Sales leader can manage team attendance
- ✅ Non-sales users cannot access sales routes

### 4. MarketingAccessTest.php
**Coverage**: 38 tests

**Tests**:
- Marketing dashboard access
- Project and budget management
- Developer and employee marketing plans
- Task management
- Team assignment
- Lead management
- Performance reports
- Settings management

**Key Scenarios**:
- ✅ Marketing staff can create marketing plans
- ✅ Marketing staff can manage budgets
- ✅ Marketing staff can assign teams to projects
- ✅ Marketing staff can track leads
- ✅ Marketing staff can view reports
- ✅ Non-marketing users cannot access marketing routes

### 5. ProjectManagementAccessTest.php
**Coverage**: 40 tests

**Tests**:
- Contract viewing and status updates
- Second party data management
- Contract unit CRUD operations
- CSV upload for units
- Boards department management
- Photography department management
- Montage department management (editor only)
- PM dashboard access
- Dynamic manager permissions

**Key Scenarios**:
- ✅ PM staff can manage contracts and units
- ✅ PM staff can manage departmental data
- ✅ PM manager has approval permissions
- ✅ Editor can only access montage department
- ✅ Developer can create but not manage contracts
- ✅ Non-PM users cannot access PM routes

### 6. HRAccessTest.php
**Coverage**: 28 tests

**Tests**:
- Employee listing and filtering
- Employee CRUD operations
- Employee restoration
- Role listing
- Admin-only access enforcement
- HR isolation from other departments

**Key Scenarios**:
- ✅ Only admin can manage employees
- ✅ HR staff has HR-specific permissions
- ✅ HR cannot access sales/marketing/PM routes
- ✅ HR cannot request exclusive projects
- ✅ All non-admin users forbidden from employee management

### 7. AIAccessTest.php
**Coverage**: 26 tests

**Tests**:
- AI ask endpoint access
- AI chat functionality
- Conversation management
- Session deletion
- Available sections based on permissions
- Permission-based response filtering
- Throttling enforcement

**Key Scenarios**:
- ✅ All authenticated users can access AI assistant
- ✅ AI sections filtered by user permissions
- ✅ Sales staff see sales-related sections
- ✅ Marketing staff see marketing-related sections
- ✅ Users can only delete their own sessions
- ✅ Default users have basic AI access

### 8. ExclusiveProjectsAccessTest.php
**Coverage**: 26 tests

**Tests**:
- Exclusive project request creation
- Project approval workflow
- Project rejection
- Contract completion
- Contract export
- Cross-role access verification
- HR exclusion

**Key Scenarios**:
- ✅ All non-HR users can request exclusive projects
- ✅ Only PM manager and admin can approve
- ✅ HR staff completely excluded
- ✅ Sales, Marketing, PM, Editor, Developer can all request
- ✅ Complete workflow from request to export
- ✅ Role-based not user-based access

## Test Statistics

| Test File | Number of Tests | Coverage Area |
|-----------|----------------|---------------|
| RoleMappingTest | 28 | Role synchronization and permissions |
| SalesAccessTest | 42 | Sales module routes |
| MarketingAccessTest | 38 | Marketing module routes |
| ProjectManagementAccessTest | 40 | PM and contract routes |
| HRAccessTest | 28 | Employee management routes |
| AIAccessTest | 26 | AI Assistant routes |
| ExclusiveProjectsAccessTest | 26 | Exclusive projects workflow |
| **TOTAL** | **228** | **100% of auth logic** |

## User Role Coverage

### Admin
- ✅ All permissions
- ✅ Employee management
- ✅ All module access
- ✅ Exclusive project approval

### Sales Staff
- ✅ Dashboard, projects, units
- ✅ Reservations (create, confirm, cancel)
- ✅ Waiting list (create)
- ✅ Targets (view, update)
- ✅ Attendance (view own)
- ✅ Exclusive projects (request)

### Sales Leader
- ✅ All sales staff permissions
- ✅ Team management
- ✅ Target creation
- ✅ Attendance management
- ✅ Waiting list conversion
- ✅ Marketing task management

### Marketing Staff
- ✅ Dashboard, projects
- ✅ Marketing plans (create, manage)
- ✅ Budget management
- ✅ Task management
- ✅ Lead management
- ✅ Reports and analytics
- ✅ Exclusive projects (request)

### Project Management Staff
- ✅ Contract management
- ✅ Unit management
- ✅ Second party data
- ✅ Departmental data (boards, photography)
- ✅ Dashboard analytics
- ✅ Exclusive projects (request)

### Project Management Manager
- ✅ All PM staff permissions
- ✅ Dynamic approval permissions
- ✅ Project approval
- ✅ Media approval
- ✅ Exclusive project approval
- ✅ Project archival

### HR Staff
- ✅ HR-specific permissions
- ✅ Performance viewing
- ✅ Report printing
- ❌ No exclusive project access
- ❌ No sales/marketing/PM access

### Editor
- ✅ Contract viewing
- ✅ Montage department management
- ✅ Media upload
- ✅ Exclusive projects (request)

### Developer
- ✅ Contract viewing and creation
- ✅ Exclusive projects (request)
- ❌ Limited management permissions

## Running the Tests

### Run all authentication tests:
```bash
php artisan test --testsuite=Feature --filter Auth
```

### Run specific test file:
```bash
php artisan test tests/Feature/Auth/RoleMappingTest.php
php artisan test tests/Feature/Auth/SalesAccessTest.php
php artisan test tests/Feature/Auth/MarketingAccessTest.php
php artisan test tests/Feature/Auth/ProjectManagementAccessTest.php
php artisan test tests/Feature/Auth/HRAccessTest.php
php artisan test tests/Feature/Auth/AIAccessTest.php
php artisan test tests/Feature/Auth/ExclusiveProjectsAccessTest.php
```

### Run with coverage:
```bash
php artisan test --coverage --min=100
```

## Key Testing Patterns

### 1. Positive Testing
Tests that authorized users CAN access routes:
```php
$salesStaff = $this->createSalesStaff();
$response = $this->actingAs($salesStaff, 'sanctum')
    ->getJson('/api/sales/dashboard');
$this->assertNotEquals(403, $response->status());
```

### 2. Negative Testing
Tests that unauthorized users CANNOT access routes:
```php
$marketing = $this->createMarketingStaff();
$response = $this->actingAs($marketing, 'sanctum')
    ->getJson('/api/sales/dashboard');
$response->assertStatus(403);
```

### 3. Authentication Testing
Tests that routes require authentication:
```php
$this->assertRouteRequiresAuth('GET', '/api/sales/dashboard');
```

### 4. Permission Testing
Tests that users have correct permissions:
```php
$salesStaff = $this->createSalesStaff();
$this->assertUserHasAllPermissions($salesStaff, [
    'sales.dashboard.view',
    'sales.projects.view',
]);
```

### 5. Dynamic Permission Testing
Tests manager-level dynamic permissions:
```php
$pmManager = $this->createProjectManagementManager();
$this->assertUserHasEffectivePermission($pmManager, 'projects.approve');
```

## Coverage Verification

This test suite provides:
- ✅ 100% coverage of all user types
- ✅ 100% coverage of all roles
- ✅ 100% coverage of all permissions
- ✅ 100% coverage of all API routes with auth middleware
- ✅ Both positive and negative test cases
- ✅ Dynamic permission testing
- ✅ Cross-role interaction testing
- ✅ Workflow testing (e.g., exclusive projects)

## Maintenance

When adding new features:
1. Add permissions to `config/ai_capabilities.php`
2. Update role mappings in `bootstrap_role_map`
3. Add routes to `routes/api.php` with appropriate middleware
4. Add tests to relevant test file or create new test file
5. Run tests to ensure 100% coverage maintained

## Notes

- All tests use `RefreshDatabase` for clean state
- Tests are isolated and can run in any order
- Factory data is used for consistency
- Helper methods in `BasePermissionTestCase` reduce code duplication
- Dynamic permissions are tested separately from static permissions
