<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use App\Models\SalesAttendanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $leader;
    protected User $marketer;
    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->leader = User::factory()->create(['type' => 'sales', 'is_manager' => true, 'team' => 'Team A']);
        $this->leader->assignRole('sales_leader');

        $this->marketer = User::factory()->create(['type' => 'sales', 'team' => 'Team A']);
        $this->marketer->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'ready']);
        $spd = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);
        ContractUnit::factory()->create(['second_party_data_id' => $spd->id, 'price' => 1000]);
    }

    public function test_reservation_list_filters()
    {
        Sanctum::actingAs($this->marketer);

        // Create one active and one cancelled
        SalesReservation::factory()->create([
            'marketing_employee_id' => $this->marketer->id,
            'status' => 'confirmed',
            'created_at' => now()->subDays(5)
        ]);
        SalesReservation::factory()->create([
            'marketing_employee_id' => $this->marketer->id,
            'status' => 'cancelled',
            'created_at' => now()
        ]);

        // Default: exclude cancelled
        $response = $this->getJson('/api/sales/reservations');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Include cancelled
        $response = $this->getJson('/api/sales/reservations?include_cancelled=true');
        $this->assertCount(2, $response->json('data'));

        // Date filter
        $from = now()->subDays(2)->toDateString();
        $response = $this->getJson("/api/sales/reservations?from={$from}&include_cancelled=true");
        $this->assertCount(1, $response->json('data'));
    }

    public function test_schedule_overlap_validation()
    {
        Sanctum::actingAs($this->leader);

        SalesAttendanceSchedule::create([
            'user_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'schedule_date' => '2026-02-01',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'created_by' => $this->leader->id
        ]);

        // Overlapping
        $response = $this->postJson('/api/sales/attendance/schedules', [
            'user_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'schedule_date' => '2026-02-01',
            'start_time' => '11:00:00',
            'end_time' => '14:00:00',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('overlaps', $response->json('message'));
    }

    public function test_emergency_contacts_update_leader_not_assigned()
    {
        $otherLeader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $otherLeader->assignRole('sales_leader');

        Sanctum::actingAs($otherLeader);

        $response = $this->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", [
            'emergency_contact_number' => '123456789'
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('not assigned', $response->json('message'));
    }

    public function test_voucher_download_error_paths()
    {
        Sanctum::actingAs($this->marketer);

        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $this->marketer->id,
            'voucher_pdf_path' => null
        ]);

        // Missing path
        $this->getJson("/api/sales/reservations/{$reservation->id}/voucher")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Voucher not found');

        // Missing file
        $reservation->update(['voucher_pdf_path' => 'nonexistent.pdf']);
        $this->getJson("/api/sales/reservations/{$reservation->id}/voucher")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Voucher file is missing');
    }
}
