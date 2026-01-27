<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\SalesWaitingList;
use App\Models\SalesReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesStaff;
    protected User $salesLeader;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sales staff
        $this->salesStaff = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
        ]);
        $this->salesStaff->syncRolesFromType();

        // Create sales leader
        $this->salesLeader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
        ]);
        $this->salesLeader->syncRolesFromType();

        // Create contract and unit
        $this->contract = Contract::factory()->create(['status' => 'approved']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);
        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'reserved',
        ]);
    }

    /** @test */
    public function sales_staff_can_create_waiting_list_entry()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'client_name' => 'John Doe',
            'client_mobile' => '0501234567',
            'client_email' => 'john@example.com',
            'priority' => 1,
            'notes' => 'Interested client',
        ];

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->postJson('/api/sales/waiting-list', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Waiting list entry created successfully',
            ]);

        $this->assertDatabaseHas('sales_waiting_list', [
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'client_name' => 'John Doe',
            'status' => 'waiting',
        ]);
    }

    /** @test */
    public function can_retrieve_waiting_list_entries()
    {
        SalesWaitingList::factory()->count(3)->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $this->salesStaff->id,
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->getJson('/api/sales/waiting-list');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function can_get_waiting_list_for_specific_unit()
    {
        SalesWaitingList::factory()->count(2)->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $this->salesStaff->id,
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->getJson("/api/sales/waiting-list/unit/{$this->unit->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function sales_leader_can_convert_waiting_list_to_reservation()
    {
        // Make unit available
        $this->unit->update(['status' => 'available']);

        $waitingEntry = SalesWaitingList::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $this->salesStaff->id,
            'status' => 'waiting',
        ]);

        $data = [
            'contract_date' => now()->format('Y-m-d'),
            'reservation_type' => 'confirmed_reservation',
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA1234567890',
            'payment_method' => 'bank_transfer',
            'down_payment_amount' => 50000,
            'down_payment_status' => 'non_refundable',
            'purchase_mechanism' => 'cash',
        ];

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->postJson("/api/sales/waiting-list/{$waitingEntry->id}/convert", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Waiting list entry converted to reservation successfully',
            ]);

        $this->assertDatabaseHas('sales_waiting_list', [
            'id' => $waitingEntry->id,
            'status' => 'converted',
        ]);

        $this->assertDatabaseHas('sales_reservations', [
            'contract_unit_id' => $this->unit->id,
            'status' => 'confirmed',
        ]);
    }

    /** @test */
    public function sales_staff_cannot_convert_waiting_list()
    {
        $waitingEntry = SalesWaitingList::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $this->salesStaff->id,
            'status' => 'waiting',
        ]);

        $data = [
            'contract_date' => now()->format('Y-m-d'),
            'reservation_type' => 'confirmed_reservation',
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA1234567890',
            'payment_method' => 'bank_transfer',
            'down_payment_amount' => 50000,
            'down_payment_status' => 'non_refundable',
            'purchase_mechanism' => 'cash',
        ];

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->postJson("/api/sales/waiting-list/{$waitingEntry->id}/convert", $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function can_cancel_waiting_list_entry()
    {
        $waitingEntry = SalesWaitingList::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $this->salesStaff->id,
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($this->salesStaff, 'sanctum')
            ->deleteJson("/api/sales/waiting-list/{$waitingEntry->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Waiting list entry cancelled successfully',
            ]);

        $this->assertDatabaseHas('sales_waiting_list', [
            'id' => $waitingEntry->id,
            'status' => 'cancelled',
        ]);
    }
}
