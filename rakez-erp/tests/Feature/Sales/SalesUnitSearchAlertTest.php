<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesUnitSearchAlert;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesUnitSearchAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $salesUser;

    private User $otherSalesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        $this->otherSalesUser = User::factory()->create(['type' => 'sales']);
        $this->otherSalesUser->assignRole('sales');
    }

    public function test_sales_user_can_create_list_show_update_and_delete_own_alert(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);

        $create = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/units/search-alerts', [
                'client_name' => 'Client One',
                'client_mobile' => '+966501234567',
                'client_sms_opt_in' => true,
                'project_id' => $contract->id,
                'unit_type' => 'villa',
                'floor' => '2',
                'min_price' => 100000,
                'max_price' => 900000,
                'query_text' => 'A-101',
            ]);

        $create->assertCreated()->assertJsonPath('data.client.sms_opt_in', true);

        $alertId = $create->json('data.id');

        $this->assertDatabaseHas('sales_unit_search_alerts', [
            'id' => $alertId,
            'sales_staff_id' => $this->salesUser->id,
            'project_id' => $contract->id,
            'query_text' => 'A-101',
        ]);

        $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/units/search-alerts')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/units/search-alerts/{$alertId}")
            ->assertOk()
            ->assertJsonPath('data.id', $alertId);

        $this->actingAs($this->salesUser, 'sanctum')
            ->patchJson("/api/sales/units/search-alerts/{$alertId}", [
                'status' => SalesUnitSearchAlert::STATUS_PAUSED,
                'max_price' => 800000,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SalesUnitSearchAlert::STATUS_PAUSED);

        $this->actingAs($this->salesUser, 'sanctum')
            ->deleteJson("/api/sales/units/search-alerts/{$alertId}")
            ->assertOk();

        $this->assertSoftDeleted('sales_unit_search_alerts', ['id' => $alertId]);
        $this->assertSame(SalesUnitSearchAlert::STATUS_CANCELLED, SalesUnitSearchAlert::withTrashed()->find($alertId)->status);
    }

    public function test_alert_with_client_sms_opt_in_stores_opt_in_timestamp_and_locale(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/units/search-alerts', [
                'client_name' => 'SMS Client',
                'client_mobile' => '+966501234567',
                'client_sms_opt_in' => true,
                'client_sms_locale' => 'ar-SA',
                'project_id' => $contract->id,
                'unit_type' => 'villa',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.client.sms_opt_in', true)
            ->assertJsonPath('data.client.sms_locale', 'ar-SA');

        $alert = SalesUnitSearchAlert::findOrFail($response->json('data.id'));
        $this->assertTrue($alert->client_sms_opt_in);
        $this->assertNotNull($alert->client_sms_opted_in_at);
        $this->assertSame('ar-SA', $alert->client_sms_locale);
    }

    public function test_sales_user_cannot_access_another_users_alert(): void
    {
        $alert = SalesUnitSearchAlert::factory()->create([
            'sales_staff_id' => $this->otherSalesUser->id,
        ]);

        $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/units/search-alerts/{$alert->id}")
            ->assertForbidden();

        $this->actingAs($this->salesUser, 'sanctum')
            ->patchJson("/api/sales/units/search-alerts/{$alert->id}", ['status' => 'paused'])
            ->assertForbidden();
    }

    public function test_sales_leader_can_access_team_alerts(): void
    {
        $leader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $leader->assignRole('sales_leader');

        $alert = SalesUnitSearchAlert::factory()->create([
            'sales_staff_id' => $this->salesUser->id,
        ]);

        $this->actingAs($leader, 'sanctum')
            ->getJson("/api/sales/units/search-alerts/{$alert->id}")
            ->assertOk();
    }

    public function test_validation_rejects_invalid_min_max_and_persistence_sort_fields(): void
    {
        $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/units/search-alerts', [
                'client_mobile' => '+966501234567',
                'min_price' => 900000,
                'max_price' => 100000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_price');

        $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/units/search-alerts', [
                'client_mobile' => '+966501234567',
                'sort_by' => 'price',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort_by');
    }

    public function test_project_id_works_for_search_endpoint(): void
    {
        [$contractOne, $unitOne] = $this->createCompletedContractWithUnit('A-101');
        [, $unitTwo] = $this->createCompletedContractWithUnit('B-202');

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/units/search?project_id={$contractOne->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($unitOne->id));
        $this->assertFalse($ids->contains($unitTwo->id));
    }

    private function createCompletedContractWithUnit(string $unitNumber): array
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'unit_number' => $unitNumber,
            'status' => 'available',
            'price' => 500000,
        ]);

        return [$contract, $unit];
    }
}
