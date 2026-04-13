<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AI\AIAssistantService;
use Illuminate\Console\Command;

/**
 * Test the AI assistant with system-relevant questions (Arabic) to verify
 * it understands the ERP and assists users within the product.
 */
class AiTestSystemCommand extends Command
{
    protected $signature = 'ai:test-system
                            {--user= : User ID to run as (default: first admin)}
                            {--section= : Only run questions for this section key}';

    protected $description = 'Test AI assistant with real system questions in Arabic (uses OPENAI_API_KEY from .env)';

    public function handle(AIAssistantService $ai): int
    {
        if (! config('ai_assistant.enabled')) {
            $this->error('AI assistant is disabled. Set AI_ENABLED=true in .env');
            return self::FAILURE;
        }

        $apiKey = config('openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '' || trim($apiKey) === 'test-fake-key-not-used') {
            $this->error('OpenAI provider is not configured. Set a real OPENAI_API_KEY in .env');
            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No user found. Create an admin user or pass --user=<id>');
            return self::FAILURE;
        }

        $this->info('Testing as: ' . $user->name . ' (id=' . $user->id . ', type=' . ($user->type ?? 'n/a') . ')');
        $this->newLine();

        $questions = $this->getSystemQuestions();
        $filterSection = $this->option('section');
        if ($filterSection) {
            $questions = array_filter($questions, fn ($q) => ($q['section'] ?? null) === $filterSection);
            if (empty($questions)) {
                $this->warn('No questions for section: ' . $filterSection);
                return self::SUCCESS;
            }
        }

        $passed = 0;
        $failed = 0;

        foreach ($questions as $i => $item) {
            $num = $i + 1;
            $section = $item['section'] ?? 'general';
            $question = $item['question'];

            $this->line('<fg=cyan>[' . $num . '/' . count($questions) . "] Section: {$section}</>");
            $this->line('  <options=bold>سؤال:</> ' . $question);

            try {
                $result = $ai->ask($question, $user, $section === 'general' ? null : $section, $item['context'] ?? []);
                $answer = $result['message'] ?? '';
                $this->line('  <options=bold>إجابة:</> ' . trim($answer));
                $passed++;
            } catch (\Throwable $e) {
                $this->line('  <fg=red>خطأ:</> ' . $e->getMessage());
                $failed++;
            }

            $this->newLine();
        }

        $this->info("Done. Passed: {$passed}, Failed: {$failed}");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $id = $this->option('user');
        if ($id !== null) {
            return User::query()->find($id);
        }
        return User::query()
            ->where('type', 'admin')
            ->first()
            ?? User::query()->first();
    }

    /**
     * System-relevant questions in Arabic (from ai_sections and real user needs).
     */
    private function getSystemQuestions(): array
    {
        return [
            [
                'section' => null,
                'question' => 'كيف أتنقل في نظام راكز وما الذي يمكنني فعله فيه؟',
            ],
            [
                'section' => 'contracts',
                'question' => 'كيف أنشئ عقداً وما هي حالات العقد في النظام؟',
            ],
            [
                'section' => 'accounting',
                'question' => 'كيف أوزع العمولات على الفريق وكيف أتابع الإيداعات؟',
            ],
            [
                'section' => 'sales',
                'question' => 'كيف أحسن نسبة الإغلاق وما أفضل طريقة لمتابعة العملاء؟',
            ],
            [
                'section' => 'marketing_dashboard',
                'question' => 'ما معنى مؤشرات لوحة التسويق وكيف أفسر نسبة إنجاز المهام اليومية؟',
            ],
            [
                'section' => 'units',
                'question' => 'ما هي حالات الوحدات وكيف أرفع وحدات بـ CSV؟',
            ],
        ];
    }
}
