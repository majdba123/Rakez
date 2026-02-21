<?php

namespace Tests\Feature\Sales;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\SalesReservation;
use App\Models\NegotiationApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class NegotiationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesLeader;
    protected User $salesUser;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'sales.reservations.create',
            'sales.reservations.view',
            'sales.negotiation.approve',
            'sales.payment-plan.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $salesLeaderRole = Role::firstOrCreate(['name' => 'sales_leader', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $adminRole->syncPermissions($permissions);
        $salesLeaderRole->syncPermissions($permissions);
        $salesRole->syncPermissions(['sales.reservations.create', 'sales.reservations.view']);

        // Create users
        $this->admin = User::factory()->create(['type' => 'admin']);
        $this->admin->assignRole('admin');

        $this->salesLeader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $this->salesLeader->assignRole('sales_leader');

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        // Create contract and unit
        $this->contract = Contract::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'approved',
            'is_off_plan' => false,
        ]);

        $secondParty = SecondPartyData::factory()->create([
            'contract_id' => $this->contract->id,
        ]);

        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondParty->id,
            'price' => 500000,
            'status' => 'available',
        ]);
    }

    public function test_sales_leader_can_view_pending_negotiations()
    {
        // Create a negotiation approval
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        NegotiationApproval::create([
            'sales_reservation_id' => $reservation->id,
            'requested_by' => $this->salesUser->id,
            'status' => 'pending',
            'negotiation_reason' => 'السعر',
            'original_price' => 500000,
            'proposed_price' => 450000,
            'deadline_at' => now()->addHours(48),
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->getJson('/api/sales/negotiations/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_regular_sales_user_cannot_view_negotiations()
    {
        $response = $this->actingAs($this->salesUser)
            ->getJson('/api/sales/negotiations/pending');

        $response->assertStatus(403);
    }

    public function test_sales_leader_can_approve_negotiation()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        $approval = NegotiationApproval::create([
            'sales_reservation_id' => $reservation->id,
            'requested_by' => $this->salesUser->id,
            'status' => 'pending',
            'negotiation_reason' => 'السعر',
            'original_price' => 500000,
            'proposed_price' => 450000,
            'deadline_at' => now()->addHours(48),
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/negotiations/{$approval->id}/approve", [
                'notes' => 'تمت الموافقة على السعر المقترح',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('negotiation_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'approved_by' => $this->salesLeader->id,
        ]);

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_sales_leader_can_reject_negotiation()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        $approval = NegotiationApproval::create([
            'sales_reservation_id' => $reservation->id,
            'requested_by' => $this->salesUser->id,
            'status' => 'pending',
            'negotiation_reason' => 'السعر',
            'original_price' => 500000,
            'proposed_price' => 450000,
            'deadline_at' => now()->addHours(48),
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/negotiations/{$approval->id}/reject", [
                'reason' => 'السعر المقترح منخفض جداً',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('negotiation_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
            'approved_by' => $this->salesLeader->id,
        ]);
    }

    public function test_cannot_approve_expired_negotiation()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        $approval = NegotiationApproval::create([
            'sales_reservation_id' => $reservation->id,
            'requested_by' => $this->salesUser->id,
            'status' => 'expired',
            'negotiation_reason' => 'السعر',
            'original_price' => 500000,
            'proposed_price' => 450000,
            'deadline_at' => now()->subHours(1),
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/negotiations/{$approval->id}/approve");

        $response->assertStatus(422);
    }

    public function test_expire_command_expires_overdue_approvals()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        // Create an overdue approval
        $approval = NegotiationApproval::create([
            'sales_reservation_id' => $reservation->id,
            'requested_by' => $this->salesUser->id,
            'status' => 'pending',
            'negotiation_reason' => 'السعر',
            'original_price' => 500000,
            'proposed_price' => 450000,
            'deadline_at' => now()->subHours(1), // Past deadline
        ]);

        $this->artisan('negotiations:expire')
            ->assertExitCode(0);

        $this->assertDatabaseHas('negotiation_approvals', [
            'id' => $approval->id,
            'status' => 'expired',
        ]);
    }

    public function test_full_negotiation_workflow_converts_to_confirmed_and_notifies_credit()
    {
        // Create credit permission and role if not exists
        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);
        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->givePermissionTo('credit.bookings.view');
        
        // Create credit user
        $creditUser = User::factory()->create(['type' => 'credit']);
        $creditUser->assignRole('credit');

        // Create negotiation reservation
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
            'proposed_price' => 450000,
        ]);

        $approval = NegotiationApproval::create([
            'sales_reservation_id' => $reservation->id,
            'requested_by' => $this->salesUser->id,
            'status' => 'pending',
            'negotiation_reason' => 'السعر',
            'original_price' => 500000,
            'proposed_price' => 450000,
            'deadline_at' => now()->addHours(48),
        ]);

        // Approve the negotiation
        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/negotiations/{$approval->id}/approve", [
                'notes' => 'Approved with discount'
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify reservation is now confirmed
        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);

        $reservation->refresh();
        $this->assertNotNull($reservation->confirmed_at);

        // Verify approval is marked as approved
        $this->assertDatabaseHas('negotiation_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'approved_by' => $this->salesLeader->id,
        ]);

        // Verify marketer is notified
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->salesUser->id,
        ]);

        // Verify credit department is notified
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $creditUser->id,
        ]);

        // Verify booking appears in credit section (view-only)
        $creditResponse = $this->actingAs($creditUser)
            ->getJson('/api/credit/bookings/confirmed');

        $creditResponse->assertStatus(200);
        // The booking should be visible to credit department
        $this->assertTrue(
            collect($creditResponse->json('data'))->contains(function ($booking) use ($reservation) {
                return $booking['id'] === $reservation->id;
            })
        );
    }
}

