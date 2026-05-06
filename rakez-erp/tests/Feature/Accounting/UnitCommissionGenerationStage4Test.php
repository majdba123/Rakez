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
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class UnitCommissionGenerationStage4Test extends TestCase
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
            'accounting.commissions.approve',
        ]);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createActiveSetting(Contract $contract, array $overrides = []): ProjectCommissionSetting
    {
        $defaults = [
            'project_id' => $contract->id,
            'commission_source' => 'buyer',
            'commission_percentage' => 10,
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
        ];

        return ProjectCommissionSetting::create(array_merge($defaults, $overrides));
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
            'price' => 110000,
        ]);

        $seller = User::factory()->create(['type' => 'sales']);

        $reservation = SalesReservation::factory()->create(array_merge([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $seller->id,
            'proposed_price' => null,
        ], $reservationOverrides));

        SalesReservationParticipant::create([
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

    public function test_migration_and_models_expose_stage4_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('commissions', 'project_commission_setting_id'));
        $this->assertTrue(Schema::hasColumn('commissions', 'calculation_formula_key'));
        $this->assertTrue(Schema::hasColumn('commissions', 'calculated_by_project_setting'));
        $this->assertTrue(Schema::hasColumn('commission_distributions', 'source_scope'));
        $this->assertTrue(Schema::hasColumn('commission_distributions', 'source_type'));

        $commissionFillable = (new Commission)->getFillable();
        $this->assertContains('project_commission_setting_id', $commissionFillable);
        $this->assertContains('calculation_formula_key', $commissionFillable);
        $this->assertContains('calculated_by_project_setting', $commissionFillable);

        $distributionFillable = (new CommissionDistribution)->getFillable();
        $this->assertContains('source_scope', $distributionFillable);
        $this->assertContains('source_type', $distributionFillable);
    }

    public function test_manual_commission_via_accounting_route_still_works_and_is_not_project_flagged(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();

        $resp = $this->postJson("/api/accounting/sold-units/{$ctx['reservation']->id}/commission", [
            'contract_unit_id' => $ctx['reservation']->contract_unit_id,
            'final_selling_price' => 200000,
            'commission_percentage' => 3,
            'commission_source' => 'buyer',
        ]);

        $resp->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('commissions', [
            'sales_reservation_id' => $ctx['reservation']->id,
            'calculated_by_project_setting' => false,
        ]);
    }

    public function test_generate_buyer_commission_row_and_project_fields(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $setting = $this->createActiveSetting($ctx['contract']);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk();

        $data = $resp->json('data.commission');

        $this->assertSame(ProjectCommissionCalculator::BUYER_FORMULA_KEY, $data['calculation_formula_key']);
        $this->assertTrue($data['calculated_by_project_setting']);
        $this->assertEquals($setting->id, $data['project_commission_setting_id']);
        $this->assertEqualsWithDelta(110000.0, (float) $data['final_selling_price'], 0.001);
        $this->assertEqualsWithDelta(11000.0, (float) $data['total_amount'], 0.001);
        $this->assertEqualsWithDelta(11000.0, (float) $data['net_amount'], 0.001);
    }

    public function test_generate_owner_formula_does_not_use_final_price_times_percentage(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $this->createActiveSetting($ctx['contract'], [
            'commission_source' => 'owner',
            'commission_percentage' => 5,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk();

        $total = (float) $resp->json('data.commission.total_amount');
        $buyerWrong = round(110000 * 5 / 100, 2);
        $ownerExpected = round(110000 / 1.05, 2);

        $this->assertEqualsWithDelta($ownerExpected, $total, 0.001);
        $this->assertNotSame($buyerWrong, round($total, 2));
    }

    /**
     * @return list<array{user_id:int,source_scope:string,source_type:string,amount:float}>
     */
    protected function canonicalDistributionTuples(array $distributions): array
    {
        $out = [];
        foreach ($distributions as $row) {
            $out[] = [
                'user_id' => (int) $row['user_id'],
                'source_scope' => (string) $row['source_scope'],
                'source_type' => (string) $row['source_type'],
                'amount' => round((float) $row['amount'], 2),
            ];
        }

        usort($out, function (array $a, array $b) {
            return [$a['user_id'], $a['source_scope'], $a['source_type']]
                <=> [$b['user_id'], $b['source_scope'], $b['source_type']];
        });

        return $out;
    }

    public function test_preview_amounts_match_generated_commission_before_persist_conflict(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        $seller = $ctx['seller'];
        $seller->team_id = $team->id;
        $seller->save();

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update([
            'did_bring' => true,
        ]);

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 100,
        ]);

        $previewJson = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/preview-unit-commission")
            ->assertOk()
            ->json('data');

        $genJson = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk()
            ->json('data');

        $this->assertEquals(
            (float) $previewJson['unit_commission_amount'],
            (float) $genJson['commission']['total_amount'],
        );

        $this->assertSame(
            $this->canonicalDistributionTuples($previewJson['distributions']),
            $this->canonicalDistributionTuples($genJson['distributions']),
        );

        $this->assertGreaterThanOrEqual(1, CommissionDistribution::count());
    }

    public function test_generates_inside_and_outside_team_distributions(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $attached = Team::factory()->create();
        $ctx['contract']->teams()->attach($attached->id);

        /** @var User $seller */
        $seller = $ctx['seller'];
        $seller->team_id = Team::factory()->create()->id;
        $seller->save();

        $inside = User::factory()->create(['team_id' => $attached->id]);
        SalesReservationParticipant::create([
            'sales_reservation_id' => $ctx['reservation']->id,
            'user_id' => $inside->id,
            'did_bring' => true,
            'did_convince' => false,
            'did_close' => false,
            'weight' => 1,
            'notes' => null,
            'created_by' => $seller->id,
        ]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)
            ->update(['did_bring' => true]);

        $this->createActiveSetting($ctx['contract'], [
            'outside_bring_percentage' => 20,
            'assigned_bring_percentage' => 30,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk();

        $rows = collect($resp->json('data.distributions'));

        $this->assertNotNull($rows->firstWhere('source_scope', 'outside_project_team'));
        $this->assertNotNull($rows->firstWhere('source_scope', 'assigned_project_team'));
    }

    public function test_equal_weight_split_three_participants_single_bucket(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->delete();

        $users = [
            User::factory()->create(['team_id' => $team->id]),
            User::factory()->create(['team_id' => $team->id]),
            User::factory()->create(['team_id' => $team->id]),
        ];

        foreach ($users as $u) {
            SalesReservationParticipant::create([
                'sales_reservation_id' => $ctx['reservation']->id,
                'user_id' => $u->id,
                'did_bring' => true,
                'did_convince' => false,
                'did_close' => false,
                'weight' => 1,
                'notes' => null,
                'created_by' => $u->id,
            ]);
        }

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 100,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk();

        $commission = Commission::firstWhere('sales_reservation_id', $ctx['reservation']->id);
        $this->assertNotNull($commission);

        $net = (float) $commission->net_amount;
        $amounts = $commission->distributions()->pluck('amount')->map(fn ($v) => (float) $v)->sort()->values()->all();

        $this->assertCount(3, $amounts);
        $third = round($net / 3, 2);
        $expected = [$third, $third, round($net - 2 * $third, 2)];
        sort($expected);
        $this->assertEquals($expected, $amounts);
    }

    public function test_custom_weight_split_in_bucket(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->delete();

        $uA = User::factory()->create(['team_id' => $team->id]);
        $uB = User::factory()->create(['team_id' => $team->id]);

        foreach ([[$uA, 3], [$uB, 1]] as [$u, $w]) {
            SalesReservationParticipant::create([
                'sales_reservation_id' => $ctx['reservation']->id,
                'user_id' => $u->id,
                'did_bring' => true,
                'did_convince' => false,
                'did_close' => false,
                'weight' => $w,
                'notes' => null,
                'created_by' => $u->id,
            ]);
        }

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 100,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")->assertOk();

        $commission = Commission::firstWhere('sales_reservation_id', $ctx['reservation']->id);
        $net = (float) $commission->net_amount;

        $aAmt = (float) $commission->distributions()->where('user_id', $uA->id)->value('amount');
        $bAmt = (float) $commission->distributions()->where('user_id', $uB->id)->value('amount');

        $this->assertSame(round($net * 3 / 4, 2), $aAmt);
        $this->assertSame(round($net - $aAmt, 2), $bAmt);
    }

    public function test_management_distribution_maps_ceo_legacy_type_and_stores_source_columns(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $ceo = User::factory()->create();

        $this->createActiveSetting($ctx['contract'], [
            'ceo_percentage' => 8,
            'ceo_user_id' => $ceo->id,
            'assigned_bring_percentage' => 0,
        ]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update([
            'did_bring' => false,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk();

        $row = collect($resp->json('data.distributions'))
            ->first(fn ($item) => (int) ($item['user_id'] ?? 0) === $ceo->id);

        $this->assertNotNull($row);
        $this->assertSame('management', $row['source_scope']);
        $this->assertSame('ceo', $row['source_type']);

        $db = CommissionDistribution::where('user_id', $ceo->id)->first();
        $this->assertNotNull($db);
        $this->assertSame('project_manager', $db->type);
        $this->assertSame('management', $db->source_scope);
        $this->assertSame('ceo', $db->source_type);

        $commission = Commission::firstWhere('sales_reservation_id', $ctx['reservation']->id);
        $pct = round(((float) $db->amount / (float) $commission->net_amount) * 100, 2);
        $this->assertSame($pct, round((float) $db->percentage, 2));
    }

    public function test_generation_returns_unresolved_without_creating_matching_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $attached = Team::factory()->create();
        $ctx['contract']->teams()->attach($attached->id);

        $seller = $ctx['seller'];
        $seller->team_id = Team::factory()->create()->id;
        $seller->save();

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update([
            'did_bring' => true,
        ]);

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 20,
            'outside_bring_percentage' => 0,
        ]);

        $resp = $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertOk();

        $unresolved = $resp->json('data.unresolved');
        $this->assertNotEmpty($unresolved);
        $this->assertNotNull(collect($unresolved)->firstWhere('reason', 'no_matching_participants'));

        $commission = Commission::firstWhere('sales_reservation_id', $ctx['reservation']->id);
        $this->assertNotNull($commission);

        $this->assertCount(count($resp->json('data.distributions')), $commission->distributions);

        $assignedBring = $commission->distributions->first(
            fn (CommissionDistribution $d) => $d->source_scope === 'assigned_project_team'
                && $d->source_type === 'bring',
        );
        $this->assertNull($assignedBring);

        foreach ($commission->distributions as $distribution) {
            $this->assertNotNull($distribution->user_id);
        }
    }

    public function test_legacy_manual_commission_blocks_project_generation(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $this->createActiveSetting($ctx['contract']);

        Commission::factory()->create([
            'sales_reservation_id' => $ctx['reservation']->id,
            'contract_unit_id' => $ctx['reservation']->contract_unit_id,
            'calculated_by_project_setting' => false,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commission']);

        $this->assertLessThanOrEqual(1, Commission::where('sales_reservation_id', $ctx['reservation']->id)->count());
    }

    public function test_regeneration_pending_replaces_generated_distributions(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        $seller = $ctx['seller'];
        $seller->team_id = $team->id;
        $seller->save();

        $this->createActiveSetting($ctx['contract'], [
            'assigned_bring_percentage' => 100,
            'outside_bring_percentage' => 0,
        ]);

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update([
            'did_bring' => true,
        ]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")->assertOk();
        $idsFirst = CommissionDistribution::pluck('id')->sort()->values()->all();

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")->assertOk();
        $idsSecond = CommissionDistribution::pluck('id')->sort()->values()->all();

        $this->assertNotEquals($idsFirst, $idsSecond);
    }

    public function test_generation_fails_when_distribution_not_pending(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        $seller = $ctx['seller'];
        $seller->team_id = $team->id;
        $seller->save();

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => true]);

        $this->createActiveSetting($ctx['contract'], ['assigned_bring_percentage' => 100]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")->assertOk();

        $distribution = CommissionDistribution::first();

        $this->postJson("/api/accounting/commissions/{$distribution->commission_id}/distributions/{$distribution->id}/approve")
            ->assertOk();

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertStatus(422);

        // approved row untouched
        $this->assertDatabaseHas('commission_distributions', ['id' => $distribution->id, 'status' => 'approved']);
    }

    public function test_generation_fails_when_commission_not_pending_even_if_distributions_are_pending(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        $seller = $ctx['seller'];
        $seller->team_id = $team->id;
        $seller->save();

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => true]);

        $this->createActiveSetting($ctx['contract'], ['assigned_bring_percentage' => 100]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")->assertOk();

        $commission = Commission::firstWhere('sales_reservation_id', $ctx['reservation']->id);
        $commission->approve();
        $commission->save();

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertStatus(422);

        CommissionDistribution::query()->update(['status' => 'pending']);
        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")
            ->assertStatus(422);
    }

    public function test_total_distribution_amount_does_not_exceed_net(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $ctx = $this->baseReservationScenario();
        $team = Team::factory()->create();
        $ctx['contract']->teams()->attach($team->id);

        $seller = $ctx['seller'];
        $seller->team_id = $team->id;
        $seller->save();

        SalesReservationParticipant::where('sales_reservation_id', $ctx['reservation']->id)->update(['did_bring' => true]);

        $this->createActiveSetting($ctx['contract'], ['assigned_bring_percentage' => 100]);

        $this->postJson("/api/accounting/reservations/{$ctx['reservation']->id}/generate-unit-commission")->assertOk();

        $commission = Commission::firstWhere('sales_reservation_id', $ctx['reservation']->id);
        $sum = round((float) $commission->distributions()->sum('amount'), 2);

        $this->assertLessThanOrEqual(round((float) $commission->net_amount + 0.02, 2), $sum);
    }
}
