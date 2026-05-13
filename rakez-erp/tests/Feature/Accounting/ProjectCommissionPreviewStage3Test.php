<?php

namespace Tests\Feature\Accounting;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ProjectCommissionSetting;
use App\Models\SalesReservation;
use App\Models\SalesReservationParticipant;
use App\Models\Team;
use App\Models\User;
use App\Services\Accounting\ProjectCommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class ProjectCommissionPreviewStage3Test extends TestCase
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
     * Create a ProjectCommissionSetting directly in the DB (bypasses service).
     * commission_source and commission_percentage are stored as given — the preview
     * service will override them from the Contract, so these values only satisfy the
     * NOT NULL constraint.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createActiveSetting(Contract $contract, array $overrides = []): ProjectCommissionSetting
    {
        $defaults = [
            'project_id'                   => $contract->id,
            'commission_source'            => 'buyer',
            'commission_percentage'        => 10,
            'assigned_bring_percentage'    => 0,
            'assigned_convince_percentage' => 0,
            'assigned_close_percentage'    => 0,
            'outside_bring_percentage'     => 0,
            'outside_convince_percentage'  => 0,
            'outside_close_percentage'     => 0,
            'ceo_percentage'               => 0,
            'sales_manager_percentage'     => 0,
            'sales_leader_percentage'      => 0,
            'group_leader_percentage'      => 0,
            'external_marketer_percentage' => 0,
            'is_active'                    => true,
            'created_by'                   => $this->accountingUser->id,
        ];

        return ProjectCommissionSetting::create(array_merge($defaults, $overrides));
    }

    /**
     * Build a contract + unit + reservation + seller scenario.
     *
     * @param  array<string, mixed>  $reservationOverrides
     * @param  array<string, mixed>  $contractOverrides   Commission terms for the contract (passed to factory)
     * @return array{contract: Contract, unit: ContractUnit, reservation: SalesReservation, seller: User}
     */
    protected function baseReservationScenario(array $reservationOverrides = [], array $contractOverrides = []): array
    {
        $contract = Contract::factory()->create($contractOverrides);
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'price'       => 110000,
        ]);

        /** @var User $seller */
        $seller = User::factory()->create(['type' => 'sales']);

        $reservation = SalesReservation::factory()->create(array_merge([
            'contract_id'           => $contract->id,
            'contract_unit_id'      => $unit->id,
            'marketing_employee_id' => $seller->id,
            'proposed_price'        => null,
        ], $reservationOverrides));

        SalesReservationParticipant::create([
            'sales_reservation_id' => $reservation->id,
            'user_id'              => $seller->id,
            'did_bring'            => false,
            'did_convince'         => false,
            'did_close'            => false,
            'weight'               => 1,
            'notes'                => null,
            'created_by'           => $seller->id,
        ]);

        return compact('contract', 'unit', 'reservation', 'seller');
    }

    public function test_unit_preview_errors_without_active_project_setting(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_commission_setting']);
    }

    public function test_unit_preview_buyer_formula_and_buyer_formula_key(): void
    {
        Sanctum::actingAs($this->accountingUser);

        // Contract defines buyer at 10% — preview reads this directly from the Contract
        $ctx = $this->baseReservationScenario(contractOverrides: [
            'commission_from'    => 'المشتري',
            'commission_percent' => 10,
        ]);
        $this->createActiveSetting($ctx['contract']);

        $commissionsBefore = Commission::count();

        $response = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")
            ->assertOk();

        $this->assertSame($commissionsBefore, Commission::count());
        $this->assertSame(0, CommissionDistribution::count());

        $unitCommission = (int) round(110000 * 10 / 100, 2);
        $response->assertJsonPath('data.formula_key', ProjectCommissionCalculator::BUYER_FORMULA_KEY)
            ->assertJsonPath('data.unit_commission_amount', $unitCommission)
            ->assertJsonPath('data.commission_source', 'buyer');
    }

    public function test_unit_preview_owner_formula_key(): void
    {
        Sanctum::actingAs($this->accountingUser);

        // Contract defines owner at 5% — preview reads this directly from the Contract
        $ctx = $this->baseReservationScenario(contractOverrides: [
            'commission_from'    => 'المالك',
            'commission_percent' => 5,
        ]);
        $this->createActiveSetting($ctx['contract']);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")
            ->assertOk();

        $amount = round(110000 / 1.05, 2);

        $resp->assertJsonPath('data.formula_key', ProjectCommissionCalculator::OWNER_FORMULA_KEY)
            ->assertJsonPath('data.unit_commission_amount', $amount);
    }

    public function test_unit_preview_assigns_assigned_team_participant_to_assigned_bucket(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();

        /** @var User $seller */
        $seller = $ctx['seller'];
        $seller->team_id = $team->id;
        $seller->save();

        $ctx['contract']->teams()->attach($team->id);

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 80,
        ]);

        $record = SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)
            ->where('user_id', $seller->id)
            ->first();
        $this->assertNotNull($record);
        $record->did_bring = true;
        $record->save();

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")
            ->assertOk();

        $data = $resp->json('data.distributions');

        $row = collect($data)->first(function ($item) use ($seller) {
            return (int) $item['user_id'] === $seller->id && $item['source_scope'] === 'assigned_project_team';
        });

        $this->assertNotNull($row);
        $this->assertSame('bring', $row['source_type']);
        $this->assertGreaterThan(0, $row['amount']);
    }

    public function test_unit_preview_routes_outside_team_to_outside_bucket(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $attached = Team::factory()->create();

        /** @var User $seller */
        $seller = $ctx['seller'];
        $seller->team_id = Team::factory()->create()->id;
        $seller->save();

        $ctx['contract']->teams()->attach($attached->id);

        $this->createActiveSetting($ctx['contract'], [
            'outside_bring_percentage'  => 70,
            'assigned_bring_percentage' => 0,
        ]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update([
            'did_bring' => true,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")->assertOk();

        $hit = collect($resp->json('data.distributions'))->first(fn ($item) =>
            (int) $item['user_id'] === $seller->id && $item['source_scope'] === 'outside_project_team');

        $this->assertNotNull($hit);
    }

    public function test_unit_preview_splits_bucket_across_three_equal_weights(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        $extra = User::factory()->create(['team_id' => $team->id]);
        SalesReservationParticipant::create([
            'sales_reservation_id' => $ctx['reservation']->id,
            'user_id'              => $extra->id,
            'did_bring'            => true,
            'did_convince'         => false,
            'did_close'            => false,
            'weight'               => 1,
            'notes'                => null,
            'created_by'           => $ctx['seller']->id,
        ]);

        $extraB = User::factory()->create(['team_id' => $team->id]);

        SalesReservationParticipant::create([
            'sales_reservation_id' => $ctx['reservation']->id,
            'user_id'              => $extraB->id,
            'did_bring'            => true,
            'did_convince'         => false,
            'did_close'            => false,
            'weight'               => 1,
            'notes'                => null,
            'created_by'           => $ctx['seller']->id,
        ]);

        /** @var User $sellerB */
        $sellerB = $ctx['seller'];
        $sellerB->team_id = $team->id;
        $sellerB->save();
        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)
            ->where('user_id', $sellerB->id)
            ->update(['did_bring' => true]);

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 100,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")->assertOk();

        $unitCommission = (float) $resp->json('data.unit_commission_amount');

        $amounts = collect($resp->json('data.distributions'))
            ->where('source_scope', 'assigned_project_team')
            ->where('source_type', 'bring')
            ->pluck('amount')
            ->map(fn ($a) => (float) $a)
            ->all();

        $this->assertCount(3, $amounts);
        $this->assertSame(round($unitCommission, 2), round(array_sum($amounts), 2));
    }

    public function test_unit_preview_splits_by_custom_weights(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        /** @var User $uA */
        $uA = User::factory()->create(['team_id' => $team->id]);
        /** @var User $uB */
        $uB = User::factory()->create(['team_id' => $team->id]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->delete();

        foreach ([
            [$uA->id, 3],
            [$uB->id, 1],
        ] as [$uid, $w]) {
            SalesReservationParticipant::create([
                'sales_reservation_id' => $ctx['reservation']->id,
                'user_id'              => $uid,
                'did_bring'            => true,
                'did_convince'         => false,
                'did_close'            => false,
                'weight'               => $w,
                'notes'                => null,
                'created_by'           => $uA->id,
            ]);
        }

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 100,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")->assertOk();
        $unitCommission = (float) $resp->json('data.unit_commission_amount');

        $bringRows = collect($resp->json('data.distributions'))
            ->where('source_scope', 'assigned_project_team')
            ->where('source_type', 'bring');

        $aAmount = (float) $bringRows->firstWhere('user_id', $uA->id)['amount'];
        $bAmount = (float) $bringRows->firstWhere('user_id', $uB->id)['amount'];

        $this->assertSame(round($unitCommission * 3 / 4, 2), $aAmount);
        $this->assertSame(round($unitCommission - $aAmount, 2), $bAmount);
    }

    public function test_unit_preview_management_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $ceo = User::factory()->create();

        $this->createActiveSetting($ctx['contract'], [
            'ceo_percentage'            => 5,
            'ceo_user_id'               => $ceo->id,
            'assigned_bring_percentage' => 0,
        ]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => false]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")->assertOk();

        $mgmt = collect($resp->json('data.distributions'))
            ->first(fn ($item) => $item['source_scope'] === 'management'
                && (int) $item['user_id'] === $ceo->id);

        $this->assertNotNull($mgmt);
        $this->assertGreaterThan(0, $mgmt['amount']);
    }

    public function test_unit_preview_reports_unresolved_assigned_bucket_with_no_matching_participants(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $attached = Team::factory()->create();
        $ctx['contract']->teams()->attach($attached->id);

        /** @var User $seller */
        $seller = $ctx['seller'];
        $seller->team_id = Team::factory()->create()->id;
        $seller->save();

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update([
            'did_bring' => true,
        ]);

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 25,
            'outside_bring_percentage'  => 0,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")->assertOk();

        $hit = collect($resp->json('data.unresolved'))->firstWhere('reason', 'no_matching_participants');

        $this->assertNotNull($hit);
        $this->assertGreaterThan(0, $hit['amount']);
        $this->assertSame('assigned_project_team', $hit['source_scope']);
    }

    public function test_total_distributed_less_or_equal_than_unit_commission(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $this->createActiveSetting($ctx['contract']);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")->assertOk();

        $unit = (float) $resp->json('data.unit_commission_amount');
        $total = (float) $resp->json('data.total_distributed');

        $this->assertLessThanOrEqual($unit + 0.01, $total);
    }

    public function test_override_unit_price_bypasses_resolver(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario([
            'proposed_price' => null,
        ]);
        $ctx['unit']->update(['price' => 0]);

        $this->createActiveSetting($ctx['contract']);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission", [
            'override_unit_price' => 50000,
        ])->assertOk();

        $this->assertSame(50000, (int) $resp->json('data.unit_price'));
    }

    public function test_project_preview_requires_base_amount(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = $this->makeBuyerContract();
        $this->createActiveSetting($contract);

        $this->postJson("/api/accounting/projects/{$contract->id}/preview-commission", [])
            ->assertStatus(422);
    }

    public function test_project_preview_buyer_formula(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = $this->makeBuyerContract(['commission_percent' => 8]);
        $this->createActiveSetting($contract);

        $this->postJson("/api/accounting/projects/{$contract->id}/preview-commission", ['base_amount' => 250000])
            ->assertOk()
            ->assertJsonPath('data.project_commission_amount', (int) round(250000 * 8 / 100, 2))
            ->assertJsonPath('data.formula_key', ProjectCommissionCalculator::BUYER_FORMULA_KEY);
    }

    public function test_project_preview_owner_formula(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create([
            'commission_from'    => 'المالك',
            'commission_percent' => 4,
        ]);
        $this->createActiveSetting($contract);

        $expectedOwner = round(250000 / 1.04, 2);

        $this->postJson("/api/accounting/projects/{$contract->id}/preview-commission", ['base_amount' => 250000])
            ->assertOk()
            ->assertJsonPath('data.project_commission_amount', $expectedOwner)
            ->assertJsonPath('data.formula_key', ProjectCommissionCalculator::OWNER_FORMULA_KEY);
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    protected function makeBuyerContract(array $overrides = []): Contract
    {
        return Contract::factory()->create(array_merge([
            'commission_from'    => 'المشتري',
            'commission_percent' => 10,
        ], $overrides));
    }
}
