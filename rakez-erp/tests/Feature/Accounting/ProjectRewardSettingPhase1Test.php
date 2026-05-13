<?php

namespace Tests\Feature\Accounting;

use App\Models\Contract;
use App\Models\ProjectRewardSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class ProjectRewardSettingPhase1Test extends TestCase
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
    protected function minimalPayload(?int $contractId = null, array $overrides = []): array
    {
        return array_merge([
            'contract_id' => $contractId ?? Contract::factory()->create()->id,
            'calculation_mode' => 'percentage_of_sale',
            'reward_percentage' => 5,
            'source' => 'company',
            'tax_enabled' => false,
            'vat_percentage' => 15,
            'assigned_bring_percentage' => 10,
            'assigned_convince_percentage' => 10,
            'assigned_close_percentage' => 10,
            'outside_bring_percentage' => 10,
            'outside_convince_percentage' => 10,
            'outside_close_percentage' => 10,
            'ceo_percentage' => 0,
            'sales_manager_percentage' => 0,
            'sales_leader_percentage' => 0,
            'group_leader_percentage' => 0,
            'external_marketer_percentage' => 0,
            'is_active' => true,
        ], $overrides);
    }

    public function test_create_setting(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $payload = $this->minimalPayload();

        $this->postJson('/api/accounting/project-reward-settings', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contract_id', $payload['contract_id'])
            ->assertJsonPath('data.calculation_mode', 'percentage_of_sale')
            ->assertJsonPath('data.source', 'company');

        $this->assertDatabaseHas('project_reward_settings', [
            'contract_id' => $payload['contract_id'],
            'is_active' => true,
            'created_by' => $this->accountingUser->id,
        ]);
    }

    public function test_create_active_setting_deactivates_sibling(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();

        $this->postJson('/api/accounting/project-reward-settings', $this->minimalPayload($contract->id))
            ->assertCreated();
        $this->postJson('/api/accounting/project-reward-settings', $this->minimalPayload($contract->id, [
            'reward_percentage' => 3,
        ]))->assertCreated();

        $this->assertSame(1, ProjectRewardSetting::where('contract_id', $contract->id)->where('is_active', true)->count());
        $this->assertSame(2, ProjectRewardSetting::where('contract_id', $contract->id)->count());
    }

    public function test_update_setting(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $setting = ProjectRewardSetting::query()->create($this->minimalPayload());

        $this->putJson("/api/accounting/project-reward-settings/{$setting->id}", [
            'source' => 'developer',
            'tax_enabled' => true,
            'assigned_bring_percentage' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('data.source', 'developer')
            ->assertJsonPath('data.tax_enabled', true)
            ->assertJsonPath('data.assigned_bring_percentage', 15);
    }

    public function test_activate_setting(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $first = ProjectRewardSetting::query()->create($this->minimalPayload($contract->id, [
            'is_active' => true,
            'created_by' => $this->accountingUser->id,
        ]));
        $second = ProjectRewardSetting::query()->create($this->minimalPayload($contract->id, [
            'is_active' => false,
            'created_by' => $this->accountingUser->id,
        ]));

        $this->postJson("/api/accounting/project-reward-settings/{$second->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_total_percentages_over_100_rejected(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $this->postJson('/api/accounting/project-reward-settings', $this->minimalPayload(null, [
            'assigned_bring_percentage' => 60,
            'assigned_convince_percentage' => 30,
            'assigned_close_percentage' => 11,
            'outside_bring_percentage' => 0,
            'outside_convince_percentage' => 0,
            'outside_close_percentage' => 0,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['distribution_total']);
    }

    public function test_management_percentage_requires_user(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $this->postJson('/api/accounting/project-reward-settings', $this->minimalPayload(null, [
            'ceo_percentage' => 5,
            'ceo_user_id' => null,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ceo_user_id']);
    }

    public function test_reward_percentage_required_for_percentage_of_sale(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $this->postJson('/api/accounting/project-reward-settings', $this->minimalPayload(null, [
            'calculation_mode' => 'percentage_of_sale',
            'reward_percentage' => null,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reward_percentage']);
    }
}
