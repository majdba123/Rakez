<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_developer_list_requires_auth(): void
    {
        $response = $this->getJson('/api/developers');
        $response->assertStatus(401);
    }

    public function test_developer_list_requires_contracts_view_permission(): void
    {
        // User with role that does not have contracts.view (e.g. sales)
        $user = User::factory()->create(['type' => 'sales']);
        $user->assignRole('sales');

        $response = $this->actingAs($user)->getJson('/api/developers');
        $response->assertStatus(403);
    }

    public function test_developer_list_returns_unique_developers_with_projects_units_teams(): void
    {
        $user = User::factory()->create(['type' => 'project_management']);
        $user->assignRole('project_management');
        $user->givePermissionTo('contracts.view');

        Contract::factory()->count(2)->create([
            'user_id' => $user->id,
            'developer_name' => 'Same Developer Co',
            'developer_number' => '+966111111111',
        ]);
        Contract::factory()->count(1)->create([
            'user_id' => $user->id,
            'developer_name' => 'Other Dev',
            'developer_number' => '+966222222222',
        ]);

        $response = $this->actingAs($user)->getJson('/api/developers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'developer_number',
                        'developer_name',
                        'projects_count',
                        'projects' => [
                            '*' => ['id', 'project_name', 'status', 'units_count'],
                        ],
                        'units_count',
                        'teams',
                    ],
                ],
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_developer_list_empty_returns_arabic_message(): void
    {
        $user = User::factory()->create(['type' => 'project_management']);
        $user->assignRole('project_management');
        $user->givePermissionTo('contracts.view');

        $response = $this->actingAs($user)->getJson('/api/developers?search=NonExistentDeveloperName999');
        $response->assertStatus(200)
            ->assertJsonPath('message', 'لا يوجد مطورين مطابقين للبحث')
            ->assertJsonPath('data', []);
    }

    public function test_accounting_user_can_access_developers_list(): void
    {
        $user = User::factory()->create(['type' => 'accounting']);
        $user->assignRole('accounting');
        $user->givePermissionTo('contracts.view_all');

        Contract::factory()->count(1)->create([
            'developer_name' => 'Dev for Accounting',
            'developer_number' => '+966333333333',
        ]);

        $response = $this->actingAs($user)->getJson('/api/developers');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_developer_detail_returns_200_with_same_data_shape_for_known_developer(): void
    {
        $user = User::factory()->create(['type' => 'accounting']);
        $user->assignRole('accounting');
        $user->givePermissionTo('contracts.view_all');

        $developerNumber = 'DEV-002';
        Contract::factory()->count(1)->create([
            'developer_name' => 'Known Developer',
            'developer_number' => $developerNumber,
        ]);

        $response = $this->actingAs($user)->getJson('/api/developers/' . rawurlencode($developerNumber));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.developer_number', $developerNumber)
            ->assertJsonPath('data.developer_name', 'Known Developer')
            ->assertJsonStructure([
                'data' => [
                    'developer_number',
                    'developer_name',
                    'projects_count',
                    'projects',
                    'units_count',
                    'teams',
                ],
            ]);
    }

    public function test_developer_detail_returns_404_for_unknown_developer_number(): void
    {
        $user = User::factory()->create(['type' => 'accounting']);
        $user->assignRole('accounting');
        $user->givePermissionTo('contracts.view_all');

        $response = $this->actingAs($user)->getJson('/api/developers/UNKNOWN-DEV-999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'لم يتم العثور على بيانات المطور. ربما تم فتح الرابط مباشرة');
    }
}
