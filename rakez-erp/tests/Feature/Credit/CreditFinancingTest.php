<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\CreditFinancingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreditFinancingTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'credit.financing.manage', 'guard_name' => 'web']);

        // Create role
        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view', 'credit.financing.manage']);

        // Create user
        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');
    }

    public function test_can_initialize_financing_tracker(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing");

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.stage_1_status', 'in_progress');

        $this->assertDatabaseHas('credit_financing_trackers', [
            'sales_reservation_id' => $reservation->id,
            'stage_1_status' => 'in_progress',
            'overall_status' => 'in_progress',
        ]);
    }

    public function test_cannot_initialize_tracker_for_cash_purchase(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'cash',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing");

        $response->assertStatus(400);
    }

    public function test_cannot_initialize_duplicate_tracker(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'unsupported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing");

        $response->assertStatus(400);
    }

    public function test_can_complete_stage_1(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $tracker = CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/financing/{$tracker->id}/stage/1", [
                'bank_name' => 'البنك الأهلي',
                'client_salary' => 15000,
                'employment_type' => 'government',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.stage_1_status', 'completed')
            ->assertJsonPath('data.stage_2_status', 'in_progress');
    }

    public function test_stage_1_requires_bank_name(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $tracker = CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/financing/{$tracker->id}/stage/1", [
                'client_salary' => 15000,
            ]);

        // Bank name is required for stage 1 - returns 422 from request validation
        // or 400 from service validation
        $response->assertStatus(400);
    }

    public function test_cannot_skip_stages(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $tracker = CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'stage_1_status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/financing/{$tracker->id}/stage/3", []);

        $response->assertStatus(400);
    }

    public function test_can_reject_financing(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        $tracker = CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/financing/{$tracker->id}/reject", [
                'reason' => 'تم رفض طلب التمويل من البنك',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.overall_status', 'rejected');

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'credit_status' => 'rejected',
        ]);
    }

    public function test_can_get_financing_tracker_status(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson("/api/credit/bookings/{$reservation->id}/financing");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tracker',
                    'progress_summary',
                    'current_stage',
                    'remaining_days',
                ],
            ]);
    }
}

