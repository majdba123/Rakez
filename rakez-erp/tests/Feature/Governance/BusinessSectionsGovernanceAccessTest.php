<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class BusinessSectionsGovernanceAccessTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Accounting & Finance',
            'Contracts & Projects',
            'Sales Oversight',
            'HR Oversight',
            'Marketing Oversight',
            'Inventory Oversight',
            'AI & Knowledge',
            'Requests & Workflow',
        ]);
    }

    #[Test]
    public function top_authority_can_access_all_business_section_indexes_and_overviews(): void
    {
        $user = $this->createSuperAdmin([
            'is_active' => true,
        ]);

        $paths = [
            '/admin/accounting-overview',
            '/admin/accounting-deposits',
            '/admin/accounting-sold-units',
            '/admin/accounting-notifications',
            '/admin/commission-distributions',
            '/admin/salary-distributions',
            '/admin/projects-overview',
            '/admin/contracts',
            '/admin/exclusive-project-requests',
            '/admin/project-media',
            '/admin/sales-overview',
            '/admin/sales-reservations',
            '/admin/sales-targets',
            '/admin/sales-attendance-schedules',
            '/admin/hr-overview',
            '/admin/hr-teams',
            '/admin/employee-performance-scores',
            '/admin/employee-warnings',
            '/admin/employee-contracts',
            '/admin/marketing-overview',
            '/admin/marketing-projects-admin',
            '/admin/developer-marketing-plans',
            '/admin/employee-marketing-plans',
            '/admin/marketing-tasks-admin',
            '/admin/marketing-leads',
            '/admin/inventory-overview',
            '/admin/inventory-units',
            '/admin/ai-overview',
            '/admin/assistant-knowledge-entries',
            '/admin/ai-interaction-logs',
            '/admin/ai-audit-entries',
            '/admin/workflow-overview',
            '/admin/workflow-tasks',
            '/admin/admin-notifications',
            '/admin/user-notifications',
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    #[Test]
    public function section_admin_overlay_roles_cannot_access_business_sections_without_top_authority(): void
    {
        $matrix = [
            'accounting_admin' => [
                'blocked' => [
                    '/admin/accounting-overview',
                    '/admin/accounting-deposits',
                    '/admin/accounting-sold-units',
                    '/admin/accounting-notifications',
                    '/admin/commission-distributions',
                    '/admin/salary-distributions',
                ],
            ],
            'projects_admin' => [
                'blocked' => ['/admin/projects-overview', '/admin/contracts', '/admin/exclusive-project-requests'],
            ],
            'sales_admin' => [
                'blocked' => ['/admin/sales-overview', '/admin/sales-reservations'],
            ],
            'hr_admin' => [
                'blocked' => ['/admin/hr-overview', '/admin/hr-teams'],
            ],
            'marketing_admin' => [
                'blocked' => ['/admin/marketing-overview', '/admin/marketing-projects-admin'],
            ],
            'inventory_admin' => [
                'blocked' => ['/admin/inventory-overview', '/admin/inventory-units'],
            ],
            'ai_admin' => [
                'blocked' => ['/admin/ai-overview', '/admin/assistant-knowledge-entries', '/admin/ai-interaction-logs'],
            ],
            'workflow_admin' => [
                'blocked' => ['/admin/workflow-overview', '/admin/admin-notifications', '/admin/workflow-tasks'],
            ],
        ];

        foreach ($matrix as $role => $expectations) {
            $user = User::factory()->create([
                'type' => 'default',
                'is_active' => true,
                'email' => "{$role}@example.com",
                'phone' => '05' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            ]);
            $user->assignRole($role);

            foreach ($expectations['blocked'] as $path) {
                $this->actingAs($user)->get($path)->assertForbidden();
            }
        }
    }

    #[Test]
    public function auditor_readonly_cannot_access_business_sections(): void
    {
        $auditor = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $auditor->assignRole('auditor_readonly');

        $this->actingAs($auditor)->get('/admin/accounting-overview')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/accounting-notifications')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/projects-overview')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/sales-overview')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/ai-overview')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/workflow-overview')->assertForbidden();
    }
}
