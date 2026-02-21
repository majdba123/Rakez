<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;

/**
 * Smoke test: admin can hit a representative endpoint from each module.
 * Guards against regressions that would deny admin access to HR, Sales, Marketing,
 * Accounting, Credit, Project Management, AI, or Exclusive Projects.
 */
class AdminSmokeTest extends BasePermissionTestCase
{
    #[Test]
    public function admin_can_access_hr_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/hr/dashboard');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access HR dashboard');
    }

    #[Test]
    public function admin_can_access_sales_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/sales/dashboard');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access Sales dashboard');
    }

    #[Test]
    public function admin_can_access_marketing_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/marketing/dashboard');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access Marketing dashboard');
    }

    #[Test]
    public function admin_can_access_accounting_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/accounting/dashboard');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access Accounting dashboard');
    }

    #[Test]
    public function admin_can_access_credit_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/credit/dashboard');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access Credit dashboard');
    }

    #[Test]
    public function admin_can_access_project_management_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/project_management/dashboard');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access Project Management dashboard');
    }

    #[Test]
    public function admin_can_access_exclusive_projects_index(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/exclusive-projects');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access Exclusive Projects');
    }

    #[Test]
    public function admin_can_access_ai_conversations(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/ai/conversations');
        $this->assertNotEquals(403, $response->status(), 'Admin must be able to access AI conversations');
    }
}
