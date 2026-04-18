<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\GovernanceCatalog;
use App\Services\Governance\UserGovernanceService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class FilamentPermissionBugTest extends BasePermissionTestCase
{
    #[Test]
    public function grouped_permission_options_must_preserve_permission_names_as_keys(): void
    {
        $catalog = app(GovernanceCatalog::class);
        $options = $catalog->groupedPermissionOptions();

        foreach ($options as $group => $permissions) {
            foreach ($permissions as $key => $value) {
                // The key should be the permission name (e.g. 'admin.users.view')
                // and NOT a numeric index (e.g. 0, 1, 2)
                $this->assertIsString($key, "Group [{$group}] has a numeric key [{$key}] for permission [{$value}]. It must be a string (the permission name).");
                $this->assertEquals($value, $key, "Group [{$group}] has mismatched key [{$key}] for value [{$value}].");
            }
        }
    }

    #[Test]
    public function user_governance_service_fails_when_provided_with_numeric_indexes(): void
    {
        $service = app(UserGovernanceService::class);

        // This simulates what Filament sends when the keys are broken
        $numericIndexes = ['0', '1', '2'];

        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);

        $service->update($user, [
            'direct_permissions' => $numericIndexes,
        ]);

        $user->refresh();

        // In the bug state, this will pass (unexpectedly) because no permissions were synced
        $this->assertCount(0, $user->permissions, "Permissions should be empty because '0', '1' are not valid permission names.");
    }
}
