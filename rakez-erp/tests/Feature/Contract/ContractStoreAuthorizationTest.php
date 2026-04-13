<?php

namespace Tests\Feature\Contract;

use App\Models\City;
use App\Models\Contract;
use App\Models\District;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractStoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('contracts.create', 'web');
    }

    public function test_contract_store_is_forbidden_without_contracts_create_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/contracts/store', $this->validPayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_contract_store_succeeds_with_contracts_create_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/contracts/store', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('contracts', [
            'user_id' => $user->id,
            'project_name' => 'Secure Project',
            'status' => 'pending',
        ]);
    }

    private function validPayload(): array
    {
        $city = City::factory()->create();
        $district = District::factory()->create(['city_id' => $city->id]);

        return [
            'developer_name' => 'Secure Dev',
            'developer_number' => 'DEV-100',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'side' => 'N',
            'contract_type' => 'residential',
            'project_name' => 'Secure Project',
            'developer_requiment' => 'Need compliant contract flow',
            'notes' => 'Hardening regression test',
            'units' => [
                [
                    'type' => 'villa',
                    'count' => 2,
                    'price' => 500000,
                ],
            ],
        ];
    }
}
