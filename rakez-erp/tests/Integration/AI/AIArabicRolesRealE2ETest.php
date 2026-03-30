<?php

namespace Tests\Integration\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesUsersWithBootstrapRole;
use Tests\Traits\ReadsDotEnvForTest;
use Tests\Traits\TestsWithRealOpenAiConnection;

/**
 * Live OpenAI: Arabic prompts for every bootstrap role (same permission sets as production seed).
 *
 * Requires OPENAI_API_KEY + AI_REAL_TESTS=true in .env.
 *
 * Run: php artisan test tests/Integration/AI/AIArabicRolesRealE2ETest.php
 * Or:  php artisan test --group=ai-arabic-roles
 * Or:  composer test:e2e-ai
 *
 * @group ai-e2e-real
 * @group ai-arabic-roles
 */
#[Group('ai-e2e-real')]
#[Group('ai-arabic-roles')]
class AIArabicRolesRealE2ETest extends TestCase
{
    use CreatesUsersWithBootstrapRole;
    use ReadsDotEnvForTest;
    use RefreshDatabase;
    use TestsWithRealOpenAiConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealOpenAiFromDotEnv();
    }

    /**
     * Must stay free of Laravel facades — runs before the application boots.
     *
     * @return array<string, array{0: string}>
     */
    public static function bootstrapRoleNamesProvider(): array
    {
        $names = [
            'admin',
            'project_management',
            'editor',
            'developer',
            'marketing',
            'sales',
            'sales_leader',
            'hr',
            'credit',
            'accounting',
            'inventory',
            'default',
            'accountant',
        ];
        $out = [];
        foreach ($names as $name) {
            $out[$name] = [$name];
        }

        return $out;
    }

    #[DataProvider('bootstrapRoleNamesProvider')]
    public function test_bootstrap_role_can_ask_in_arabic(string $roleName): void
    {
        $user = $this->createUserWithBootstrapRole($roleName);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'اشرح بجملة واحدة بالعربية: كيف يمكنك مساعدتي في نظام إدارة المبيعات والعقارات؟',
            'section' => 'general',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $message = $response->json('data.message');
        $this->assertIsString($message);
        $this->assertGreaterThan(10, strlen($message), "Role {$roleName}: empty or too short assistant message");

        $this->assertArabicOrBilingualContent($message, $roleName, 'ask');
    }

    #[DataProvider('bootstrapRoleNamesProvider')]
    public function test_bootstrap_role_tools_chat_returns_arabic_schema(string $roleName): void
    {
        $user = $this->createUserWithBootstrapRole($roleName);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'لخص في فقرة قصيرة بالعربية دور المساعد الذكي لهذا المستخدم في النظام.',
            'section' => 'general',
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Route /api/ai/tools/chat is not registered');
        }

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('answer_markdown', $data);
        $this->assertNotEmpty($data['answer_markdown'], "Role {$roleName}: empty answer_markdown");

        $this->assertArabicOrBilingualContent($data['answer_markdown'], $roleName, 'tools');

        $this->assertArrayHasKey('confidence', $data);
        $this->assertContains($data['confidence'], ['high', 'medium', 'low']);
    }

    #[DataProvider('bootstrapRoleNamesProvider')]
    public function test_bootstrap_role_chat_non_stream_arabic(string $roleName): void
    {
        $prevOrchestrated = config('ai_assistant.tools.orchestrated_chat');
        try {
            Config::set('ai_assistant.tools.orchestrated_chat', false);

            $user = $this->createUserWithBootstrapRole($roleName);
            Sanctum::actingAs($user);

            $response = $this->postJson('/api/ai/chat', [
                'message' => 'ما هي نصيحة واحدة بالعربية لاستخدام لوحة التحكم بكفاءة؟',
                'section' => 'general',
                'stream' => false,
            ]);

            $response->assertOk();
            $response->assertJson(['success' => true]);

            $message = $response->json('data.message');
            $this->assertIsString($message);
            $this->assertGreaterThan(5, strlen($message));
        } finally {
            Config::set('ai_assistant.tools.orchestrated_chat', $prevOrchestrated);
        }
    }

    private function assertArabicOrBilingualContent(string $text, string $roleName, string $flow): void
    {
        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $text);
        $this->assertTrue(
            $hasArabic,
            "Expected Arabic script in {$flow} response for role {$roleName}. Got: ".mb_substr($text, 0, 200)
        );
    }
}
