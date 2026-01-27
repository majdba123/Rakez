<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);
        
        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);
    }

    protected function getValidReservationData(): array
    {
        return [
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'contract_date' => '2025-01-25',
            'reservation_type' => 'confirmed_reservation',
            'client_name' => 'Ahmed Ali',
            'client_mobile' => '0501234567',
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA0000000000000000000000',
            'payment_method' => 'bank_transfer',
            'down_payment_amount' => 100000,
            'down_payment_status' => 'non_refundable',
            'purchase_mechanism' => 'supported_bank',
        ];
    }

    public function test_create_reservation_requires_all_fields()
    {
        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'contract_id',
                'contract_unit_id',
                'contract_date',
                'reservation_type',
                'client_name',
                'client_mobile',
                'client_nationality',
                'client_iban',
                'payment_method',
                'down_payment_amount',
                'down_payment_status',
                'purchase_mechanism',
            ]);
    }

    public function test_negotiation_type_requires_negotiation_notes()
    {
        $data = $this->getValidReservationData();
        $data['reservation_type'] = 'negotiation';

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['negotiation_notes']);
    }

    public function test_create_reservation_generates_voucher_pdf()
    {
        $data = $this->getValidReservationData();

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertNotNull($reservation->voucher_pdf_path);
        
        // Verify PDF file exists
        Storage::disk('public')->assertExists($reservation->voucher_pdf_path);
    }

    public function test_create_reservation_stores_snapshot()
    {
        $data = $this->getValidReservationData();

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertNotNull($reservation->snapshot);
        $this->assertIsArray($reservation->snapshot);
        $this->assertArrayHasKey('project', $reservation->snapshot);
        $this->assertArrayHasKey('unit', $reservation->snapshot);
        $this->assertArrayHasKey('employee', $reservation->snapshot);
        $this->assertArrayHasKey('client', $reservation->snapshot);
        $this->assertArrayHasKey('payment', $reservation->snapshot);
        
        $this->assertEquals('Saudi', $reservation->snapshot['client']['nationality']);
        $this->assertEquals('SA0000000000000000000000', $reservation->snapshot['client']['iban']);
        $this->assertEquals('non_refundable', $reservation->snapshot['payment']['status']);
        $this->assertEquals('supported_bank', $reservation->snapshot['payment']['mechanism']);
    }

    public function test_log_action_with_arabic_types()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $arabicTypes = ['lead_acquisition', 'persuasion', 'closing'];

        foreach ($arabicTypes as $type) {
            $response = $this->actingAs($this->salesUser, 'sanctum')
                ->postJson("/api/sales/reservations/{$reservation->id}/actions", [
                    'action_type' => $type,
                    'notes' => "Action of type $type",
                ]);

            $response->assertStatus(201);
            
            $this->assertDatabaseHas('sales_reservation_actions', [
                'sales_reservation_id' => $reservation->id,
                'action_type' => $type,
            ]);
        }
    }

    public function test_negotiation_reservation_creates_under_negotiation_status()
    {
        $data = $this->getValidReservationData();
        $data['reservation_type'] = 'negotiation';
        $data['negotiation_notes'] = 'Client wants 10% discount';

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales_reservations', [
            'contract_unit_id' => $this->unit->id,
            'status' => 'under_negotiation',
        ]);
    }

    public function test_confirmed_reservation_creates_confirmed_status()
    {
        $data = $this->getValidReservationData();
        $data['reservation_type'] = 'confirmed_reservation';

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales_reservations', [
            'contract_unit_id' => $this->unit->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirm_reservation_updates_status()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/confirm");

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);

        $reservation->refresh();
        $this->assertNotNull($reservation->confirmed_at);
    }

    public function test_cannot_confirm_already_confirmed_reservation()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/confirm");

        $response->assertStatus(400);
    }

    public function test_cancel_reservation_updates_status()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/cancel", [
                'cancellation_reason' => 'Client withdrew',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);

        $reservation->refresh();
        $this->assertNotNull($reservation->cancelled_at);
    }

    public function test_log_action_on_reservation()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/actions", [
                'action_type' => 'lead_acquisition',
                'notes' => 'Initial contact with client',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['action_id', 'action_type', 'created_at'],
            ]);

        $this->assertDatabaseHas('sales_reservation_actions', [
            'sales_reservation_id' => $reservation->id,
            'user_id' => $this->salesUser->id,
            'action_type' => 'lead_acquisition',
        ]);
    }

    public function test_list_my_reservations()
    {
        // Create reservations for this user
        SalesReservation::factory()->count(3)->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        // Create reservation for another user
        $otherUser = User::factory()->create(['type' => 'sales']);
        $otherUser->assignRole('sales');
        SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $otherUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/reservations?mine=1');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_filter_reservations_by_status()
    {
        SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/reservations?mine=1&status=confirmed');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_download_voucher_returns_pdf()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
            'voucher_pdf_path' => 'reservations/test.pdf',
        ]);

        // Create a fake PDF file
        Storage::disk('public')->put('reservations/test.pdf', 'fake pdf content');

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->get("/api/sales/reservations/{$reservation->id}/voucher");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_cannot_download_other_users_voucher()
    {
        $otherUser = User::factory()->create(['type' => 'sales']);
        $otherUser->assignRole('sales');

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $otherUser->id,
            'status' => 'confirmed',
            'voucher_pdf_path' => 'reservations/test.pdf',
        ]);

        Storage::disk('public')->put('reservations/test.pdf', 'fake pdf content');

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->get("/api/sales/reservations/{$reservation->id}/voucher");

        $response->assertStatus(403);
    }
}
