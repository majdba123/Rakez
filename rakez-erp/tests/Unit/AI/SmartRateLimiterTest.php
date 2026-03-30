<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\Infrastructure\SmartRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * حدود الطلبات الدقيقة حسب الدور (config ai_assistant.smart_rate_limits).
 *
 * @see tests/AI_SCENARIO_MATRIX.md (S-03)
 */
class SmartRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_limit_matches_configured_role_cap(): void
    {
        config([
            'ai_assistant.smart_rate_limits' => [
                'admin' => 120,
                'sales_leader' => 60,
                'sales' => 30,
                'marketing' => 30,
                'default' => 15,
            ],
        ]);

        Permission::findOrCreate('use-ai-assistant', 'web');
        $limiter = new SmartRateLimiter;

        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');
        $this->assertSame(120, $limiter->getLimit($admin->fresh()));

        $sales = User::factory()->create();
        $this->assignRole($sales, 'sales');
        $this->assertSame(30, $limiter->getLimit($sales->fresh()));

        $fallback = User::factory()->create();
        $this->assignRole($fallback, 'credit');
        $this->assertSame(15, $limiter->getLimit($fallback->fresh()));
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $perms = array_keys(config('ai_capabilities.definitions', []));
        if ($roleName === 'admin') {
            foreach ($perms as $p) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            }
            $role->syncPermissions($perms);
        } else {
            $subset = config('ai_capabilities.bootstrap_role_map.'.$roleName, ['use-ai-assistant']);
            foreach ($subset as $p) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            }
            $role->syncPermissions($subset);
        }
        $user->assignRole($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
