<?php

namespace Tests\Feature\Contract;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvertiserExpiryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $pmStaff;
    protected User $salesUser;
    protected User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create(['type' => 'developer']);
        $this->owner->syncRolesFromType();

        $this->pmStaff = User::factory()->create(['type' => 'project_management']);
        $this->pmStaff->syncRolesFromType();

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->syncRolesFromType();

        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->syncRolesFromType();
    }

    public function test_second_party_data_can_store_expiry_date(): void
    {
        $contract = Contract::factory()->create([
            'user_id' => $this->owner->id,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->pmStaff, 'sanctum')
            ->postJson("/api/second-party-data/store/{$contract->id}", [
                'real_estate_papers_url' => 'https://example.com/deed.pdf',
                'plans_equipment_docs_url' => 'https://example.com/plans.pdf',
                'project_logo_url' => 'https://example.com/logo.png',
                'prices_units_url' => 'https://example.com/prices.pdf',
                'marketing_license_url' => 'https://example.com/license.pdf',
                'advertiser_section_url' => '123456789',
                'advertiser_section_expiry_date' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.advertiser_number', '123456789')
            ->assertJsonPath('data.advertiser_number_expiry_status', 'valid');

        $this->assertDatabaseHas('second_party_data', [
            'contract_id' => $contract->id,
            'advertiser_section_url' => '123456789',
            'advertiser_section_expiry_date' => now()->addDays(30)->startOfDay()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_resource_returns_missing_status_when_number_or_expiry_date_is_missing(): void
    {
        $contract = Contract::factory()->create(['user_id' => $this->owner->id]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        SecondPartyData::factory()->create([
            'contract_id' => $contract->id,
            'advertiser_section_url' => null,
            'advertiser_section_expiry_date' => null,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/contracts/show/{$contract->id}");

        $response->assertOk()
            ->assertJsonPath('data.advertiser_number', null)
            ->assertJsonPath('data.advertiser_number_expiry_status', 'missing');
    }

    public function test_resource_returns_expired_status(): void
    {
        $contract = Contract::factory()->create(['user_id' => $this->owner->id]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        SecondPartyData::factory()->create([
            'contract_id' => $contract->id,
            'advertiser_section_url' => '111222333',
            'advertiser_section_expiry_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/contracts/show/{$contract->id}");

        $response->assertOk()
            ->assertJsonPath('data.advertiser_number_expiry_status', 'expired')
            ->assertJsonPath('data.advertiser_number_remaining_days', -1);
    }

    public function test_resource_returns_expiring_soon_status(): void
    {
        $contract = Contract::factory()->create(['user_id' => $this->owner->id]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        SecondPartyData::factory()->create([
            'contract_id' => $contract->id,
            'advertiser_section_url' => '111222333',
            'advertiser_section_expiry_date' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/contracts/show/{$contract->id}");

        $response->assertOk()
            ->assertJsonPath('data.advertiser_number_expiry_status', 'expiring_soon')
            ->assertJsonPath('data.advertiser_number_remaining_days', 7);
    }

    public function test_resource_returns_valid_status(): void
    {
        $contract = Contract::factory()->create(['user_id' => $this->owner->id]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        SecondPartyData::factory()->create([
            'contract_id' => $contract->id,
            'advertiser_section_url' => '111222333',
            'advertiser_section_expiry_date' => now()->addDays(30)->toDateString(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/contracts/show/{$contract->id}");

        $response->assertOk()
            ->assertJsonPath('data.advertiser_number_expiry_status', 'valid')
            ->assertJsonPath('data.advertiser_number_remaining_days', 30);
    }

    public function test_marketing_and_sales_resources_use_second_party_number_with_contract_info_fallback(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'user_id' => $this->owner->id,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agency_number' => 'ADV-FALLBACK',
        ]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        $marketingResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $marketingResponse->assertOk()
            ->assertJsonPath('data.0.advertiser_number_value', 'ADV-FALLBACK')
            ->assertJsonPath('data.0.advertiser_number_expiry_status', 'missing')
            ->assertJsonPath('data.0.advertiser_number_source', 'contract_infos.agency_number');

        $salesResponse = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}");

        $salesResponse->assertOk()
            ->assertJsonPath('data.ad_code', 'ADV-FALLBACK')
            ->assertJsonPath('data.advertiser_number_expiry_status', 'missing')
            ->assertJsonPath('data.advertiser_number_source', 'contract_infos.agency_number');
    }
}
