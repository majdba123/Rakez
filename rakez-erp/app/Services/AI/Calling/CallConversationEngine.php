<?php

namespace App\Services\AI\Calling;

use App\Models\AiCall;
use App\Models\AiCallMessage;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class CallConversationEngine
{
    /**
     * Process client speech and return the AI's next response.
     *
     * @return array{ai_response: string, question_key: ?string, is_complete: bool}
     */
    public function processClientResponse(AiCall $call, string $clientSpeech, ?string $currentQuestionKey = null): array
    {
        $this->saveMessage($call, 'client', $clientSpeech, $currentQuestionKey);

        $call->recordAnswer();

        $nextQuestion = $this->getNextQuestion($call);

        if ($nextQuestion === null || $this->isCallComplete($call)) {
            return [
                'ai_response' => $this->getClosingText($call),
                'question_key' => null,
                'is_complete' => true,
            ];
        }

        $aiResponse = $this->generateAiTransition($call, $clientSpeech, $nextQuestion);

        $this->saveMessage($call, 'ai', $aiResponse, $nextQuestion['key'] ?? null);

        $call->advanceQuestion();

        return [
            'ai_response' => $aiResponse,
            'question_key' => $nextQuestion['key'] ?? null,
            'is_complete' => false,
        ];
    }

    /**
     * Handle the case where the client didn't speak (silence/timeout).
     *
     * @return array{ai_response: string, question_key: ?string, is_complete: bool}
     */
    public function handleNoResponse(AiCall $call, ?string $currentQuestionKey = null): array
    {
        $maxRetries = $call->script?->max_retries_per_question ?? config('ai_calling.call.max_retries_per_question', 2);
        $retryCount = $this->getRetryCountForQuestion($call, $currentQuestionKey);

        if ($retryCount >= $maxRetries) {
            $this->saveMessage($call, 'client', '[رفض الإجابة / صمت]', $currentQuestionKey);

            $nextQuestion = $this->getNextQuestion($call);
            if ($nextQuestion === null) {
                return [
                    'ai_response' => $this->getClosingText($call),
                    'question_key' => null,
                    'is_complete' => true,
                ];
            }

            $questionText = $this->getQuestionText($nextQuestion);
            $response = "طيب. السؤال التالي: {$questionText}";
            $this->saveMessage($call, 'ai', $response, $nextQuestion['key'] ?? null);

            $call->advanceQuestion();

            return [
                'ai_response' => $response,
                'question_key' => $nextQuestion['key'] ?? null,
                'is_complete' => false,
            ];
        }

        $currentQuestion = $this->getCurrentQuestion($call);
        $questionText = $currentQuestion ? $this->getQuestionText($currentQuestion) : '';
        $response = "ما سمعت ردك. مرة ثانية: {$questionText}";

        $this->saveMessage($call, 'ai', $response, $currentQuestionKey);

        return [
            'ai_response' => $response,
            'question_key' => $currentQuestionKey,
            'is_complete' => false,
        ];
    }

    /**
     * Build the greeting and first question for when the call is answered.
     *
     * @return array{ai_response: string, question_key: ?string}
     */
    public function buildGreeting(AiCall $call): array
    {
        $script = $call->script;
        if (! $script) {
            return [
                'ai_response' => 'السلام عليكم. معاك من شركة راكز العقارية. عندي كم سؤال بسيط.',
                'question_key' => null,
            ];
        }

        $greeting = $script->greeting_text;

        if ($call->customer_name) {
            $greeting = str_replace('{customer_name}', $call->customer_name, $greeting);
        }

        $firstQuestion = $this->getNextQuestion($call);
        if ($firstQuestion) {
            $questionText = $this->getQuestionText($firstQuestion);
            $greeting .= " {$questionText}";
            $call->advanceQuestion();

            $this->saveMessage($call, 'ai', $greeting, $firstQuestion['key'] ?? null);

            return [
                'ai_response' => $greeting,
                'question_key' => $firstQuestion['key'] ?? null,
            ];
        }

        $this->saveMessage($call, 'ai', $greeting, null);

        return [
            'ai_response' => $greeting,
            'question_key' => null,
        ];
    }

    /**
     * Get the next question from the script based on current index.
     */
    public function getNextQuestion(AiCall $call): ?array
    {
        $script = $call->script;
        if (! $script) {
            return null;
        }

        return $script->getQuestionAt($call->current_question_index);
    }

    /**
     * Get the current question (at the current index without advancing).
     */
    public function getCurrentQuestion(AiCall $call): ?array
    {
        $script = $call->script;
        if (! $script) {
            return null;
        }

        $index = max(0, $call->current_question_index - 1);

        return $script->getQuestionAt($index);
    }

    /**
     * Check if all questions in the script have been asked.
     */
    public function isCallComplete(AiCall $call): bool
    {
        $script = $call->script;
        if (! $script) {
            return true;
        }

        return $call->current_question_index >= $script->getQuestionCount();
    }

    /**
     * Generate a post-call summary using OpenAI.
     */
    public function generateCallSummary(AiCall $call): string
    {
        $messages = $call->messages()->orderBy('created_at')->get();
        if ($messages->isEmpty()) {
            return 'لا توجد محادثة مسجلة.';
        }

        $transcript = $messages->map(function (AiCallMessage $msg) {
            $role = $msg->role === 'ai' ? 'الموظف' : 'العميل';
            return "{$role}: {$msg->content}";
        })->implode("\n");

        try {
            $response = OpenAI::chat()->create([
                'model' => config('ai_calling.openai.model', 'gpt-4.1-mini'),
                'temperature' => 0.2,
                'max_tokens' => 500,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->buildSummaryPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => "نص المكالمة:\n{$transcript}",
                    ],
                ],
            ]);

            return $response->choices[0]->message->content ?? 'فشل في إنشاء الملخص.';
        } catch (Throwable $e) {
            Log::error('Failed to generate call summary', [
                'ai_call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            return 'فشل في إنشاء الملخص: ' . $e->getMessage();
        }
    }

    /**
     * Score the lead based on call responses.
     *
     * @return array{score: float, qualification: string, notes: string}
     */
    public function qualifyLead(AiCall $call): array
    {
        $messages = $call->messages()->orderBy('created_at')->get();
        if ($messages->isEmpty()) {
            return ['score' => 0, 'qualification' => 'unqualified', 'notes' => 'لا توجد بيانات'];
        }

        $transcript = $messages->map(function (AiCallMessage $msg) {
            $role = $msg->role === 'ai' ? 'الموظف' : 'العميل';
            return "{$role}: {$msg->content}";
        })->implode("\n");

        try {
            $response = OpenAI::chat()->create([
                'model' => config('ai_calling.openai.model', 'gpt-4.1-mini'),
                'temperature' => 0.1,
                'max_tokens' => 300,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->buildQualificationPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => "نص المكالمة:\n{$transcript}",
                    ],
                ],
            ]);

            $content = $response->choices[0]->message->content ?? '{}';
            $data = json_decode($content, true) ?? [];

            return [
                'score' => (float) ($data['score'] ?? 0),
                'qualification' => $data['qualification'] ?? 'unqualified',
                'notes' => $data['notes'] ?? '',
            ];
        } catch (Throwable $e) {
            Log::error('Failed to qualify lead from call', [
                'ai_call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            return ['score' => 0, 'qualification' => 'unknown', 'notes' => 'فشل التقييم'];
        }
    }

    /**
     * Use OpenAI to generate a contextual transition between the client's
     * answer and the next question, keeping the strict personality.
     */
    private function generateAiTransition(AiCall $call, string $clientAnswer, array $nextQuestion): string
    {
        $questionText = $this->getQuestionText($nextQuestion);

        try {
            $response = OpenAI::chat()->create([
                'model' => config('ai_calling.openai.model', 'gpt-4.1-mini'),
                'temperature' => config('ai_calling.openai.temperature', 0.3),
                'max_tokens' => config('ai_calling.openai.max_tokens', 300),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->buildStrictSystemPrompt($call),
                    ],
                    [
                        'role' => 'user',
                        'content' => "العميل قال: \"{$clientAnswer}\"\n\nالسؤال التالي اللي لازم تسأله: \"{$questionText}\"\n\nرد بجملة قصيرة تنتقل فيها للسؤال التالي بدون تعاطف. الرد لازم ينتهي بالسؤال التالي.",
                    ],
                ],
            ]);

            return $response->choices[0]->message->content ?? $questionText;
        } catch (Throwable $e) {
            Log::warning('AI transition generation failed, using raw question', [
                'ai_call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            return "تمام. {$questionText}";
        }
    }

    /**
     * Build the strict, no-empathy system prompt.
     */
    private function buildStrictSystemPrompt(AiCall $call): string
    {
        $customerName = $call->customer_name ?? 'العميل';

        return <<<PROMPT
أنت موظف تحصيل معلومات صارم في شركة راكز العقارية. اسم العميل: {$customerName}.

قواعد صارمة لازم تتبعها بالضبط:
- لا تتعاطف مع العميل أبداً. لا تقول "أفهمك" أو "مقدر موقفك" أو "أحس فيك" أو أي عبارة تعاطف.
- لا تعتذر. لا تقول "آسف" أو "عذراً" أو "معليش".
- كن مباشر وحازم. اسأل السؤال وانتظر الجواب.
- إذا العميل حاول يغير الموضوع، ارجع للسؤال مباشرة: "ما جاوبت على سؤالي. مرة ثانية:" ثم اعد السؤال.
- إذا العميل رفض يجاوب، قل "طيب" وانتقل للسؤال التالي.
- لا تشرح أسباب الأسئلة. اسأل وبس.
- لا تعطي نصائح أو توصيات. أنت تجمع معلومات فقط.
- ردودك قصيرة ومباشرة. جملة أو جملتين بالكثير.
- إذا العميل سأل "ليش تسأل؟" أو "ليش تبي تعرف؟" قل: "هذي إجراءات الشركة. تكرم جاوب على السؤال."
- إذا العميل زعل أو رفع صوته، لا تتأثر. كمّل بنفس الأسلوب بدون أي رد فعل عاطفي.
- لا تستخدم عبارات مجاملة زيادة. لا تقول "شكراً جزيلاً" أو "ممتنلك". إذا لازم تشكر قل "تمام" بس.
- تكلم بالعربي السعودي (اللهجة السعودية).
- لا تضيف معلومات من عندك. اسأل السؤال بالضبط واستلم الجواب.
PROMPT;
    }

    private function buildSummaryPrompt(): string
    {
        return <<<'PROMPT'
أنت محلل مكالمات. لخص المكالمة التالية بشكل مختصر ومفيد. اذكر:
1. النقاط الرئيسية اللي ذكرها العميل
2. الأسئلة اللي ما جاوب عليها
3. مستوى اهتمام العميل (عالي/متوسط/منخفض)
4. أي معلومات مهمة للمتابعة

الملخص لازم يكون بالعربي ومختصر (5-8 أسطر بالكثير).
PROMPT;
    }

    private function buildQualificationPrompt(): string
    {
        return <<<'PROMPT'
أنت محلل تأهيل عملاء عقارية. حلل المكالمة وارجع JSON بالضبط بهالشكل:
{
    "score": 0-100,
    "qualification": "hot" أو "warm" أو "cold" أو "unqualified",
    "notes": "ملاحظات مختصرة عن العميل"
}

معايير التقييم:
- hot (80-100): عنده ميزانية واضحة، جاهز يشتري خلال 3 شهور، صاحب قرار
- warm (50-79): مهتم بس ما حدد ميزانية أو وقت، يحتاج متابعة
- cold (20-49): مهتم بشكل سطحي، ما عنده خطة واضحة
- unqualified (0-19): مو مهتم، رفض أغلب الأسئلة، ما عنده قدرة شرائية

رد بـ JSON فقط بدون أي نص إضافي.
PROMPT;
    }

    private function saveMessage(AiCall $call, string $role, string $content, ?string $questionKey): void
    {
        $timestampInCall = $call->started_at
            ? max(0, (int) round(now()->floatDiffInSeconds($call->started_at, false)))
            : 0;

        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => $role,
            'content' => $content,
            'question_key' => $questionKey,
            'timestamp_in_call' => $timestampInCall,
            'created_at' => now(),
        ]);
    }

    private function getQuestionText(array $question): string
    {
        $lang = config('ai_calling.call.language', 'ar-SA');

        if (str_starts_with($lang, 'ar')) {
            return $question['text_ar'] ?? $question['text_en'] ?? '';
        }

        return $question['text_en'] ?? $question['text_ar'] ?? '';
    }

    private function getClosingText(AiCall $call): string
    {
        return $call->script?->closing_text ?? 'خلصنا. شكراً على وقتك. مع السلامة.';
    }

    private function getRetryCountForQuestion(AiCall $call, ?string $questionKey): int
    {
        if (! $questionKey) {
            return 0;
        }

        return $call->messages()
            ->where('role', 'ai')
            ->where('question_key', $questionKey)
            ->count() - 1;
    }
}
