<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\SystemPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    private SystemPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SystemPromptBuilder();
    }

    public function test_build_includes_base_instructions(): void
    {
        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringContainsString('Rakez ERP assistant', $prompt);
        $this->assertStringContainsString('help users understand', $prompt);
        $this->assertStringContainsString('Respond in the same language', $prompt);
    }

    public function test_build_includes_behavior_rules(): void
    {
        config([
            'ai_capabilities.behavior_rules' => [
                'Always allowed to explain general workflows',
                'Never show data or steps that imply performing restricted actions',
            ],
        ]);

        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringContainsString('Behavior rules:', $prompt);
        $this->assertStringContainsString('Always allowed to explain general workflows', $prompt);
        $this->assertStringContainsString('Never show data or steps', $prompt);
    }

    public function test_build_includes_section_label(): void
    {
        $user = User::factory()->create();
        $section = ['label' => 'Contracts'];

        $prompt = $this->builder->build($user, [], $section, []);

        $this->assertStringContainsString('Current section: Contracts', $prompt);
    }

    public function test_build_handles_missing_section(): void
    {
        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringContainsString('If the question is unclear, ask which section they are in', $prompt);
    }

    public function test_build_includes_capabilities(): void
    {
        config([
            'ai_capabilities.definitions' => [
                'contracts.view' => 'View contract lists and details.',
                'units.view' => 'View contract units.',
            ],
        ]);

        $user = User::factory()->create();
        $capabilities = ['contracts.view', 'units.view'];

        $prompt = $this->builder->build($user, $capabilities, null, []);

        $this->assertStringContainsString('User capabilities:', $prompt);
        $this->assertStringContainsString('contracts.view: View contract lists and details', $prompt);
        $this->assertStringContainsString('units.view: View contract units', $prompt);
    }

    public function test_build_handles_empty_capabilities(): void
    {
        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringContainsString('User capabilities: none specified', $prompt);
    }

    public function test_build_handles_missing_capability_definitions(): void
    {
        config(['ai_capabilities.definitions' => []]);

        $user = User::factory()->create();
        $capabilities = ['contracts.view', 'unknown.capability'];

        $prompt = $this->builder->build($user, $capabilities, null, []);

        $this->assertStringContainsString('User capabilities:', $prompt);
        $this->assertStringNotContainsString('unknown.capability', $prompt);
    }

    public function test_build_includes_context(): void
    {
        $user = User::factory()->create();
        $context = [
            'user' => ['id' => 1, 'name' => 'Test User'],
            'section' => 'contracts',
        ];

        $prompt = $this->builder->build($user, [], null, $context);

        $this->assertStringContainsString('Context summary (safe, minimal):', $prompt);
        $this->assertStringContainsString('"id":1', $prompt);
        $this->assertStringContainsString('"name":"Test User"', $prompt);
    }

    public function test_build_handles_empty_context(): void
    {
        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringNotContainsString('Context summary', $prompt);
    }

    public function test_build_json_encodes_context(): void
    {
        $user = User::factory()->create();
        $context = ['test' => 'value', 'number' => 123];

        $prompt = $this->builder->build($user, [], null, $context);

        $this->assertStringContainsString('"test":"value"', $prompt);
        $this->assertStringContainsString('"number":123', $prompt);
    }

    public function test_build_handles_missing_behavior_rules(): void
    {
        config(['ai_capabilities.behavior_rules' => null]);

        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringNotContainsString('Behavior rules:', $prompt);
    }

    public function test_build_handles_empty_behavior_rules(): void
    {
        config(['ai_capabilities.behavior_rules' => []]);

        $user = User::factory()->create();
        $prompt = $this->builder->build($user, [], null, []);

        $this->assertStringNotContainsString('Behavior rules:', $prompt);
    }

    public function test_build_handles_section_without_label(): void
    {
        $user = User::factory()->create();
        $section = ['label' => null];

        $prompt = $this->builder->build($user, [], $section, []);

        $this->assertStringContainsString('Current section: Unknown', $prompt);
    }
}
