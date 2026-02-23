<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\BoardsDepartment;
use App\Models\PhotographyDepartment;
use App\Models\MontageDepartment;

/**
 * Comprehensive test coverage for Project Management module access control
 * Tests all PM-related routes for proper authorization
 */
class ProjectManagementAccessTest extends BasePermissionTestCase
{
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->contract = $this->createContractWithUnits(5);
    }

    #[Test]
    public function admin_index_contracts_requires_authentication()
    {
        $this->assertRouteRequiresAuth('GET', '/api/contracts/admin-index');
    }

    #[Test]
    public function admin_index_contracts_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson('/api/contracts/admin-index');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_index_contracts_accessible_by_pm_manager()
    {
        $pmManager = $this->createProjectManagementManager();
        
        $response = $this->actingAs($pmManager, 'sanctum')
            ->getJson('/api/contracts/admin-index');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_index_contracts_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/contracts/admin-index');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_index_contracts_forbidden_for_sales_staff()
    {
        $sales = $this->createSalesStaff();
        
        $response = $this->actingAs($sales, 'sanctum')
            ->getJson('/api/contracts/admin-index');
        
        $response->assertStatus(403);
    }

    #[Test]
    public function pm_update_contract_status_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->patchJson("/api/contracts/update-status/{$this->contract->id}", [
                'status' => 'approved',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_update_contract_status_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/contracts/adminUpdateStatus/{$this->contract->id}", [
                'status' => 'approved',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_second_party_data_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson("/api/second-party-data/show/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function store_second_party_data_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->postJson("/api/second-party-data/store/{$this->contract->id}", [
                'name' => 'Test Developer',
                'email' => 'developer@example.com',
                'phone' => '1234567890',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_second_party_data_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->putJson("/api/second-party-data/update/{$this->contract->id}", [
                'name' => 'Updated Developer',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function get_all_second_parties_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson('/api/second-party-data/second-parties');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function get_contracts_by_second_party_email_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson('/api/second-party-data/contracts-by-email?email=test@example.com');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_contract_units_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson("/api/contracts/units/show/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function upload_units_csv_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->postJson("/api/contracts/units/upload-csv/{$this->contract->id}", [
                'csv_data' => 'unit_number,floor,area\n101,1,100',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function store_contract_unit_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->postJson("/api/contracts/units/store/{$this->contract->id}", [
                'unit_number' => '101',
                'floor' => 1,
                'area' => 100,
                'price' => 500000,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_contract_unit_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        $unit = $this->contract->units()->first();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->putJson("/api/contracts/units/update/{$unit->id}", [
                'price' => 550000,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function delete_contract_unit_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        $unit = $this->contract->units()->first();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->deleteJson("/api/contracts/units/delete/{$unit->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_boards_department_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson("/api/boards-department/show/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function store_boards_department_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->postJson("/api/boards-department/store/{$this->contract->id}", [
                'board_type' => 'outdoor',
                'quantity' => 5,
                'location' => 'Main Street',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_boards_department_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->putJson("/api/boards-department/update/{$this->contract->id}", [
                'quantity' => 10,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_photography_department_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson("/api/photography-department/show/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function store_photography_department_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->postJson("/api/photography-department/store/{$this->contract->id}", [
                'photo_type' => 'interior',
                'quantity' => 20,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_photography_department_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->putJson("/api/photography-department/update/{$this->contract->id}", [
                'quantity' => 30,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_pm_dashboard_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson('/api/project_management/dashboard');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_units_statistics_accessible_by_pm_staff()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $response = $this->actingAs($pmStaff, 'sanctum')
            ->getJson('/api/project_management/dashboard/units-statistics');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_can_view_contracts()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->getJson('/api/editor/contracts/index');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_can_view_contract_details()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->getJson("/api/editor/contracts/show/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_can_view_montage_department()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->getJson("/api/editor/montage-department/show/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_can_store_montage_department()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/api/editor/montage-department/store/{$this->contract->id}", [
                'video_type' => 'promotional',
                'duration' => 60,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_can_update_montage_department()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->putJson("/api/editor/montage-department/update/{$this->contract->id}", [
                'duration' => 90,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function editor_cannot_access_boards_department()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->getJson("/api/boards-department/show/{$this->contract->id}");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function editor_cannot_access_photography_department()
    {
        $editor = $this->createEditor();
        
        $response = $this->actingAs($editor, 'sanctum')
            ->getJson("/api/photography-department/show/{$this->contract->id}");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function sales_staff_cannot_access_pm_routes()
    {
        $sales = $this->createSalesStaff();
        
        $routes = [
            ['GET', '/api/contracts/admin-index'],
            ['GET', "/api/second-party-data/show/{$this->contract->id}"],
            ['GET', "/api/contracts/units/show/{$this->contract->id}"],
            ['GET', "/api/boards-department/show/{$this->contract->id}"],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($sales, 'sanctum')
                ->json($method, $uri);
            
            $response->assertStatus(403);
        }
    }

    #[Test]
    public function marketing_staff_cannot_access_pm_routes()
    {
        $marketing = $this->createMarketingStaff();
        
        $routes = [
            ['GET', '/api/contracts/admin-index'],
            ['POST', "/api/second-party-data/store/{$this->contract->id}"],
            ['POST', "/api/contracts/units/store/{$this->contract->id}"],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($marketing, 'sanctum')
                ->json($method, $uri);
            
            $response->assertStatus(403);
        }
    }

    #[Test]
    public function pm_staff_permissions_are_correctly_assigned()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $expectedPermissions = [
            'contracts.view',
            'contracts.view_all',
            'contracts.approve',
            'units.view',
            'units.edit',
            'units.csv_upload',
            'second_party.view',
            'second_party.edit',
            'departments.boards.view',
            'departments.boards.edit',
            'departments.photography.view',
            'departments.photography.edit',
            'dashboard.analytics.view',
            'projects.view',
            'projects.create',
            'projects.media.upload',
        ];
        
        $this->assertUserHasAllPermissions($pmStaff, $expectedPermissions);
    }

    #[Test]
    public function pm_staff_does_not_have_manager_permissions()
    {
        $pmStaff = $this->createProjectManagementStaff();
        
        $managerPermissions = [
            'projects.approve',
            'projects.media.approve',
            'projects.archive',
            'exclusive_projects.approve',
        ];
        
        $this->assertUserDoesNotHavePermissions($pmStaff, $managerPermissions);
    }

    #[Test]
    public function pm_manager_has_dynamic_permissions()
    {
        $pmManager = $this->createProjectManagementManager();
        
        // Should have base PM permissions
        $this->assertTrue($pmManager->hasPermissionTo('projects.view'));
        $this->assertTrue($pmManager->hasPermissionTo('contracts.view'));
        
        // Should have dynamic manager permissions
        $this->assertTrue($pmManager->isProjectManagementManager());
        $this->assertUserHasEffectivePermission($pmManager, 'projects.approve');
        $this->assertUserHasEffectivePermission($pmManager, 'projects.media.approve');
        $this->assertUserHasEffectivePermission($pmManager, 'exclusive_projects.approve');
        $this->assertUserHasEffectivePermission($pmManager, 'projects.archive');
    }

    #[Test]
    public function editor_permissions_are_correctly_assigned()
    {
        $editor = $this->createEditor();
        
        $expectedPermissions = [
            'contracts.view',
            'contracts.view_all',
            'departments.montage.view',
            'departments.montage.edit',
            'editing.projects.view',
            'editing.media.upload',
        ];
        
        $this->assertUserHasAllPermissions($editor, $expectedPermissions);
    }

    #[Test]
    public function editor_does_not_have_pm_management_permissions()
    {
        $editor = $this->createEditor();
        
        $pmPermissions = [
            'units.edit',
            'second_party.edit',
            'departments.boards.edit',
            'departments.photography.edit',
        ];
        
        $this->assertUserDoesNotHavePermissions($editor, $pmPermissions);
    }

    #[Test]
    public function developer_can_view_contracts()
    {
        $developer = $this->createDeveloper();
        
        $response = $this->actingAs($developer, 'sanctum')
            ->getJson('/api/contracts/index');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function developer_can_create_contracts()
    {
        $developer = $this->createDeveloper();
        
        $response = $this->actingAs($developer, 'sanctum')
            ->postJson('/api/contracts/store', [
                'project_name' => 'Test Project',
                'location' => 'Test Location',
                'total_units' => 100,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function developer_permissions_are_correctly_assigned()
    {
        $developer = $this->createDeveloper();
        
        $expectedPermissions = [
            'contracts.view',
            'contracts.create',
        ];
        
        $this->assertUserHasAllPermissions($developer, $expectedPermissions);
    }

    #[Test]
    public function developer_does_not_have_pm_management_permissions()
    {
        $developer = $this->createDeveloper();
        
        $pmPermissions = [
            'contracts.view_all',
            'contracts.approve',
            'units.edit',
            'second_party.edit',
        ];
        
        $this->assertUserDoesNotHavePermissions($developer, $pmPermissions);
    }
}
