<?php

namespace Database\Seeders;

use App\Models\DailyDeposit;
use App\Models\DailyMarketingSpend;
use App\Models\DeveloperMarketingPlan;
use App\Models\EmployeeMarketingPlan;
use App\Models\ExpectedBooking;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\MarketingProject;
use App\Models\MarketingProjectTeam;
use App\Models\MarketingSetting;
use App\Models\MarketingTask;
use App\Models\User;
use App\Models\Contract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();

        $readyContracts = Contract::where('status', 'ready')->pluck('id')->all();
        if (count($readyContracts) > $counts['marketing_projects']) {
            $readyContracts = array_slice($readyContracts, 0, $counts['marketing_projects']);
        }

        $marketingUsers = User::where('type', 'marketing')->pluck('id')->all();
        if (!$marketingUsers) {
            $marketingUsers = User::where('type', 'sales')->pluck('id')->all();
        }

        $salesLeaders = User::where('type', 'sales')->where('is_manager', true)->pluck('id')->all();
        $leaderPool = $salesLeaders ?: $marketingUsers;

        foreach ($readyContracts as $contractId) {
            $project = MarketingProject::create([
                'contract_id' => $contractId,
                'status' => 'active',
                'assigned_team_leader' => Arr::random($leaderPool),
            ]);

            DeveloperMarketingPlan::factory()->create([
                'contract_id' => $contractId,
            ]);

            $teamMembers = Arr::random($marketingUsers, fake()->numberBetween(3, 4));
            foreach ($teamMembers as $userId) {
                MarketingProjectTeam::create([
                    'marketing_project_id' => $project->id,
                    'user_id' => $userId,
                    'role' => 'marketer',
                ]);
            }

            for ($i = 0; $i < $counts['employee_marketing_plans_per_project']; $i++) {
                $plan = EmployeeMarketingPlan::factory()->create([
                    'marketing_project_id' => $project->id,
                    'user_id' => Arr::random($marketingUsers),
                ]);

                for ($c = 0; $c < $counts['campaigns_per_plan']; $c++) {
                    MarketingCampaign::create([
                        'employee_marketing_plan_id' => $plan->id,
                        'platform' => $c % 2 === 0 ? 'snapchat' : 'instagram',
                        'campaign_type' => $c % 2 === 0 ? 'conversion' : 'awareness',
                        'budget' => fake()->randomFloat(2, 2000, 15000),
                        'status' => 'active',
                    ]);
                }
            }

            for ($t = 0; $t < $counts['marketing_tasks_per_contract']; $t++) {
                MarketingTask::factory()->create([
                    'contract_id' => $contractId,
                    'marketing_project_id' => $project->id,
                    'marketer_id' => Arr::random($marketingUsers),
                    'created_by' => Arr::random($leaderPool),
                    'status' => $t % 2 === 0 ? 'new' : 'in_progress',
                    'due_date' => now()->addDays(fake()->numberBetween(1, 14))->format('Y-m-d'),
                ]);
            }

            for ($l = 0; $l < $counts['leads_per_contract']; $l++) {
                Lead::factory()->create([
                    'project_id' => $contractId,
                    'assigned_to' => Arr::random($marketingUsers),
                    'status' => $l % 2 === 0 ? 'new' : 'contacted',
                ]);
            }

            ExpectedBooking::create([
                'marketing_project_id' => $project->id,
                'direct_communications' => fake()->numberBetween(50, 200),
                'hand_raises' => fake()->numberBetween(10, 80),
                'expected_bookings_count' => fake()->numberBetween(5, 30),
                'expected_booking_value' => fake()->randomFloat(2, 200000, 1000000),
                'conversion_rate' => fake()->randomFloat(2, 0.5, 10),
            ]);

            for ($d = 0; $d < $counts['daily_deposits_per_contract']; $d++) {
                DailyDeposit::create([
                    'date' => now()->subDays(fake()->numberBetween(0, 45))->format('Y-m-d'),
                    'amount' => fake()->randomFloat(2, 500, 20000),
                    'booking_id' => 'BK-' . Str::upper(Str::random(8)),
                    'project_id' => $contractId,
                ]);
            }
        }

        for ($i = 0; $i < $counts['daily_marketing_spends']; $i++) {
            DailyMarketingSpend::updateOrCreate(
                ['date' => now()->subDays($i)->format('Y-m-d')],
                ['amount' => fake()->randomFloat(2, 500, 30000)]
            );
        }

        $settings = [
            'default_cpm' => '25.00',
            'default_cpc' => '2.50',
            'target_conversion_rate' => '3.0',
            'default_campaign_budget' => '15000',
            'default_platform_split' => json_encode(['snapchat' => 50, 'instagram' => 50]),
        ];

        foreach ($settings as $key => $value) {
            MarketingSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'description' => 'Seeded marketing setting']
            );
        }
    }
}
