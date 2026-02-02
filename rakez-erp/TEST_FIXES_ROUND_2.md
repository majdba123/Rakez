# Test Fixes Applied - Round 2

This document summarizes additional fixes applied after the first round of test execution.

## New Fixes Applied

### 5. Marketing Project Factory Schema Fix

**Problem**: Tests were failing with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'budget' in 'field list'` when creating `MarketingProject` instances.

**Root Cause**: The `MarketingProjectFactory` was trying to set columns (`budget`, `duration_months`, `start_date`, `end_date`) that don't exist in the `marketing_projects` table. 

The actual `marketing_projects` table schema (from migration `2026_01_26_143649_create_marketing_tables.php`) only has:
- `id`
- `contract_id`
- `status`
- `assigned_team_leader`
- `timestamps`

**Solution**: Updated `MarketingProjectFactory` to match the actual table schema:

```php
public function definition(): array
{
    return [
        'contract_id' => Contract::factory(),
        'status' => $this->faker->randomElement(['active', 'completed', 'on_hold']),
        'assigned_team_leader' => User::factory(),
    ];
}
```

**Files Modified**:
- `rakez-erp/database/factories/MarketingProjectFactory.php`

## Remaining Test Failures

After this fix, there are still some authorization-related test failures:

### 1. Project Management Unit Update/Delete (2 failures)

**Tests**:
- `Tests\Feature\Auth\ProjectManagementAccessTest::update_contract_unit_accessible_by_pm_staff`
- `Tests\Feature\Auth\ProjectManagementAccessTest::delete_contract_unit_accessible_by_pm_staff`

**Issue**: Both tests are returning 403 (Forbidden) when PM staff try to update/delete contract units.

**Expected Behavior**: PM staff have:
- `units.edit` permission ✓
- `contracts.approve` permission ✓

According to `ContractUnitPolicy`, to update/delete a unit, the user must:
1. Have `units.edit` permission (PM staff have this)
2. Be able to update the contract (checked via `ContractPolicy::update`)

According to `ContractPolicy::update`, a user can update a contract if:
1. They are the owner, OR
2. They are a manager and the contract belongs to their team, OR
3. They have `contracts.approve` permission (PM staff have this)

**Potential Issues**:
- The authorization chain might not be working correctly
- The contract relationship might not be loading properly
- There might be an issue with how permissions are checked in the policy

### 2. Sales Leader Emergency Contacts (1 failure)

**Test**:
- `Tests\Feature\Auth\SalesAccessTest::update_emergency_contacts_accessible_by_sales_leader`

**Issue**: Returning 403 (Forbidden) when sales leader tries to update emergency contacts.

**Expected Behavior**: Sales leaders should have `sales.team.manage` permission, which is required by the route middleware.

**Route Definition** (from `routes/api.php` line 198):
```php
Route::middleware('permission:sales.team.manage')->group(function () {
    Route::patch('projects/{contractId}/emergency-contacts', [SalesProjectController::class, 'updateEmergencyContacts']);
    // ...
});
```

**Potential Issues**:
- The `sales.team.manage` permission might not be properly assigned to the `sales_leader` role
- Permission cache might not be refreshed properly in tests
- The user's roles might not be loaded correctly

## Investigation Needed

These failures appear to be genuine authorization issues rather than test setup problems. The following areas need investigation:

1. **Verify permission assignment**: Confirm that `contracts.approve` is actually assigned to `project_management` role and `sales.team.manage` is assigned to `sales_leader` role in the test environment.

2. **Check policy execution**: Add logging or debugging to see which part of the policy chain is failing.

3. **Test permission caching**: Ensure that permission cache is properly cleared and refreshed in the test environment.

4. **Review authorization middleware**: Check if the `permission` middleware is working correctly with Spatie permissions.

## Test Statistics

- **Total Tests**: 640
- **Passed**: 593
- **Failed**: 46
- **Skipped**: 1
- **Duration**: 182.31s

The majority of failures (43 out of 46) are related to the Marketing Project factory schema issue, which has now been fixed. The remaining 3 failures are authorization-related and require further investigation.
