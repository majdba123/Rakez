<?php

namespace Tests\Feature;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\ExclusiveProjectRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExclusiveProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesStaff;
    protected User $projectManager;
    protected User $hrStaff;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sales staff
        $this->salesStaff = User::factory()->create(['type' => 'sales']);
        $this->salesStaff->syncRolesFromType();

        // Create project management manager
        $this->projectManager = User::factory()->create([
            'type' => 'project_management',
            'is_manager' => true,
        ]);
        $this->projectManager->syncRolesFromType();

        // Create HR staff (should NOT have exclusive project permissions)
        $this->hrStaff = User::factory()->create(['type' => 'hr']);
        $this->hrStaff->syncRolesFromType();
    }

    #[Test]
    public function sales_staff_can_create_exclusive_project_request()
    {
        $data = [
            'project_name' => 'Luxury Towers',
            'developer_name' => 'ABC Development',
            'developer_contact' => '0501234567',
            'project_description' => 'High-end residential towers',
            'estimated_units' => 200,
            'location_city' => 'Riyadh',
            'location_district' => 'Al Olaya',
        ];

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->postJson('/api/exclusive-projects', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Exclusive project request created successfully',
            ]);

        $this->assertDatabaseHas('exclusive_project_requests', [
            'project_name' => 'Luxury Towers',
            'developer_name' => 'ABC Development',
            'status' => 'pending',
            'requested_by' => $this->salesStaff->id,
        ]);
    }

    #[Test]
    public function hr_staff_cannot_create_exclusive_project_request()
    {
        $data = [
            'project_name' => 'Luxury Towers',
            'developer_name' => 'ABC Development',
            'developer_contact' => '0501234567',
            'location_city' => 'Riyadh',
        ];

        $response = $this->actingAs($this->hrStaff, 'sanctum')
            ->postJson('/api/exclusive-projects', $data);

        $response->assertStatus(403);
    }

    #[Test]
    public function can_retrieve_exclusive_project_requests()
    {
        ExclusiveProjectRequest::factory()->count(3)->create([
            'requested_by' => $this->salesStaff->id,
        ]);

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->getJson('/api/exclusive-projects');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_get_single_exclusive_project_request()
    {
        $request = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $this->salesStaff->id,
        ]);

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->getJson("/api/exclusive-projects/{$request->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $request->id,
                    'project_name' => $request->project_name,
                ],
            ]);
    }

    #[Test]
    public function project_manager_can_approve_exclusive_project_request()
    {
        $request = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $this->salesStaff->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->projectManager, 'sanctum')
            ->postJson("/api/exclusive-projects/{$request->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Exclusive project request approved successfully',
            ]);

        $this->assertDatabaseHas('exclusive_project_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'approved_by' => $this->projectManager->id,
        ]);
    }

    #[Test]
    public function sales_staff_cannot_approve_exclusive_project_request()
    {
        $request = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $this->salesStaff->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->postJson("/api/exclusive-projects/{$request->id}/approve");

        $response->assertStatus(403);
    }

    #[Test]
    public function project_manager_can_reject_exclusive_project_request()
    {
        $request = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $this->salesStaff->id,
            'status' => 'pending',
        ]);

        $data = [
            'rejection_reason' => 'Insufficient information provided',
        ];

        $response = $this->actingAs($this->projectManager, 'sanctum')
            ->postJson("/api/exclusive-projects/{$request->id}/reject", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Exclusive project request rejected successfully',
            ]);

        $this->assertDatabaseHas('exclusive_project_requests', [
            'id' => $request->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient information provided',
        ]);
    }

    #[Test]
    public function can_complete_contract_for_approved_request()
    {
        $request = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $this->salesStaff->id,
            'status' => 'approved',
            'approved_by' => $this->projectManager->id,
        ]);

        $data = [
            'units' => [
                ['type' => 'Apartment', 'count' => 50, 'price' => 500000],
                ['type' => 'Villa', 'count' => 10, 'price' => 1500000],
            ],
            'notes' => 'Premium project with excellent location',
        ];

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->putJson("/api/exclusive-projects/{$request->id}/contract", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contract completed successfully',
            ]);

        $this->assertDatabaseHas('exclusive_project_requests', [
            'id' => $request->id,
            'status' => 'contract_completed',
        ]);

        $this->assertDatabaseHas('contracts', [
            'project_name' => $request->project_name,
            'developer_name' => $request->developer_name,
        ]);
    }

    #[Test]
    public function cannot_complete_contract_for_pending_request()
    {
        $request = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $this->salesStaff->id,
            'status' => 'pending',
        ]);

        $data = [
            'units' => [
                ['type' => 'Apartment', 'count' => 50, 'price' => 500000],
            ],
        ];

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->putJson("/api/exclusive-projects/{$request->id}/contract", $data);

        $response->assertStatus(400);
    }
}
