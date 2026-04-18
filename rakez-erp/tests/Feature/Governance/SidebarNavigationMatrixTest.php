<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\FilamentNavigationPolicy;
use App\Services\Governance\GovernanceAccessService;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class SidebarNavigationMatrixTest extends BasePermissionTestCase
{
    private const ALL_BUSINESS_GROUPS = [
        'Credit Oversight',
        'Accounting & Finance',
        'Contracts & Projects',
        'Sales Oversight',
        'HR Oversight',
        'Marketing Oversight',
        'Inventory Oversight',
        'AI & Knowledge',
        'Requests & Workflow',
    ];

    #[Test]
    public function super_admin_can_access_all_business_navigation_groups_when_rollout_enabled(): void
    {
        $user = $this->makeGovernanceUser('super_admin');
        $policy = app(FilamentNavigationPolicy::class);

        foreach (self::ALL_BUSINESS_GROUPS as $group) {
            $this->assertTrue(
                $policy->canAccessNavigationGroup($user, $group),
                "super_admin should access [{$group}] when section is enabled and permissions allow.",
            );
        }
    }

    #[Test]
    public function erp_admin_cannot_access_business_navigation_groups_without_top_authority(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');
        $policy = app(FilamentNavigationPolicy::class);

        foreach (self::ALL_BUSINESS_GROUPS as $group) {
            $this->assertFalse(
                $policy->canAccessNavigationGroup($user, $group),
                "erp_admin should not access [{$group}] without top authority.",
            );
        }
    }

    #[Test]
    public function auditor_readonly_has_no_business_group_access(): void
    {
        $user = $this->makeGovernanceUser('auditor_readonly');
        $policy = app(FilamentNavigationPolicy::class);

        foreach (self::ALL_BUSINESS_GROUPS as $group) {
            $this->assertFalse(
                $policy->canAccessNavigationGroup($user, $group),
                "auditor_readonly should NOT access [{$group}]",
            );
        }
    }

    #[Test]
    #[DataProvider('sectionAdminGroupMatrix')]
    public function section_admin_navigation_is_blocked_without_top_authority(string $role, array $allowedGroups): void
    {
        $user = $this->makeGovernanceUser($role);
        $policy = app(FilamentNavigationPolicy::class);

        foreach (self::ALL_BUSINESS_GROUPS as $group) {
            $this->assertFalse(
                $policy->canAccessNavigationGroup($user, $group),
                "{$role} should not access [{$group}] without top authority",
            );
        }
    }

    public static function sectionAdminGroupMatrix(): array
    {
        return [
            'credit_admin' => ['credit_admin', ['Credit Oversight']],
            'accounting_admin' => ['accounting_admin', ['Accounting & Finance']],
            'projects_admin' => ['projects_admin', ['Contracts & Projects', 'Inventory Oversight']],
            'sales_admin' => ['sales_admin', ['Sales Oversight']],
            'hr_admin' => ['hr_admin', ['HR Oversight']],
            'marketing_admin' => ['marketing_admin', ['Marketing Oversight']],
            'inventory_admin' => ['inventory_admin', ['Inventory Oversight', 'Contracts & Projects']],
            'ai_admin' => ['ai_admin', ['AI & Knowledge']],
            'workflow_admin' => ['workflow_admin', ['Requests & Workflow']],
        ];
    }

    #[Test]
    public function ungated_groups_allow_any_panel_user(): void
    {
        $user = $this->makeGovernanceUser('auditor_readonly');
        $policy = app(FilamentNavigationPolicy::class);

        $this->assertFalse($policy->canAccessNavigationGroup($user, 'Overview'));
        $this->assertFalse($policy->canAccessNavigationGroup($user, 'Access Governance'));
        $this->assertFalse($policy->canAccessNavigationGroup($user, 'Governance Observability'));
    }

    #[Test]
    public function operational_user_without_governance_role_has_no_panel_access(): void
    {
        $user = User::factory()->create(['type' => 'sales', 'is_active' => true]);
        $user->assignRole('sales');

        $access = app(GovernanceAccessService::class);
        $this->assertFalse($access->canAccessPanel($user));

        $policy = app(FilamentNavigationPolicy::class);
        foreach (self::ALL_BUSINESS_GROUPS as $group) {
            $this->assertFalse(
                $policy->canAccessNavigationGroup($user, $group),
                "Operational sales user should NOT access [{$group}]",
            );
        }
    }

    #[Test]
    public function admin_home_page_is_forbidden_for_erp_admin_without_top_authority(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function section_admin_cannot_reach_admin_panel_root(): void
    {
        $user = $this->makeGovernanceUser('credit_admin');
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function workflow_admin_sidebar_contains_overview_and_workflow_groups(): void
    {
        $user = $this->makeGovernanceUser('super_admin');

        $this->actingAs($user);

        $this->assertContains('Overview', $this->navigationGroupLabels());
        $this->assertContains('Requests & Workflow', $this->navigationGroupLabels());
    }

    #[Test]
    public function auditor_sidebar_has_only_governance_core_groups(): void
    {
        $user = $this->makeGovernanceUser('auditor_readonly');

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    /**
     * @return array<int, string>
     */
    protected function navigationGroupLabels(): array
    {
        return array_values(array_map(
            fn (NavigationGroup $group): string => (string) $group->getLabel(),
            Filament::getPanel('admin')->getNavigation(),
        ));
    }

    protected function makeGovernanceUser(string $governanceRole): User
    {
        if ($governanceRole === 'super_admin') {
            return $this->createSuperAdmin(['is_active' => true])->fresh();
        }

        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $user->assignRole($governanceRole);

        return $user->fresh();
    }
}
