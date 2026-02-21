<?php

namespace Tests\Feature\Sales;

use App\Models\Commission;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;
    protected Contract $contract;
    protected ContractUnit $unit;
    protected SalesReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);

        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'sold',
            'price' => 900000,
            'unit_number' => 'A-22',
        ]);

        $this->reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
            'client_name' => 'Client X',
            'confirmed_at' => now(),
        ]);

        Commission::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_reservation_id' => $this->reservation->id,
            'final_selling_price' => 950000,
            'commission_percentage' => 2.5,
            'commission_source' => 'owner',
            'status' => 'approved',
            'net_amount' => 20000,
            'total_amount' => 23750,
            'vat' => 3562.5,
            'marketing_expenses' => 0,
            'bank_fees' => 187.5,
        ]);

        Deposit::factory()->create([
            'sales_reservation_id' => $this->reservation->id,
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'amount' => 100000,
            'status' => 'confirmed',
            'commission_source' => 'owner',
            'payment_method' => 'bank_transfer',
            'client_name' => 'Client X',
        ]);
    }

    public function test_sales_sold_units_endpoint_returns_expected_fields(): void
    {
        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/sold-units');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'unit_id',
                        'project_name',
                        'unit_number',
                        'unit_type',
                        'final_selling_price',
                        'commission_source',
                        'commission_percentage',
                    ],
                ],
            ]);
    }

    public function test_sales_unit_commission_summary_endpoint_returns_summary(): void
    {
        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/sold-units/{$this->unit->id}/commission-summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'commission_id',
                    'final_selling_price',
                    'commission_percentage',
                    'total_before_tax',
                    'vat',
                    'net_amount',
                    'distributions',
                ],
            ]);
    }

    public function test_sales_deposits_management_endpoint_returns_data(): void
    {
        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/deposits/management');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'deposit_id',
                        'project_name',
                        'unit_number',
                        'deposit_amount',
                        'payment_method',
                        'status',
                        'can_refund',
                    ],
                ],
            ]);
    }

    public function test_sales_deposits_follow_up_endpoint_returns_data(): void
    {
        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/deposits/follow-up');

        $payload = $response->json();
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertIsArray($payload['data'] ?? null);
        $this->assertNotEmpty($payload['data']);
        $first = array_values($payload['data'])[0];
        $this->assertArrayHasKey('deposit_id', $first);
        $this->assertArrayHasKey('project_name', $first);
        $this->assertArrayHasKey('unit_number', $first);
        $this->assertArrayHasKey('client_name', $first);
        $this->assertArrayHasKey('commission_source', $first);
        $this->assertArrayHasKey('is_refundable', $first);
    }
}
