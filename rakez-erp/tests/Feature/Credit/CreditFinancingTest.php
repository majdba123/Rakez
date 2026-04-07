<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\CreditFinancingTracker;
use App\Services\Credit\CreditFinancingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreditFinancingTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'credit.financing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'credit.financing.manage', 'guard_name' => 'web']);

        // Create role
        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view', 'credit.financing.view', 'credit.financing.manage']);

        // Create user
        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');
    }

    public function test_supported_bank_planned_total_is_25_calendar_days(): void
    {
        $tracker = new CreditFinancingTracker(['is_supported_bank' => true, 'is_cash_workflow' => false]);
        $this->assertSame(25, $tracker->getTotalExpectedDays());
    }

    public function test_unsupported_bank_planned_total_is_20_calendar_days(): void
    {
        $tracker = new CreditFinancingTracker(['is_supported_bank' => false, 'is_cash_workflow' => false]);
        $this->assertSame(20, $tracker->getTotalExpectedDays());
    }

    public function test_cash_workflow_planned_total_is_7_calendar_days(): void
    {
        $tracker = new CreditFinancingTracker(['is_cash_workflow' => true]);
        $this->assertSame(7, $tracker->getTotalExpectedDays());
    }

    public function test_stage_2_deadline_is_three_days_after_completing_stage_1(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 10:00:00'));

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing");

        $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/1", [
                'client_salary' => 15000,
            ]);

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservation->id)->first();
        $this->assertNotNull($tracker->stage_2_deadline);
        $this->assertSame(
            '2026-04-04',
            $tracker->stage_2_deadline->format('Y-m-d'),
            'Stage 2 duration must be 3 calendar days after completing stage 1'
        );
    }

    public function test_stage_6_deadline_is_ten_days_after_stage_5_for_supported_bank(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00'));

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $this->actingAs($this->creditUser)->postJson("/api/credit/bookings/{$reservation->id}/financing");
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->creditUser)
                ->postJson("/api/credit/bookings/{$reservation->id}/financing/advance", [
                    'client_salary' => 15000,
                    'appraiser_name' => $i === 3 ? 'Reviewer' : null,
                ]);
        }

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservation->id)->first();
        $this->assertSame('in_progress', $tracker->stage_6_status);
        $this->assertSame(
            '2026-05-11',
            $tracker->stage_6_deadline->format('Y-m-d'),
            'Supported bank: stage 6 must be 10 calendar days'
        );
    }

    public function test_stage_6_deadline_is_five_days_after_stage_5_for_unsupported_bank(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00'));

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'unsupported_bank',
        ]);

        $this->actingAs($this->creditUser)->postJson("/api/credit/bookings/{$reservation->id}/financing");
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->creditUser)
                ->postJson("/api/credit/bookings/{$reservation->id}/financing/advance", [
                    'client_salary' => 15000,
                    'appraiser_name' => $i === 3 ? 'Reviewer' : null,
                ]);
        }

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservation->id)->first();
        $this->assertSame('in_progress', $tracker->stage_6_status);
        $this->assertSame(
            '2026-05-06',
            $tracker->stage_6_deadline->format('Y-m-d'),
            'Unsupported bank: stage 6 must be 5 calendar days'
        );
    }

    public function test_completing_stage_6_sets_reservation_credit_status_to_title_transfer(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()->atStage6InProgress()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/6", []);

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'credit_status' => 'title_transfer',
        ]);

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservation->id)->first();
        $this->assertSame('completed', $tracker->overall_status);
        $this->assertSame('completed', $tracker->stage_6_status);
    }

    public function test_mark_overdue_still_marks_past_stage_6_deadline(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()
            ->atStage6InProgress()
            ->create([
                'sales_reservation_id' => $reservation->id,
                'stage_6_deadline' => now()->subDay(),
            ]);

        $count = app(CreditFinancingService::class)->markOverdueStages();
        $this->assertGreaterThanOrEqual(1, $count);

        $this->assertDatabaseHas('credit_financing_trackers', [
            'sales_reservation_id' => $reservation->id,
            'stage_6_status' => 'overdue',
        ]);
    }

    public function test_financing_show_includes_six_stage_progress_summary(): void
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
            ->assertJsonPath('data.progress_summary.stage_6.status', 'pending');
    }

    public function test_invalid_financing_stage_parameter_returns_validation_error(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/9", []);

        $response->assertStatus(422);
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

    public function test_can_initialize_tracker_for_cash_purchase_skips_middle_stages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'cash',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing");

        $response->assertStatus(201)
            ->assertJsonPath('data.is_cash_workflow', true)
            ->assertJsonPath('data.stage_1_status', 'in_progress')
            ->assertJsonPath('data.stage_2_status', 'completed')
            ->assertJsonPath('data.stage_6_status', 'pending');

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservation->id)->first();
        $this->assertTrue($tracker->is_cash_workflow);
        $this->assertSame(7, $tracker->getTotalExpectedDays());
        $this->assertSame(
            '2026-06-03',
            $tracker->stage_1_deadline->format('Y-m-d'),
            'Cash stage 1 uses 2 calendar days'
        );
    }

    public function test_cash_completing_stage_one_activates_stage_six_with_five_day_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 10:00:00'));

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'cash',
        ]);

        CreditFinancingTracker::factory()->cashPurchaseInProgress()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/1", [
                'client_salary' => 12000,
            ]);

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservation->id)->first();
        $this->assertSame('completed', $tracker->stage_1_status);
        $this->assertSame('in_progress', $tracker->stage_6_status);
        $this->assertSame(
            '2026-06-15',
            $tracker->stage_6_deadline->format('Y-m-d'),
            'Cash stage 6 uses 5 calendar days after stage 1'
        );
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
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/1", [
                'bank_name' => 'البنك الأهلي',
                'client_salary' => 15000,
                'employment_type' => 'government',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.stage_1_status', 'completed')
            ->assertJsonPath('data.stage_2_status', 'in_progress');
    }

    public function test_stage_1_can_complete_without_bank_name(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/1", [
                'client_salary' => 15000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stage_1_status', 'completed');
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
            ->patchJson("/api/credit/bookings/{$reservation->id}/financing/stage/3", []);

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
            ->postJson("/api/credit/bookings/{$reservation->id}/financing/reject", [
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
                    'financing',
                    'progress_summary',
                    'current_stage',
                    'remaining_days',
                    'booking_id',
                ],
            ])
            ->assertJsonPath('data.booking_id', $reservation->id);
    }

    public function test_advance_initializes_when_no_tracker(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing/advance", []);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.action', 'initialized')
            ->assertJsonPath('data.financing.stage_1_status', 'in_progress');

        $this->assertDatabaseHas('credit_financing_trackers', [
            'sales_reservation_id' => $reservation->id,
            'overall_status' => 'in_progress',
        ]);
    }

    public function test_advance_completes_current_stage_when_tracker_exists(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/financing/advance", [
                'bank_name' => 'بنك الراجحي',
                'client_salary' => 20000,
                'employment_type' => 'private',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.action', 'stage_completed')
            ->assertJsonPath('data.stage', 1)
            ->assertJsonPath('data.financing.stage_1_status', 'completed')
            ->assertJsonPath('data.financing.stage_2_status', 'in_progress');
    }
}

