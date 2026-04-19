<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $this->contract = Contract::factory()->create(['status' => 'completed']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);
        
        $this->unit = ContractUnit::factory()->create([
            'contract_id' => $secondPartyData->contract_id,
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
                'reservation_type',
                'client_name',
                'client_mobile',
                'down_payment_amount',
            ]);
    }

    public function test_negotiation_type_requires_negotiation_notes()
    {
        $data = $this->getValidReservationData();
        $data['reservation_type'] = 'negotiation';

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['proposed_price']);
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
        $this->assertTrue(Storage::disk('public')->exists($reservation->voucher_pdf_path));
    }

    public function test_create_reservation_can_upload_receipt_voucher()
    {
        $data = $this->getValidReservationData();
        $data['receipt_voucher'] = UploadedFile::fake()->image('receipt-voucher.jpg');

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.receipt_voucher_path', fn ($path) => is_string($path) && str_starts_with($path, 'reservations/receipts/'))
            ->assertJsonPath('data.receipt_voucher_url', fn ($url) => is_string($url) && str_contains($url, 'reservations/receipts/'));

        $reservation = SalesReservation::first();
        $this->assertNotNull($reservation->receipt_voucher_path);
        $this->assertTrue(Storage::disk('public')->exists($reservation->receipt_voucher_path));
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
        $data['negotiation_reason'] = 'السعر';
        $data['proposed_price'] = 450000;

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

    public function test_confirmed_reservation_creates_deposit_automatically()
    {
        // Test that creating a confirmed reservation automatically creates a deposit
        $data = $this->getValidReservationData();
        $data['reservation_type'] = 'confirmed_reservation';
        $data['down_payment_amount'] = 150000;
        $data['payment_method'] = 'bank_transfer';

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertSame('confirmed', $reservation->status);

        // Verify deposit was created
        $this->assertDatabaseHas('deposits', [
            'sales_reservation_id' => $reservation->id,
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'amount' => 150000,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $deposit = Deposit::where('sales_reservation_id', $reservation->id)->first();
        $this->assertNotNull($deposit);
        $this->assertSame($reservation->client_name, $deposit->client_name);
        // Commission source should be a valid enum value (owner or buyer)
        $this->assertContains($deposit->commission_source, ['owner', 'buyer']);
    }

    public function test_negotiation_reservation_does_not_create_deposit()
    {
        // Test that negotiation reservations don't create deposits (only confirmed ones do)
        $data = $this->getValidReservationData();
        $data['reservation_type'] = 'negotiation';
        $data['proposed_price'] = 450000; // Must be less than unit price (500000)
        $data['negotiation_notes'] = 'Client wants discount';

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertSame('under_negotiation', $reservation->status);

        // Verify NO deposit was created
        $this->assertDatabaseMissing('deposits', [
            'sales_reservation_id' => $reservation->id,
        ]);
    }

    public function test_created_deposit_appears_in_accounting_queue()
    {
        // Test that a deposit created from reservation appears in accounting queue
        $data = $this->getValidReservationData();
        $data['down_payment_amount'] = 250000;
        $data['client_name'] = 'Khalid Ahmed';

        // Create the reservation (which also creates a deposit)
        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);
        $response->assertStatus(201);

        $reservation = SalesReservation::first();

        // Set up accounting user with proper role and permission
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $accountingUser = User::factory()->create(['type' => 'accounting']);
        $accountingUser->assignRole('accounting');
        $accountingUser->givePermissionTo('accounting.deposits.view');

        // Get accounting queue
        $response = $this->actingAs($accountingUser, 'sanctum')
            ->getJson('/api/accounting/deposits/pending');

        $response->assertStatus(200);

        // Find the deposit row for our reservation
        $depositRows = collect($response->json('data'))->filter(fn ($row) =>
            $row['reservation_id'] === $reservation->id && $row['row_entity'] === 'deposit'
        );

        $this->assertGreaterThan(0, $depositRows->count(), 'Created deposit should appear in accounting queue');

        $depositRow = $depositRows->first();
        $this->assertSame('deposit', $depositRow['row_entity']);
        $this->assertSame('deposit_pending_confirmation', $depositRow['accounting_state']);
        $this->assertSame('Khalid Ahmed', $depositRow['client']['name']);
        $this->assertEquals(250000.0, (float) $depositRow['amount']);
        $this->assertSame('bank_transfer', $depositRow['payment_method']);
    }

    public function test_deposit_amount_matches_down_payment_from_reservation()
    {
        // Test that deposit amount is correctly set to down_payment_amount
        $data = $this->getValidReservationData();
        $testAmount = 175000;
        $data['down_payment_amount'] = $testAmount;

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);
        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $deposit = Deposit::where('sales_reservation_id', $reservation->id)->first();

        $this->assertEquals($testAmount, (float) $deposit->amount);
        $this->assertEquals($reservation->down_payment_amount, $deposit->amount);
    }

    public function test_deposit_payment_method_matches_reservation()
    {
        // Test that deposit payment method is correctly taken from reservation
        $paymentMethods = ['cash', 'bank_transfer', 'bank_financing'];

        foreach ($paymentMethods as $method) {
            $this->unit->refresh();
            $data = $this->getValidReservationData();
            $data['payment_method'] = $method;

            $response = $this->actingAs($this->salesUser, 'sanctum')
                ->postJson('/api/sales/reservations', $data);
            $response->assertStatus(201);

            $reservation = SalesReservation::latest()->first();
            $deposit = Deposit::where('sales_reservation_id', $reservation->id)->first();

            $this->assertSame($method, $deposit->payment_method,
                "Deposit payment method should match reservation for method: $method"
            );

            // Clean up for next iteration
            $reservation->delete();
            $deposit->delete();
        }
    }

    public function test_reservation_without_down_payment_does_not_create_deposit()
    {
        // Test that reservations with zero down_payment don't create deposits
        $data = $this->getValidReservationData();
        $data['down_payment_amount'] = 0;

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);
        $response->assertStatus(201);

        $reservation = SalesReservation::first();

        // Verify NO deposit was created (since amount is 0)
        $this->assertDatabaseMissing('deposits', [
            'sales_reservation_id' => $reservation->id,
        ]);
    }

    public function test_complete_sales_to_accounting_flow()
    {
        // Test the complete flow: sales creates reservation -> deposit created -> accounting sees it
        
        // Step 1: Sales creates a confirmed reservation
        $data = $this->getValidReservationData();
        $data['client_name'] = 'Mohammed Hassan';
        $data['down_payment_amount'] = 300000;

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $data);
        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertSame('confirmed', $reservation->status);

        // Step 2: Verify deposit was created with correct data
        $deposit = Deposit::where('sales_reservation_id', $reservation->id)->first();
        $this->assertNotNull($deposit);
        $this->assertSame('pending', $deposit->status);
        $this->assertSame('Mohammed Hassan', $deposit->client_name);
        $this->assertEquals(300000.0, (float) $deposit->amount);

        // Step 3: Accounting views the pending deposits
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $accountingUser = User::factory()->create(['type' => 'accounting']);
        $accountingUser->assignRole('accounting');
        $accountingUser->givePermissionTo('accounting.deposits.view');

        $response = $this->actingAs($accountingUser, 'sanctum')
            ->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertStatus(200);

        // Step 4: Verify the deposit appears in accounting queue
        $depositRows = collect($response->json('data'))->filter(fn ($row) =>
            $row['deposit_id'] === $deposit->id
        );

        $this->assertCount(1, $depositRows, 'Deposit should appear in accounting queue');

        $row = $depositRows->first();
        $this->assertSame('deposit', $row['row_entity']);
        $this->assertSame('deposit_pending_confirmation', $row['accounting_state']);
        $this->assertSame($reservation->id, $row['reservation_id']);
        $this->assertSame($deposit->id, $row['deposit_id']);
        $this->assertSame('Mohammed Hassan', $row['client']['name']);
        $this->assertEquals(300000.0, (float) $row['amount']);
        $this->assertSame($this->contract->id, $row['project']['contract_id']);
        $this->assertSame($this->unit->id, $row['unit']['id']);

        // Step 5: Accounting confirms the deposit receipt
        $response = $this->actingAs($accountingUser, 'sanctum')
            ->postJson("/api/accounting/deposits/{$deposit->id}/confirm");
        $response->assertStatus(200);

        // Step 6: Verify deposit status changed to confirmed
        $deposit->refresh();
        $this->assertSame('confirmed', $deposit->status);
        $this->assertNotNull($deposit->confirmed_at);
    }
}
