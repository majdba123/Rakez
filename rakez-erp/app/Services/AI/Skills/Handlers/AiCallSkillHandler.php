<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Calling\AiCallingService;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AiCallSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly AiCallingService $callingService,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $mode = (string) ($definition['mode'] ?? 'call');

        try {
            $data = match ($mode) {
                'analytics' => $this->callingService->getCallAnalytics($user, $input),
                default => $this->callingService->getCallTranscript((int) ($input['call_id'] ?? 0)),
            };
        } catch (ModelNotFoundException) {
            return [
                'status' => 'not_found',
                'message' => 'The requested AI call data could not be found.',
                'reason' => 'ai_call.not_found',
            ];
        }

        return [
            'status' => 'ok',
            'data' => $data,
            'sources' => $this->buildSources($mode, $input),
            'confidence' => 'high',
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => '',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array{type:string,title:string,ref:string}>
     */
    private function buildSources(string $mode, array $input): array
    {
        if ($mode === 'analytics') {
            return [[
                'type' => 'tool',
                'title' => 'AI Calls Analytics',
                'ref' => 'ai_calls:analytics',
            ]];
        }

        $callId = (int) ($input['call_id'] ?? 0);

        return [[
            'type' => 'record',
            'title' => 'AI Call #'.$callId,
            'ref' => 'ai_call:'.$callId,
        ]];
    }
}
