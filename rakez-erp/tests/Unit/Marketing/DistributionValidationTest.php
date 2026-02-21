<?php

namespace Tests\Unit\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Requests\Marketing\StoreEmployeePlanRequest;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;

class DistributionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function basePayload(): array
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        return [
            'marketing_project_id' => $project->id,
            'user_id' => $user->id,
            'commission_value' => 20000,
            'marketing_value' => 2000,
        ];
    }

    #[Test]
    public function it_rejects_invalid_platform_distribution_keys()
    {
        $request = new StoreEmployeePlanRequest();
        $payload = array_merge($this->basePayload(), [
            'platform_distribution' => [
                'TikTok' => 20,
                'Meta' => 20,
                'Snap' => 20,
                'YouTube' => 20,
                'LinkedIn' => 10,
                // Missing X
            ],
            'campaign_distribution' => [
                'Direct Communication' => 30,
                'Hand Raise' => 30,
                'Impression' => 20,
                'Sales' => 20,
            ],
        ]);

        $validator = Validator::make($payload, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_distribution', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_valid_distributions()
    {
        $request = new StoreEmployeePlanRequest();
        $payload = array_merge($this->basePayload(), [
            'platform_distribution' => [
                'TikTok' => 20,
                'Meta' => 20,
                'Snap' => 20,
                'YouTube' => 20,
                'LinkedIn' => 10,
                'X' => 10,
            ],
            'campaign_distribution' => [
                'Direct Communication' => 30,
                'Hand Raise' => 30,
                'Impression' => 20,
                'Sales' => 20,
            ],
        ]);

        $validator = Validator::make($payload, $request->rules());

        $this->assertFalse($validator->fails());
    }
}
