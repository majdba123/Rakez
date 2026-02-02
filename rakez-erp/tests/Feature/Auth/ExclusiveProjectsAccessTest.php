<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\ExclusiveProjectRequest;
use App\Models\Contract;

/**
 * Comprehensive test coverage for Exclusive Projects module access control
 * Tests cross-role access verification for exclusive project requests
 */
class ExclusiveProjectsAccessTest extends BasePermissionTestCase
{
    #[Test]
    public function list_exclusive_projects_requires_authentication()
    {
        $this->assertRouteRequiresAuth('GET', '/api/exclusive-projects');
    }

    #[Test]
    public function list_exclusive_projects_accessible_by_authenticated_users()
    {
        $user = $this->createSalesStaff();
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/exclusive-projects');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_exclusive_project_details_accessible_by_authenticated_users()
    {
        $user = $this->createMarketingStaff();
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/exclusive-projects/{$project->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_staff_can_request_exclusive_project()
    {
        $sales = $this->createSalesStaff();
        
        $response = $this->actingAs($sales, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'Exclusive Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Luxury residential tower',
                'estimated_units' => 50,
                'location_city' => 'Riyadh',
                'location_district' => 'Al Olaya',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function marketing_staff_can_request_exclusive_project()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'Marketing Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Commercial complex',
                'estimated_units' => 30,
                'location_city' => 'Jeddah',
                'location_district' => 'Al Hamra',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function pm_staff_can_request_exclusive_project()
    {
        $pm = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pm, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'PM Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Mixed-use development',
                'estimated_units' => 40,
                'location_city' => 'Dammam',
                'location_district' => 'Al Faisaliyah',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_can_request_exclusive_project()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'Editor Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Residential compound',
                'estimated_units' => 25,
                'location_city' => 'Riyadh',
                'location_district' => 'Al Malqa',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function developer_can_request_exclusive_project()
    {
        $developer = $this->createDeveloper();
        
        $response = $this->actingAs($developer, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'Developer Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Office building',
                'estimated_units' => 20,
                'location_city' => 'Riyadh',
                'location_district' => 'King Abdullah Financial District',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function hr_staff_cannot_request_exclusive_project()
    {
        $hr = $this->createHRStaff();
        
        $response = $this->actingAs($hr, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'HR Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Test project',
                'estimated_units' => 10,
                'location_city' => 'Riyadh',
                'location_district' => 'Test',
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function approve_exclusive_project_requires_pm_manager_permission()
    {
        $pmStaff = $this->createProjectManagementStaff();
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $pmStaff->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function approve_exclusive_project_accessible_by_pm_manager()
    {
        $pmManager = $this->createProjectManagementManager();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($pmManager, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function approve_exclusive_project_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        $requester = $this->createMarketingStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function reject_exclusive_project_requires_pm_manager_permission()
    {
        $sales = $this->createSalesStaff();
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $sales->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($sales, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/reject", [
                'rejection_reason' => 'Not feasible',
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function reject_exclusive_project_accessible_by_pm_manager()
    {
        $pmManager = $this->createProjectManagementManager();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($pmManager, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/reject", [
                'rejection_reason' => 'Budget constraints',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function complete_contract_accessible_by_requester()
    {
        $sales = $this->createSalesStaff();
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $sales->id,
            'status' => 'approved',
        ]);
        
        $response = $this->actingAs($sales, 'sanctum')
            ->putJson("/api/exclusive-projects/{$project->id}/contract", [
                'contract_details' => 'Contract completed',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function complete_contract_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'approved',
        ]);
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->putJson("/api/exclusive-projects/{$project->id}/contract", [
                'contract_details' => 'Contract completed',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function export_contract_accessible_by_authorized_users()
    {
        $sales = $this->createSalesStaff();
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $sales->id,
            'status' => 'approved',
            'contract_completed_at' => now(),
        ]);
        
        $response = $this->actingAs($sales, 'sanctum')
            ->getJson("/api/exclusive-projects/{$project->id}/export");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function export_contract_forbidden_for_hr_staff()
    {
        $hr = $this->createHRStaff();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'approved',
            'contract_completed_at' => now(),
        ]);
        
        $response = $this->actingAs($hr, 'sanctum')
            ->getJson("/api/exclusive-projects/{$project->id}/export");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function all_non_hr_users_have_exclusive_project_request_permission()
    {
        $users = [
            'sales' => $this->createSalesStaff(),
            'sales_leader' => $this->createSalesLeader(),
            'marketing' => $this->createMarketingStaff(),
            'pm_staff' => $this->createProjectManagementStaff(),
            'pm_manager' => $this->createProjectManagementManager(),
            'editor' => $this->createEditor(),
            'developer' => $this->createDeveloper(),
            'admin' => $this->createAdmin(),
        ];

        foreach ($users as $type => $user) {
            $this->assertTrue(
                $user->hasPermissionTo('exclusive_projects.request'),
                "User type '{$type}' should have exclusive_projects.request permission"
            );
        }
    }

    #[Test]
    public function hr_staff_does_not_have_exclusive_project_permissions()
    {
        $hr = $this->createHRStaff();
        
        $forbiddenPermissions = [
            'exclusive_projects.request',
            'exclusive_projects.approve',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];
        
        $this->assertUserDoesNotHavePermissions($hr, $forbiddenPermissions);
    }

    #[Test]
    public function only_pm_manager_and_admin_can_approve_exclusive_projects()
    {
        $pmManager = $this->createProjectManagementManager();
        $admin = $this->createAdmin();
        
        // PM Manager should have effective permission
        $this->assertUserHasEffectivePermission($pmManager, 'exclusive_projects.approve');
        
        // Admin should have permission
        $this->assertTrue($admin->hasPermissionTo('exclusive_projects.approve'));
        
        // Others should not
        $nonApprovers = [
            $this->createSalesStaff(),
            $this->createMarketingStaff(),
            $this->createProjectManagementStaff(),
            $this->createEditor(),
        ];

        foreach ($nonApprovers as $user) {
            $this->assertUserDoesNotHaveEffectivePermission($user, 'exclusive_projects.approve');
        }
    }

    #[Test]
    public function sales_leader_cannot_approve_exclusive_projects()
    {
        $salesLeader = $this->createSalesLeader();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function marketing_staff_cannot_approve_exclusive_projects()
    {
        $marketing = $this->createMarketingStaff();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function users_can_view_their_own_exclusive_project_requests()
    {
        $sales = $this->createSalesStaff();
        
        $ownProject = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $sales->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($sales, 'sanctum')
            ->getJson("/api/exclusive-projects/{$ownProject->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function exclusive_project_workflow_is_correctly_enforced()
    {
        // Step 1: Sales staff requests project
        $sales = $this->createSalesStaff();
        $response = $this->actingAs($sales, 'sanctum')
            ->postJson('/api/exclusive-projects', [
                'project_name' => 'Workflow Test Tower',
                'developer_name' => 'Test Developer',
                'developer_contact' => '1234567890',
                'project_description' => 'Test project',
                'estimated_units' => 30,
                'location_city' => 'Riyadh',
                'location_district' => 'Test District',
            ]);
        $this->assertNotEquals(403, $response->status());
        
        // Step 2: PM Manager approves
        $pmManager = $this->createProjectManagementManager();
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $sales->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($pmManager, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        $this->assertNotEquals(403, $response->status());
        
        // Step 3: PM staff completes contract
        $pmStaff = $this->createProjectManagementStaff();
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->putJson("/api/exclusive-projects/{$project->id}/contract", [
                'contract_details' => 'Contract completed',
            ]);
        $this->assertNotEquals(403, $response->status());
        
        // Step 4: Export contract
        $response = $this->actingAs($sales, 'sanctum')
            ->getJson("/api/exclusive-projects/{$project->id}/export");
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function exclusive_projects_permissions_are_role_based_not_user_based()
    {
        // Create multiple users of same role
        $sales1 = $this->createSalesStaff();
        $sales2 = $this->createSalesStaff();
        
        // Both should have same permissions
        $this->assertTrue($sales1->hasPermissionTo('exclusive_projects.request'));
        $this->assertTrue($sales2->hasPermissionTo('exclusive_projects.request'));
        
        // Sales1 creates project
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $sales1->id,
            'status' => 'pending',
        ]);
        
        // Sales2 can view it (role-based access)
        $response = $this->actingAs($sales2, 'sanctum')
            ->getJson("/api/exclusive-projects/{$project->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_has_full_access_to_exclusive_projects()
    {
        $admin = $this->createAdmin();
        $requester = $this->createSalesStaff();
        
        $project = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        
        // Admin can view
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/exclusive-projects/{$project->id}");
        $this->assertNotEquals(403, $response->status());
        
        // Admin can approve
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/exclusive-projects/{$project->id}/approve");
        $this->assertNotEquals(403, $response->status());
        
        // Admin can complete contract
        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/exclusive-projects/{$project->id}/contract", [
                'contract_details' => 'Contract completed',
            ]);
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function exclusive_project_permissions_summary()
    {
        // Request permission (all except HR)
        $canRequest = [
            $this->createSalesStaff(),
            $this->createMarketingStaff(),
            $this->createProjectManagementStaff(),
            $this->createEditor(),
            $this->createDeveloper(),
            $this->createAdmin(),
        ];

        foreach ($canRequest as $user) {
            $this->assertTrue($user->hasPermissionTo('exclusive_projects.request'));
        }

        // Approve permission (only PM Manager and Admin)
        $pmManager = $this->createProjectManagementManager();
        $admin = $this->createAdmin();
        
        $this->assertUserHasEffectivePermission($pmManager, 'exclusive_projects.approve');
        $this->assertTrue($admin->hasPermissionTo('exclusive_projects.approve'));
        
        // HR has no permissions
        $hr = $this->createHRStaff();
        $this->assertFalse($hr->hasPermissionTo('exclusive_projects.request'));
        $this->assertFalse($hr->hasPermissionTo('exclusive_projects.approve'));
        $this->assertFalse($hr->hasPermissionTo('exclusive_projects.contract.complete'));
        $this->assertFalse($hr->hasPermissionTo('exclusive_projects.contract.export'));
    }
}
