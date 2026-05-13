<?php

namespace Tests\Feature\Accounting;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ProjectRewardSetting;
use App\Models\SalesReservation;
use App\Models\SalesReservationParticipant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class ProjectRewardPreviewPhase1Test extends TestCase
{
    use RefreshDatabase;
    use TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoleWithPermissions('accounting', [
            'accounting.project-reward-settings.view',
            'accounting.project-reward-settings.manage',
            'accounting.project-rewards.view',
        ]);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createActiveSetting(Contract $contract, array $overrides = []): ProjectRewardSetting
    {
        return ProjectRewardSetting::query()->create(array_merge([
            'contract_id' => $contract->id,
            'calculation_mode' => 'percentage_of_sale',
            'reward_percentage' => 10,
            'source' => 'company',
            'tax_enabled' => false,
            'vat_percentage' => 15,
            'assigned_bring_percentage' => 0,
            'assigned_convince_percentage' => 0,
            'assigned_close_percentage' => 0,
            'outside_bring_percentage' => 0,
            'outside_convince_percentage' => 0,
            'outside_close_percentage' => 0,
            'ceo_percentage' => 0,
            'sales_manager_percentage' => 0,
            'sales_leader_percentage' => 0,
            'group_leader_percentage' => 0,
            'external_marketer_percentage' => 0,
            'is_active' => true,
            'created_by' => $this->accountingUser->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $reservationOverrides
     * @return array{contract: Contract, unit: ContractUnit, reservation: SalesReservation, seller: User}
     */
    protected function baseReservationScenario(array $reservationOverrides = []): array
    {
        $contract = Contract::factory()->create();
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'price' => 1000000,
        ]);
        $seller = User::factory()->create(['type' => 'sales']);
        $reservation = SalesReservation::factory()->create(array_merge([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $seller->id,
            'proposed_price' => 1200000,
        ], $reservationOverrides));

        SalesReservationParticipant::query()->create([
            'sales_reservation_id' => $reservation->id,
            'user_id' => $seller->id,
            'did_bring' => false,
            'did_convince' => false,
            'did_close' => false,
            'weight' => 1,
            'notes' => null,
            'created_by' => $seller->id,
        ]);

        return compact('contract', 'unit', 'reservation', 'seller');
    }

    public function test_preview_percentage_of_sale(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $this->createActiveSetting($ctx['contract'], [
            'reward_percentage' => 5,
            'tax_enabled' => true,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")
            ->assertOk()
            ->assertJsonPath('data.calculation_mode', 'percentage_of_sale')
            ->assertJsonPath('data.calculation_base_amount', 1200000)
            ->assertJsonPath('data.base_amount', 60000)
            ->assertJsonPath('data.vat_amount', 9000)
            ->assertJsonPath('data.total_amount', 69000)
            ->assertJsonPath('data.distribution_pool_amount', 60000);
    }

    public function test_preview_manual_amount(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $this->createActiveSetting($ctx['contract'], [
            'calculation_mode' => 'manual_amount',
            'reward_percentage' => null,
            'tax_enabled' => false,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward", [
            'manual_amount' => 50000,
        ])
            ->assertOk()
            ->assertJsonPath('data.calculation_mode', 'manual_amount')
            ->assertJsonPath('data.base_amount', 50000)
            ->assertJsonPath('data.vat_amount', 0)
            ->assertJsonPath('data.distribution_pool_amount', 50000);
    }

    public function test_assigned_participant_gets_bring_bucket(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);
        $ctx['seller']->update(['team_id' => $team->id]);
        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => true]);
        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 50,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")->assertOk();
        $row = collect($resp->json('data.recipients'))->firstWhere('user_id', $ctx['seller']->id);

        $this->assertNotNull($row);
        $this->assertSame('assigned_project_team', $row['source_scope']);
        $this->assertSame('bring', $row['source_type']);
        $this->assertSame(60000.0, (float) $row['amount']);
    }

    public function test_outside_participant_gets_outside_bucket(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $ctx['contract']->teams()->attach(Team::factory()->create()->id);
        $ctx['seller']->update(['team_id' => Team::factory()->create()->id]);
        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => true]);
        $this->createActiveSetting($ctx['contract'], [
            'outside_bring_percentage' => 25,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")->assertOk();
        $row = collect($resp->json('data.recipients'))->firstWhere('user_id', $ctx['seller']->id);

        $this->assertNotNull($row);
        $this->assertSame('outside_project_team', $row['source_scope']);
        $this->assertSame('bring', $row['source_type']);
        $this->assertSame(30000.0, (float) $row['amount']);
    }

    public function test_split_by_weight_one_and_half(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario(['proposed_price' => 1000000]);
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);
        $ctx['seller']->update(['team_id' => $team->id]);
        $other = User::factory()->create(['type' => 'sales', 'team_id' => $team->id]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->delete();
        foreach ([[$ctx['seller']->id, 1], [$other->id, 0.5]] as [$userId, $weight]) {
            SalesReservationParticipant::query()->create([
                'sales_reservation_id' => $ctx['reservation']->id,
                'user_id' => $userId,
                'did_bring' => true,
                'did_convince' => false,
                'did_close' => false,
                'weight' => $weight,
                'notes' => null,
                'created_by' => $ctx['seller']->id,
            ]);
        }
        $this->createActiveSetting($ctx['contract'], [
            'reward_percentage' => 10,
            'assigned_bring_percentage' => 15,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")->assertOk();
        $rows = collect($resp->json('data.recipients'))->keyBy('user_id');

        $this->assertSame(10000.0, (float) $rows[$ctx['seller']->id]['amount']);
        $this->assertSame(5000.0, (float) $rows[$other->id]['amount']);
    }

    public function test_management_rows_included(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $ceo = User::factory()->create();
        $this->createActiveSetting($ctx['contract'], [
            'ceo_user_id' => $ceo->id,
            'ceo_percentage' => 10,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")->assertOk();
        $row = collect($resp->json('data.recipients'))->firstWhere('source_type', 'ceo');

        $this->assertNotNull($row);
        $this->assertSame($ceo->id, $row['user_id']);
        $this->assertSame(12000.0, (float) $row['amount']);
    }

    public function test_unresolved_returned_when_no_matching_participant(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $ctx['contract']->teams()->attach(Team::factory()->create()->id);
        $ctx['seller']->update(['team_id' => Team::factory()->create()->id]);
        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => true]);
        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 20,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")->assertOk();
        $row = collect($resp->json('data.unresolved'))->firstWhere('reason', 'no_matching_participants');

        $this->assertNotNull($row);
        $this->assertSame('assigned_project_team', $row['source_scope']);
        $this->assertSame(24000.0, (float) $row['amount']);
    }

    public function test_preview_writes_no_project_rewards_rows_or_commission_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $this->createActiveSetting($ctx['contract']);

        $this->assertTrue(Schema::hasTable('project_rewards'));
        $this->assertTrue(Schema::hasTable('project_reward_recipients'));
        $rewardsBefore = \App\Models\ProjectReward::count();
        $rewardRecipientsBefore = \App\Models\ProjectRewardRecipient::count();
        $commissionsBefore = Commission::count();
        $distributionsBefore = CommissionDistribution::count();

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-reward")
            ->assertOk();

        $this->assertSame($rewardsBefore, \App\Models\ProjectReward::count());
        $this->assertSame($rewardRecipientsBefore, \App\Models\ProjectRewardRecipient::count());
        $this->assertSame($commissionsBefore, Commission::count());
        $this->assertSame($distributionsBefore, CommissionDistribution::count());
    }
}
