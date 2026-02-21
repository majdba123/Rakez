<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use App\Models\ExpectedBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExpectedSalesRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->assignRole('marketing');
        $this->marketingUser->givePermissionTo('marketing.budgets.manage');
    }

    #[Test]
    public function it_can_create_expected_sales_via_post_route()
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 500000,
        ]);
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/expected-sales', [
                'project_id' => $project->id,
                'direct_communications' => 100,
                'hand_raises' => 50,
                'conversion_rate' => 2.5,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'marketing_project_id', 'expected_bookings_count', 'expected_booking_value']
            ]);

        $this->assertEquals(1875000.0, (float) $response->json('data.expected_booking_value'));

        $this->assertDatabaseHas('expected_bookings', [
            'marketing_project_id' => $project->id,
            'direct_communications' => 100,
            'hand_raises' => 50,
        ]);
    }

    #[Test]
    public function it_can_list_expected_sales_via_get_route()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);
        ExpectedBooking::create([
            'marketing_project_id' => $project->id,
            'direct_communications' => 100,
            'hand_raises' => 50,
            'expected_bookings_count' => 3.75,
            'conversion_rate' => 2.5,
            'expected_booking_value' => 0,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/expected-sales');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'marketing_project_id', 'expected_bookings_count']
                ]
            ]);
    }

    #[Test]
    public function it_can_filter_expected_sales_by_project_id()
    {
        $contract1 = Contract::factory()->create();
        $contract2 = Contract::factory()->create();
        $project1 = MarketingProject::create(['contract_id' => $contract1->id]);
        $project2 = MarketingProject::create(['contract_id' => $contract2->id]);
        
        ExpectedBooking::create([
            'marketing_project_id' => $project1->id,
            'direct_communications' => 100,
            'hand_raises' => 50,
            'expected_bookings_count' => 3.75,
            'conversion_rate' => 2.5,
            'expected_booking_value' => 0,
        ]);
        
        ExpectedBooking::create([
            'marketing_project_id' => $project2->id,
            'direct_communications' => 200,
            'hand_raises' => 100,
            'expected_bookings_count' => 7.5,
            'conversion_rate' => 2.5,
            'expected_booking_value' => 0,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/expected-sales?project_id={$project1->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($project1->id, $data[0]['marketing_project_id']);
    }

    #[Test]
    public function it_requires_project_id_for_post_route()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/expected-sales', [
                'direct_communications' => 100,
                'hand_raises' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    #[Test]
    public function existing_route_with_project_id_still_works()
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 100000,
        ]);
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/expected-sales/{$project->id}", [
                'direct_communications' => 100,
                'hand_raises' => 50,
                'conversion_rate' => 2.5,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function it_updates_expected_booking_value_using_new_expected_count()
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 200000,
        ]);
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/expected-sales', [
                'project_id' => $project->id,
                'direct_communications' => 100,
                'hand_raises' => 0,
                'conversion_rate' => 1,
            ])->assertStatus(201);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/expected-sales', [
                'project_id' => $project->id,
                'direct_communications' => 300,
                'hand_raises' => 200,
                'conversion_rate' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expected_bookings_count', 10);

        $this->assertEquals(2000000.0, (float) $response->json('data.expected_booking_value'));
    }
}
