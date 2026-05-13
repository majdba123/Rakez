<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingSalaryDistribution;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ProjectReward;
use App\Models\ProjectRewardRecipient;
use App\Models\ProjectRewardSetting;
use App\Models\SalesReservation;
use App\Models\SalesReservationParticipant;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class ProjectRewardWorkflowPhase2Phase3Test extends TestCase
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
            'accounting.project-rewards.manage',
            'accounting.project-rewards.approve',
            'accounting.project-rewards.pay',
            'accounting.salaries.view',
            'accounting.salaries.distribute',
        ]);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /**
     * @param  array<string, mixed>  $settingOverrides
     * @return array{contract: Contract, unit: ContractUnit, reservation: SalesReservation, seller: User}
     */
    protected function scenario(array $settingOverrides = []): array
    {
        $team = Team::factory()->create();
        $contract = Contract::factory()->create();
        $contract->teams()->attach($team->id);
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'price' => 1000000,
        ]);
        $seller = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'salary' => 10000,
            'is_active' => true,
        ]);
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $seller->id,
            'proposed_price' => 1000000,
            'confirmed_at' => now(),
        ]);

        SalesReservationParticipant::query()->create([
            'sales_reservation_id' => $reservation->id,
            'user_id' => $seller->id,
            'did_bring' => true,
            'did_convince' => false,
            'did_close' => false,
            'weight' => 1,
            'notes' => null,
            'created_by' => $seller->id,
        ]);

        ProjectRewardSetting::query()->create(array_merge([
            'contract_id' => $contract->id,
            'calculation_mode' => 'percentage_of_sale',
            'reward_percentage' => 5,
            'source' => 'company',
            'tax_enabled' => true,
            'vat_percentage' => 15,
            'assigned_bring_percentage' => 100,
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
        ], $settingOverrides));

        return compact('contract', 'unit', 'reservation', 'seller');
    }

    public function test_generate_reward_creates_parent_recipients_and_notification_without_commissions(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->scenario();

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-reward")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.base_amount', 50000)
            ->assertJsonPath('data.vat_amount', 7500)
            ->assertJsonPath('data.distribution_pool_amount', 50000)
            ->assertJsonPath('data.recipients.0.amount', 50000);

        $this->assertSame(1, ProjectReward::count());
        $this->assertSame(1, ProjectRewardRecipient::count());
        $this->assertSame(0, Commission::count());
        $this->assertSame(0, CommissionDistribution::count());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $ctx['seller']->id,
            'event_type' => 'reward_generated',
            'status' => 'pending',
        ]);
    }

    public function test_generate_reward_rejects_unresolved_allocations(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->scenario([
            'assigned_bring_percentage' => 0,
            'outside_bring_percentage' => 100,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-reward")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unresolved']);

        $this->assertSame(0, ProjectReward::count());
    }

    public function test_approve_reject_and_pay_status_rules(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->scenario();
        $rewardId = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-reward")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/accounting/project-rewards/{$rewardId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.recipients.0.status', 'approved');

        $this->postJson("/api/accounting/project-rewards/{$rewardId}/reject")
            ->assertStatus(422);

        $this->postJson("/api/accounting/project-rewards/{$rewardId}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.recipients.0.status', 'paid');
    }

    public function test_reject_pending_reward(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->scenario();
        $rewardId = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-reward")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/accounting/project-rewards/{$rewardId}/reject", [
            'reason' => 'wrong allocation',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.recipients.0.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'wrong allocation');
    }

    public function test_salary_response_includes_reward_details_and_salary_distribution_total_rewards(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->scenario();
        $rewardId = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-reward")
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/api/accounting/project-rewards/{$rewardId}/approve")->assertOk();

        $month = now()->month;
        $year = now()->year;

        $this->getJson("/api/accounting/salaries/{$ctx['seller']->id}?month={$month}&year={$year}")
            ->assertOk()
            ->assertJsonPath('data.rewards_total', 50000)
            ->assertJsonPath('data.reward_details.total_rewards', 50000)
            ->assertJsonPath('data.summary.total_rewards', 50000)
            ->assertJsonPath('data.summary.total_amount', 60000);

        $this->postJson("/api/accounting/salaries/{$ctx['seller']->id}/distribute", [
            'month' => $month,
            'year' => $year,
        ])
            ->assertCreated()
            ->assertJsonPath('data.total_rewards', '50000.00')
            ->assertJsonPath('data.total_amount', '60000.00');

        $this->assertDatabaseHas('accounting_salary_distributions', [
            'user_id' => $ctx['seller']->id,
            'total_rewards' => 50000,
        ]);
        $this->assertSame(1, AccountingSalaryDistribution::count());
        $this->assertSame(1, UserNotification::where('event_type', 'reward_generated')->count());
    }
}
