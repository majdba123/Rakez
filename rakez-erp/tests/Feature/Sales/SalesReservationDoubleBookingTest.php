<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReservationDoubleBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser1;
    protected User $salesUser2;
    protected array $reservationData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser1 = User::factory()->create(['type' => 'sales']);
        $this->salesUser1->assignRole('sales');

        $this->salesUser2 = User::factory()->create(['type' => 'sales']);
        $this->salesUser2->assignRole('sales');
    }

    protected function createReservableUnit(): array
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        return [
            'contract' => $contract,
            'unit' => $unit,
        ];
    }

    protected function getReservationData(int $contractId, int $unitId): array
    {
        return [
            'contract_id' => $contractId,
            'contract_unit_id' => $unitId,
            'contract_date' => '2025-01-25',
            'reservation_type' => 'confirmed_reservation',
            'client_name' => 'Test Client',
            'client_mobile' => '0501234567',
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA0000000000000000000000',
            'payment_method' => 'cash',
            'down_payment_amount' => 50000,
            'down_payment_status' => 'non_refundable',
            'purchase_mechanism' => 'cash',
        ];
    }

    public function test_first_reservation_succeeds()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);

        $response = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Reservation created successfully',
            ]);

        $this->assertDatabaseHas('sales_reservations', [
            'contract_unit_id' => $data['unit']->id,
            'marketing_employee_id' => $this->salesUser1->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_second_reservation_fails_with_409_conflict()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);

        // First request succeeds
        $response1 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);
        $response1->assertStatus(201);

        // Second request fails
        $response2 = $this->actingAs($this->salesUser2, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);

        $response2->assertStatus(409)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment([
                'message' => 'Unit already reserved',
            ]);

        // Verify only one reservation exists
        $this->assertEquals(1, SalesReservation::where('contract_unit_id', $data['unit']->id)->count());
    }

    public function test_reservation_after_cancellation_succeeds()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);

        // First reservation
        $response1 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);
        $response1->assertStatus(201);

        $reservationId = $response1->json('data.reservation_id');

        // Cancel first reservation
        $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservationId}/cancel");

        // Second reservation should succeed
        $response2 = $this->actingAs($this->salesUser2, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);

        $response2->assertStatus(201);

        // Verify two reservations exist (one cancelled, one active)
        $this->assertEquals(2, SalesReservation::where('contract_unit_id', $data['unit']->id)->count());
        $this->assertEquals(1, SalesReservation::where('contract_unit_id', $data['unit']->id)
            ->where('status', 'cancelled')->count());
        $this->assertEquals(1, SalesReservation::where('contract_unit_id', $data['unit']->id)
            ->where('status', 'confirmed')->count());
    }

    public function test_negotiation_reservation_prevents_double_booking()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);
        $reservationData['reservation_type'] = 'negotiation';
        $reservationData['negotiation_notes'] = 'Test negotiation notes';

        // First negotiation reservation
        $response1 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);
        $response1->assertStatus(201);

        // Second attempt should fail
        $reservationData2 = $this->getReservationData($data['contract']->id, $data['unit']->id);
        $response2 = $this->actingAs($this->salesUser2, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData2);

        $response2->assertStatus(409);
    }

    public function test_confirmed_reservation_prevents_negotiation()
    {
        $data = $this->createReservableUnit();
        
        // Confirmed reservation first
        $confirmedData = $this->getReservationData($data['contract']->id, $data['unit']->id);
        $response1 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $confirmedData);
        $response1->assertStatus(201);

        // Negotiation attempt should fail
        $negotiationData = $this->getReservationData($data['contract']->id, $data['unit']->id);
        $negotiationData['reservation_type'] = 'negotiation';
        $negotiationData['negotiation_notes'] = 'Test notes';
        
        $response2 = $this->actingAs($this->salesUser2, 'sanctum')
            ->postJson('/api/sales/reservations', $negotiationData);

        $response2->assertStatus(409);
    }

    public function test_unit_status_updated_to_reserved_on_reservation()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);

        $this->assertEquals('available', $data['unit']->fresh()->status);

        $response = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);

        $response->assertStatus(201);

        $this->assertEquals('reserved', $data['unit']->fresh()->status);
    }

    public function test_unit_status_reverted_to_available_on_cancellation()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);

        // Create reservation
        $response1 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);
        $reservationId = $response1->json('data.reservation_id');

        $this->assertEquals('reserved', $data['unit']->fresh()->status);

        // Cancel reservation
        $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservationId}/cancel");

        $this->assertEquals('available', $data['unit']->fresh()->status);
    }

    public function test_multiple_cancelled_reservations_allowed_for_same_unit()
    {
        $data = $this->createReservableUnit();
        $reservationData = $this->getReservationData($data['contract']->id, $data['unit']->id);

        // First reservation
        $response1 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);
        $reservationId1 = $response1->json('data.reservation_id');

        // Cancel it
        $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservationId1}/cancel");

        // Second reservation
        $response2 = $this->actingAs($this->salesUser2, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);
        $reservationId2 = $response2->json('data.reservation_id');

        // Cancel it
        $this->actingAs($this->salesUser2, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservationId2}/cancel");

        // Third reservation should succeed
        $response3 = $this->actingAs($this->salesUser1, 'sanctum')
            ->postJson('/api/sales/reservations', $reservationData);

        $response3->assertStatus(201);

        // Verify three reservations exist, two cancelled
        $this->assertEquals(3, SalesReservation::where('contract_unit_id', $data['unit']->id)->count());
        $this->assertEquals(2, SalesReservation::where('contract_unit_id', $data['unit']->id)
            ->where('status', 'cancelled')->count());
    }
}
