<?php

namespace App\Services\AI\SafeWrites\Handlers;

use App\Models\User;
use App\Services\AI\Drafts\AssistantDraftService;
use App\Services\AI\SafeWrites\Contracts\SafeWriteActionHandler;

class DraftBackedSafeWriteActionHandler implements SafeWriteActionHandler
{
    public function __construct(
        private readonly AssistantDraftService $draftService,
    ) {}

    public function propose(User $user, array $action, array $input): array
    {
        $message = (string) ($input['message'] ?? '');

        return [
            'status' => 'proposed',
            'execution_enabled' => false,
            'proposal' => $this->draftService->prepare($user, $message, $action['draft_flow_key']),
        ];
    }

    public function preview(User $user, array $action, array $proposal): array
    {
        return [
            'status' => 'preview_ready',
            'execution_enabled' => false,
            'proposal' => $proposal,
        ];
    }

    public function confirm(User $user, array $action, array $input): array
    {
        return [
            'status' => 'execution_disabled',
            'execution_enabled' => false,
            'message' => 'Direct execution remains disabled. Submit the reviewed payload through the normal application endpoint.',
            'handoff' => $input['proposal']['flow']['handoff'] ?? null,
            'confirmation_boundary' => [
                'confirmed_with_phrase' => $input['confirmation_phrase'] ?? null,
                'assistant_execution_enabled' => false,
                'manual_submit_required' => true,
            ],
        ];
    }

    public function reject(User $user, array $action, array $input): array
    {
        return [
            'status' => 'rejected',
            'execution_enabled' => false,
            'message' => 'Proposal rejected. No write action was executed.',
        ];
    }
}
