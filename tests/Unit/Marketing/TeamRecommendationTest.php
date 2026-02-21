<?php

namespace Tests\Unit\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\MarketingTask;
use App\Models\SalesReservation;
use App\Services\Marketing\TeamManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeamRecommendationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_recommends_employee_using_tasks_and_booking_ratio()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $userOne = User::factory()->create(['type' => 'marketing']);
        $userTwo = User::factory()->create(['type' => 'marketing']);

        MarketingTask::create([
            'contract_id' => $contract->id,
            'task_name' => 'Task 1',
            'marketer_id' => $userOne->id,
            'status' => 'completed',
            'created_by' => $userOne->id,
            'due_date' => now()->toDateString()
        ]);
        MarketingTask::create([
            'contract_id' => $contract->id,
            'task_name' => 'Task 2',
            'marketer_id' => $userOne->id,
            'status' => 'new',
            'created_by' => $userOne->id,
            'due_date' => now()->toDateString()
        ]);

        SalesReservation::factory()->count(4)->create([
            'contract_id' => $contract->id,
            'marketing_employee_id' => $userTwo->id,
            'status' => 'confirmed',
        ]);

        $service = app(TeamManagementService::class);
        $result = $service->recommendEmployeeForClient($project->id);

        $this->assertEquals($userTwo->id, $result['user']->id);
        $this->assertArrayHasKey('recommendation_reason', $result);
        $this->assertNotEmpty($result['recommendation_reason']);
    }
}
