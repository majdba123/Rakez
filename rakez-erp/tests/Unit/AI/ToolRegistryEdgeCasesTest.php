<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * حالات أدوات: اسم غير معروف، معاملات ناقصة، أخطاء منطق الأداة.
 *
 * @see tests/AI_SCENARIO_MATRIX.md (T-01, T-02)
 */
class ToolRegistryEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_tool_returns_error_shape(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $registry = app(ToolRegistry::class);

        $out = $registry->execute($user, 'tool_does_not_exist', []);

        $this->assertArrayHasKey('result', $out);
        $this->assertArrayHasKey('error', $out['result']);
        $this->assertStringContainsString('Unknown tool', (string) $out['result']['error']);
    }

    public function test_search_records_with_empty_query_returns_tool_error(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $registry = app(ToolRegistry::class);

        $out = $registry->execute($user, 'tool_search_records', [
            'query' => '',
            'modules' => ['leads'],
        ]);

        $this->assertArrayHasKey('result', $out);
        $this->assertStringContainsString('required', strtolower((string) ($out['result']['error'] ?? '')));
    }
}
