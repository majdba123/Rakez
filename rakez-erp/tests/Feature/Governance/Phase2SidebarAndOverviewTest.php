<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\FilamentNavigationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class Phase2SidebarAndOverviewTest extends BasePermissionTestCase
{
    private const ALL_ENABLED_GROUPS = [
        'Overview',
        'Access Governance',
        'Governance Observability',
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

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', self::ALL_ENABLED_GROUPS);
    }

    #[Test]
    public function enabled_sections_config_matches_all_navigation_groups(): void
    {
        $enabled = config('governance.enabled_sections');
        $this->assertEqualsCanonicalizing(self::ALL_ENABLED_GROUPS, $enabled);
    }

    #[Test]
    public function super_admin_sees_all_12_navigation_groups(): void
    {
        $user = $this->makeGovernanceUser('super_admin');
        $policy = app(FilamentNavigationPolicy::class);

        foreach (self::ALL_ENABLED_GROUPS as $group) {
            $this->assertTrue(
                $policy->canAccessNavigationGroup($user, $group),
                "super_admin should access [{$group}]"
            );
        }
    }

    #[Test]
    #[DataProvider('sectionAdminOverviewMatrix')]
    public function section_admin_cannot_reach_their_overview_page_without_top_authority(string $role, string $overviewUrl): void
    {
        $user = $this->makeGovernanceUser($role);
        $this->actingAs($user)->get($overviewUrl)->assertForbidden();
    }

    public static function sectionAdminOverviewMatrix(): array
    {
        return [
            'credit_admin → credit overview' => ['credit_admin', '/admin/credit-overview'],
            'accounting_admin → accounting overview' => ['accounting_admin', '/admin/accounting-overview'],
            'projects_admin → projects overview' => ['projects_admin', '/admin/projects-overview'],
            'sales_admin → sales overview' => ['sales_admin', '/admin/sales-overview'],
            'hr_admin → HR overview' => ['hr_admin', '/admin/hr-overview'],
            'marketing_admin → marketing overview' => ['marketing_admin', '/admin/marketing-overview'],
            'inventory_admin → inventory overview' => ['inventory_admin', '/admin/inventory-overview'],
            'ai_admin → AI overview' => ['ai_admin', '/admin/ai-overview'],
            'workflow_admin → workflow overview' => ['workflow_admin', '/admin/workflow-overview'],
        ];
    }

    #[Test]
    public function top_authority_can_reach_all_overview_pages(): void
    {
        $user = $this->makeGovernanceUser('super_admin');

        $overviewPages = [
            '/admin/credit-overview',
            '/admin/accounting-overview',
            '/admin/projects-overview',
            '/admin/sales-overview',
            '/admin/hr-overview',
            '/admin/marketing-overview',
            '/admin/inventory-overview',
            '/admin/ai-overview',
            '/admin/workflow-overview',
        ];

        foreach ($overviewPages as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    #[Test]
    public function section_admin_cannot_reach_unrelated_overview_page(): void
    {
        $creditAdmin = $this->makeGovernanceUser('credit_admin');
        $this->actingAs($creditAdmin)->get('/admin/hr-overview')->assertForbidden();

        $hrAdmin = $this->makeGovernanceUser('hr_admin');
        $this->actingAs($hrAdmin)->get('/admin/credit-overview')->assertForbidden();
    }

    #[Test]
    public function auditor_readonly_cannot_access_business_overview_pages(): void
    {
        $auditor = $this->makeGovernanceUser('auditor_readonly');

        $businessOverviewPages = [
            '/admin/credit-overview',
            '/admin/accounting-overview',
            '/admin/sales-overview',
            '/admin/hr-overview',
            '/admin/marketing-overview',
        ];

        foreach ($businessOverviewPages as $url) {
            $this->actingAs($auditor)->get($url)->assertForbidden();
        }
    }

    #[Test]
    public function operational_user_cannot_access_admin_panel_at_all(): void
    {
        $salesUser = User::factory()->create(['type' => 'sales', 'is_active' => true]);
        $salesUser->assignRole('sales');

        $this->actingAs($salesUser)->get('/admin')->assertForbidden();
        $this->actingAs($salesUser)->get('/admin/credit-overview')->assertForbidden();
    }

    #[Test]
    public function dashboard_home_renders_for_governance_user(): void
    {
        $user = $this->makeGovernanceUser('super_admin');
        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    #[Test]
    public function effective_access_page_exposes_only_active_phase_filters(): void
    {
        // When temporary grants are disabled, their nav item and any related copy must not appear.
        config(['governance.temporary_permissions.enabled' => false]);

        $user = $this->makeGovernanceUser('super_admin');
        $response = $this->actingAs($user)->get('/admin/effective-access');

        $response->assertOk()
            ->assertSeeText('Has Direct Permissions')
            ->assertDontSeeText('Has Active Temp Grants')
            ->assertDontSeeText('Temporary Permissions');
    }

    #[Test]
    public function governance_audit_filters_include_subject_type_and_category(): void
    {
        $user = $this->makeGovernanceUser('super_admin');
        $response = $this->actingAs($user)->get('/admin/governance-audit');

        $response->assertOk();
    }

    protected function makeGovernanceUser(string $governanceRole): User
    {
        if ($governanceRole === 'super_admin') {
            return $this->createSuperAdmin(['is_active' => true])->fresh();
        }

        $user = $this->createDefaultUser(['is_active' => true]);
        $user->assignRole($governanceRole);

        return $user->fresh();
    }
}
