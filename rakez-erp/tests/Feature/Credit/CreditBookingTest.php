<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\SalesWaitingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreditBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);

        // Create role
        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view']);

        // Create user
        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');
    }

    public function test_can_list_confirmed_bookings(): void
    {
        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);

        SalesReservation::factory()->count(3)->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'status' => 'confirmed',
            'credit_status' => 'pending',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/bookings/confirmed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_can_filter_bookings_by_credit_status(): void
    {
        SalesReservation::factory()->count(2)->create([
            'status' => 'confirmed',
            'credit_status' => 'in_progress',
        ]);

        SalesReservation::factory()->create([
            'status' => 'confirmed',
            'credit_status' => 'pending',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/bookings/confirmed?credit_status=in_progress');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_list_negotiation_bookings(): void
    {
        SalesReservation::factory()->count(2)->create([
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/bookings/negotiation');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_list_waiting_bookings(): void
    {
        // Test that the endpoint returns 200 even with no data
        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/bookings/waiting');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['total'],
            ]);
    }

    public function test_can_show_booking_details(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson("/api/credit/bookings/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'project',
                    'unit',
                    'client',
                    'financial',
                    'marketing',
                    'status',
                    'credit_status',
                    'credit_procedure_steps',
                ],
            ])
            ->assertJsonPath('data.credit_procedure_steps.0.label_ar', 'التواصل مع العميل')
            ->assertJsonPath('data.credit_procedure_steps.1.label_ar', 'رفع الطلب للبنك')
            ->assertJsonPath('data.credit_procedure_steps.6.label_ar', 'فترة التجهيز قبل الإفراغ');
    }

    public function test_returns_404_for_nonexistent_booking(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/bookings/99999');

        $response->assertStatus(404);
    }
}

