<?php

namespace Tests\Feature\Access;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class AccessProfileContractTest extends BasePermissionTestCase
{
    #[Test]
    public function unauthenticated_user_cannot_access_profile_endpoint(): void
    {
        $this->getJson('/api/access/profile')->assertUnauthorized();
    }

    #[Test]
    public function endpoint_returns_expected_shape_without_admin_flags_or_admin_section(): void
    {
        $user = $this->createSalesStaff();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/access/profile')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email', 'type'],
                    'frontend' => [
                        'sections',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('admin', $data['frontend']['sections']);

        $this->assertForbiddenKeysAbsent($data, [
            'is_section_admin',
            'is_filament_admin',
            'is_full_system_admin',
            'can_open_filament',
        ]);
    }

    #[Test]
    public function sales_user_gets_sales_section_visible_with_expected_tabs_and_actions(): void
    {
        $user = $this->createSalesStaff();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/access/profile')
            ->assertOk();

        $sections = $response->json('data.frontend.sections');

        $this->assertTrue($sections['sales']['visible']);
        $this->assertTrue($sections['sales']['tabs']['dashboard']['visible']);
        $this->assertTrue($sections['sales']['tabs']['projects']['visible']);
        $this->assertTrue($sections['sales']['tabs']['reservations']['visible']);
        $this->assertTrue($sections['sales']['actions']['create_reservation']);
        $this->assertFalse($sections['sales']['actions']['manage_team']);

        $this->assertFalse($sections['hr']['visible']);
    }

    #[Test]
    public function direct_permission_is_reflected_through_derived_section_visibility(): void
    {
        $user = $this->createDefaultUser();
        $user->givePermissionTo('credit.bookings.view');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/access/profile')
            ->assertOk();

        $sections = $response->json('data.frontend.sections');

        $this->assertTrue($sections['credit']['visible']);
        $this->assertTrue($sections['credit']['tabs']['bookings']['visible']);
    }

    #[Test]
    public function section_visibility_is_permission_driven_not_role_name_driven(): void
    {
        $user = $this->createDefaultUser();
        $user->givePermissionTo('sales.dashboard.view');

        $sections = $this->actingAs($user, 'sanctum')
            ->getJson('/api/access/profile')
            ->assertOk()
            ->json('data.frontend.sections');

        $this->assertTrue($sections['sales']['visible']);
        $this->assertTrue($sections['sales']['tabs']['dashboard']['visible']);
    }

    #[Test]
    public function admin_user_profile_still_excludes_admin_section_model(): void
    {
        $user = $this->createAdmin();

        $sections = $this->actingAs($user, 'sanctum')
            ->getJson('/api/access/profile')
            ->assertOk()
            ->json('data.frontend.sections');

        $this->assertArrayNotHasKey('admin', $sections);
    }

    #[Test]
    public function frontend_access_permissions_are_defined_in_capabilities_dictionary(): void
    {
        $permissions = $this->collectFrontendAccessPermissions();
        $definitions = array_keys(config('ai_capabilities.definitions', []));
        $missing = array_values(array_diff($permissions, $definitions));

        $this->assertSame([], $missing, 'Frontend access mapping contains permissions missing from ai_capabilities.definitions.');
    }

    #[Test]
    public function frontend_access_contract_contains_no_admin_or_governance_permissions(): void
    {
        $permissions = $this->collectFrontendAccessPermissions();
        $forbidden = array_values(array_filter($permissions, function (string $permission): bool {
            return Str::startsWith($permission, ['admin.', 'governance.']);
        }));

        $this->assertSame([], $forbidden, 'Frontend access mapping must not include admin/governance permissions.');
    }

    /**
     * @param  array<mixed>  $value
     * @param  array<int, string>  $forbiddenKeys
     */
    protected function assertForbiddenKeysAbsent(array $value, array $forbiddenKeys): void
    {
        $stack = [$value];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }

            foreach ($forbiddenKeys as $forbiddenKey) {
                $this->assertArrayNotHasKey($forbiddenKey, $current);
            }

            foreach ($current as $item) {
                if (is_array($item)) {
                    $stack[] = $item;
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function collectFrontendAccessPermissions(): array
    {
        $sections = config('frontend_access.sections', []);
        $permissions = [];
        $stack = [$sections];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }

            foreach ($current as $key => $value) {
                if ($key === 'permissions_any' && is_array($value)) {
                    foreach ($value as $permission) {
                        if (is_string($permission) && $permission !== '') {
                            $permissions[$permission] = true;
                        }
                    }
                    continue;
                }

                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        $list = array_keys($permissions);
        sort($list);

        return $list;
    }
}
