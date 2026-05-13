<?php

namespace Tests\Feature\Accounting;

use App\Models\Contract;
use App\Models\ProjectCommissionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class ProjectCommissionSettingStage2Test extends TestCase
{
    use RefreshDatabase;
    use TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoleWithPermissions('accounting', [
            'accounting.sold-units.view',
            'accounting.sold-units.manage',
        ]);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /**
     * Create a Contract that the resolver can always read (buyer, 5%).
     */
    protected function makeBuyerContract(array $overrides = []): Contract
    {
        return Contract::factory()->create(array_merge([
            'commission_from'    => 'المشتري',
            'commission_percent' => 5,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function minimalPayload(?int $projectId = null, array $overrides = []): array
    {
        $pid = $projectId ?? $this->makeBuyerContract()->id;

        return array_merge([
            'project_id'                    => $pid,
            'assigned_bring_percentage'     => 10,
            'assigned_convince_percentage'  => 10,
            'assigned_close_percentage'     => 10,
            'outside_bring_percentage'      => 10,
            'outside_convince_percentage'   => 10,
            'outside_close_percentage'      => 10,
            'ceo_percentage'                => 0,
            'sales_manager_percentage'      => 0,
            'sales_leader_percentage'       => 0,
            'group_leader_percentage'       => 0,
            'external_marketer_percentage'  => 0,
            'is_active'                     => true,
        ], $overrides);
    }

    public function test_accounting_user_can_create_project_commission_setting(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $payload = $this->minimalPayload();

        $response = $this->postJson('/api/accounting/project-commission-settings', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_id', $payload['project_id']);

        $this->assertDatabaseCount('project_commission_settings', 1);
        $this->assertDatabaseHas('project_commission_settings', [
            'project_id'  => $payload['project_id'],
            'is_active'   => true,
            'created_by'  => $this->accountingUser->id,
            // commission_source auto-filled from Contract (buyer)
            'commission_source' => 'buyer',
        ]);
    }

    public function test_contract_without_commission_from_returns_422(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create([
            'commission_from'    => null,
            'commission_percent' => 5,
        ]);

        $payload = $this->minimalPayload($contract->id);

        $this->postJson('/api/accounting/project-commission-settings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commission_source']);
    }

    public function test_contract_without_commission_percent_returns_422(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create([
            'commission_from'    => 'المشتري',
            'commission_percent' => null,
        ]);

        $payload = $this->minimalPayload($contract->id);

        $this->postJson('/api/accounting/project-commission-settings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commission_percentage']);
    }

    public function test_distribution_total_over_100_rejected(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $payload = $this->minimalPayload(null, [
            'assigned_bring_percentage'    => 40,
            'assigned_convince_percentage' => 30,
            'assigned_close_percentage'    => 31,
            'outside_bring_percentage'     => 0,
            'outside_convince_percentage'  => 0,
            'outside_close_percentage'     => 0,
        ]);

        $this->postJson('/api/accounting/project-commission-settings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['distribution_total']);
    }

    public function test_individual_percentage_over_100_rejected(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $payload = $this->minimalPayload(null, [
            'assigned_bring_percentage'    => 101,
            'assigned_convince_percentage' => 0,
            'assigned_close_percentage'    => 0,
            'outside_bring_percentage'     => 0,
            'outside_convince_percentage'  => 0,
            'outside_close_percentage'     => 0,
        ]);

        $this->postJson('/api/accounting/project-commission-settings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_bring_percentage']);
    }

    public function test_management_percentage_requires_user(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $payload = $this->minimalPayload(null, [
            'ceo_percentage' => 5,
            'ceo_user_id'    => null,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
        ]);

        $this->postJson('/api/accounting/project-commission-settings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ceo_user_id']);
    }

    public function test_only_one_active_setting_per_project_on_create(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = $this->makeBuyerContract();

        $this->postJson('/api/accounting/project-commission-settings', $this->minimalPayload($contract->id))->assertCreated();

        $this->postJson('/api/accounting/project-commission-settings', $this->minimalPayload($contract->id))->assertCreated();

        $this->assertSame(1, ProjectCommissionSetting::where('project_id', $contract->id)->where('is_active', true)->count());
        $this->assertSame(2, ProjectCommissionSetting::where('project_id', $contract->id)->count());
    }

    public function test_activate_deactivates_sibling_settings(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = $this->makeBuyerContract();

        $a = ProjectCommissionSetting::create([
            'project_id'                   => $contract->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 3,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => true,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $b = ProjectCommissionSetting::create([
            'project_id'                   => $contract->id,
            'commission_source'            => 'owner',
            'commission_percentage'        => 4,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => false,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $this->postJson("/api/accounting/project-commission-settings/{$b->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($a->fresh()->is_active);
        $this->assertTrue($b->fresh()->is_active);
    }

    public function test_settings_on_different_projects_can_both_be_active(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $c1 = $this->makeBuyerContract();
        $c2 = $this->makeBuyerContract();

        $this->postJson('/api/accounting/project-commission-settings', $this->minimalPayload($c1->id))->assertCreated();

        $this->postJson('/api/accounting/project-commission-settings', $this->minimalPayload($c2->id))->assertCreated();

        $this->assertSame(1, ProjectCommissionSetting::where('project_id', $c1->id)->where('is_active', true)->count());
        $this->assertSame(1, ProjectCommissionSetting::where('project_id', $c2->id)->where('is_active', true)->count());
    }

    public function test_index_can_filter_by_project_id(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $c1 = $this->makeBuyerContract();
        $c2 = $this->makeBuyerContract();

        ProjectCommissionSetting::create([
            'project_id'                   => $c1->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 1,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => true,
            'created_by'                   => $this->accountingUser->id,
        ]);

        ProjectCommissionSetting::create([
            'project_id'                   => $c2->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 2,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => true,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $response = $this->getJson('/api/accounting/project-commission-settings?project_id='.$c1->id);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.project_id', $c1->id);
    }

    public function test_show_returns_expected_shape(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ceo = User::factory()->create();

        // Contract with owner commission terms
        $contract = Contract::factory()->create([
            'commission_from'    => 'المالك',
            'commission_percent' => 2.5,
        ]);

        $row = ProjectCommissionSetting::create([
            'project_id'                   => $contract->id,
            'commission_source'            => 'owner',
            'commission_percentage'        => 2.5,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'ceo_user_id'                  => $ceo->id,
            'ceo_percentage'               => 5,
            'is_active'                    => true,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $this->getJson("/api/accounting/project-commission-settings/{$row->id}")
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'project_id',
                    'project',
                    'commission_source',
                    'commission_percentage',
                    'assigned_bring_percentage',
                    'ceo_user_id',
                    'ceo_percentage',
                    'is_active',
                    'created_by',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.commission_source', 'owner');
    }

    public function test_update_can_change_distribution_percentages(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = $this->makeBuyerContract(['commission_percent' => 9]);

        $row = ProjectCommissionSetting::create([
            'project_id'                   => $contract->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 9,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => false,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $response = $this->putJson("/api/accounting/project-commission-settings/{$row->id}", [
            'assigned_bring_percentage' => 6,
        ]);

        $response->assertOk();

        $this->assertSame(6.0, (float) $response->json('data.assigned_bring_percentage'));
        // commission_percentage is always re-synced from Contract (9%)
        $this->assertSame(9.0, (float) $response->json('data.commission_percentage'));
    }

    public function test_update_setting_is_active_true_deactivates_siblings(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = $this->makeBuyerContract();

        $first = ProjectCommissionSetting::create([
            'project_id'                   => $contract->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 1,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => true,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $second = ProjectCommissionSetting::create([
            'project_id'                   => $contract->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 1,
            'assigned_bring_percentage'    => 5,
            'assigned_convince_percentage' => 5,
            'assigned_close_percentage'    => 5,
            'outside_bring_percentage'     => 5,
            'outside_convince_percentage'  => 5,
            'outside_close_percentage'     => 5,
            'is_active'                    => false,
            'created_by'                   => $this->accountingUser->id,
        ]);

        $this->putJson("/api/accounting/project-commission-settings/{$second->id}", [
            'is_active' => true,
        ])->assertOk();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }
}
