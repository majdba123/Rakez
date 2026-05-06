<?php

namespace Tests\Feature\Accounting;

use App\Models\Commission;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\CommissionDistribution;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class SimpleCommissionMvpStage5Test extends TestCase
{
    use RefreshDatabase;
    use TestsWithPermissions;

    protected User $salesUser;

    protected User $otherSalesUser;

    protected User $accountingUser;

    protected Contract $contract;

    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->createRoleWithPermissions('accounting', [
            'accounting.sold-units.view',
            'accounting.sold-units.manage',
        ]);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        $this->otherSalesUser = User::factory()->create(['type' => 'sales']);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');

        $this->contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);

        $team = Team::factory()->create();
        $this->contract->teams()->attach($team->id);

        foreach ([$this->salesUser, $this->otherSalesUser] as $u) {
            $u->team_id = $team->id;
            $u->save();
        }

        $this->unit = ContractUnit::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'available',
            'price' => 500000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    protected function reservationPayload(array $extras = []): array
    {
        return array_merge([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'contract_date' => '2025-01-25',
            'reservation_type' => 'confirmed_reservation',
            'client_name' => 'Stage Five Client',
            'client_mobile' => '0509998888',
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA0000000000000000000999',
            'payment_method' => 'bank_transfer',
            'down_payment_amount' => 100000,
            'down_payment_status' => 'non_refundable',
            'purchase_mechanism' => 'supported_bank',
            'evacuation_date' => '2026-12-31',
            'receipt_voucher' => UploadedFile::fake()->image('voucher-stage5.jpg'),
        ], $extras);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function minimalSettingPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->contract->id,
            'commission_source' => 'buyer',
            'commission_percentage' => 10,
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
        ], $overrides);
    }

    public function test_end_to_end_happy_path_reservation_participants_setting_preview_generate_and_manual_flow(): void
    {
        $payload = $this->reservationPayload([
            'participants' => [
                [
                    'user_id' => $this->otherSalesUser->id,
                    'did_bring' => true,
                    'did_convince' => false,
                    'did_close' => false,
                    'weight' => 1,
                ],
            ],
        ]);

        $createResp = $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload);

        $createResp->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertNotNull($reservation);

        Sanctum::actingAs($this->accountingUser);

        $this->postJson('/api/accounting/project-commission-settings', $this->minimalSettingPayload())
            ->assertCreated();

        $preview = $this->postJson("/api/accounting/reservations/{$reservation->id}/preview-unit-commission")
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('unit_commission_amount', $preview);
        $this->assertGreaterThan(0, (float) $preview['unit_commission_amount']);
        $this->assertGreaterThanOrEqual(1, count($preview['distributions'] ?? []));

        $this->postJson("/api/accounting/projects/{$this->contract->id}/preview-commission", [
            'base_amount' => 500000,
        ])
            ->assertOk()
            ->assertJsonPath('data.formula_key', 'buyer_unit_price_times_rate');

        $generate = $this->postJson("/api/accounting/reservations/{$reservation->id}/generate-unit-commission")
            ->assertOk()
            ->json('data');

        $commissionId = (int) ($generate['commission']['id'] ?? 0);
        $this->assertGreaterThan(0, $commissionId);
        $this->assertTrue((bool) $generate['commission']['calculated_by_project_setting']);
        $this->assertNotEmpty($generate['distributions']);

        $this->assertDatabaseHas('commissions', [
            'id' => $commissionId,
            'sales_reservation_id' => $reservation->id,
            'calculated_by_project_setting' => true,
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            CommissionDistribution::where('commission_id', $commissionId)->count(),
        );

        foreach (CommissionDistribution::where('commission_id', $commissionId)->get() as $row) {
            $this->assertNotNull($row->source_scope);
            $this->assertNotNull($row->source_type);
        }

        // Legacy manual commission on another reservation must still succeed (unchanged flow).
        $unitB = ContractUnit::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'available',
            'price' => 400000,
        ]);

        $reservationB = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $unitB->id,
            'marketing_employee_id' => $this->salesUser->id,
        ]);

        $this->postJson("/api/accounting/sold-units/{$reservationB->id}/commission", [
            'contract_unit_id' => $unitB->id,
            'final_selling_price' => 350000,
            'commission_percentage' => 4,
            'commission_source' => 'buyer',
        ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('commissions', [
            'sales_reservation_id' => $reservationB->id,
            'calculated_by_project_setting' => 0,
        ]);
    }

    public function test_preview_and_generate_return_422_without_active_project_setting(): void
    {
        $payload = $this->reservationPayload([
            'participants' => [
                ['user_id' => $this->otherSalesUser->id, 'did_bring' => true],
            ],
        ]);

        $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload)
            ->assertStatus(201);

        $reservation = SalesReservation::first();
        Sanctum::actingAs($this->accountingUser);

        $this->postJson("/api/accounting/reservations/{$reservation->id}/preview-unit-commission")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_commission_setting']);

        $gen = $this->postJson("/api/accounting/reservations/{$reservation->id}/generate-unit-commission");
        $gen->assertStatus(422);
        $gen->assertJsonValidationErrors(['project_commission_setting']);
        $errors = $gen->json('errors.project_commission_setting');
        $this->assertNotEmpty($errors);
        $this->assertTrue(is_array($errors));
        $this->assertNotSame('', trim((string) ($errors[0] ?? '')));
    }

    public function test_unresolved_allocations_appear_in_preview_and_generate_when_bucket_has_no_recipient(): void
    {
        $payload = $this->reservationPayload();

        $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload)
            ->assertStatus(201);

        $reservation = SalesReservation::first();

        $detachedTeam = Team::factory()->create();
        $this->salesUser->team_id = $detachedTeam->id;
        $this->salesUser->save();

        $this->actingAs($this->salesUser, 'sanctum')
            ->putJson("/api/sales/reservations/{$reservation->id}/participants", [
                'participants' => [
                    [
                        'user_id' => $this->salesUser->id,
                        'did_bring' => true,
                        'did_convince' => false,
                        'did_close' => false,
                        'weight' => 1,
                    ],
                ],
            ])
            ->assertStatus(200);

        Sanctum::actingAs($this->accountingUser);

        $this->postJson('/api/accounting/project-commission-settings', array_merge($this->minimalSettingPayload(), [
            'assigned_bring_percentage' => 25,
            'outside_bring_percentage' => 0,
        ]))->assertCreated();

        $prev = $this->postJson("/api/accounting/reservations/{$reservation->id}/preview-unit-commission")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty(collect($prev['unresolved'] ?? [])->where('reason', 'no_matching_participants'));

        $gen = $this->postJson("/api/accounting/reservations/{$reservation->id}/generate-unit-commission")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty(collect($gen['unresolved'] ?? [])->where('reason', 'no_matching_participants'));
    }

    public function test_project_generation_blocked_does_not_overwrite_manual_commission_attributes(): void
    {
        $payload = $this->reservationPayload();

        $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload)
            ->assertStatus(201);

        $reservation = SalesReservation::first();

        $manualRow = Commission::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'final_selling_price' => 200000,
            'commission_percentage' => 3,
            'total_amount' => 8888,
            'net_amount' => 8888,
            'vat' => 0,
            'commission_source' => 'buyer',
            'calculated_by_project_setting' => false,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->accountingUser);

        $this->postJson('/api/accounting/project-commission-settings', $this->minimalSettingPayload())->assertCreated();

        $this->postJson("/api/accounting/reservations/{$reservation->id}/generate-unit-commission")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commission']);

        $manualRow->refresh();

        $this->assertSame(8888.0, (float) $manualRow->total_amount);
        $this->assertSame(8888.0, (float) $manualRow->net_amount);
        $this->assertNull($manualRow->project_commission_setting_id);
        $this->assertSame(false, (bool) $manualRow->calculated_by_project_setting);
    }
}
