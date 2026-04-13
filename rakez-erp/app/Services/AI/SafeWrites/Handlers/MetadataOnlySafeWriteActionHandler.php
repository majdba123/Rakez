<?php

namespace App\Services\AI\SafeWrites\Handlers;

use App\Models\User;
use App\Services\AI\SafeWrites\Contracts\SafeWriteActionHandler;

class MetadataOnlySafeWriteActionHandler implements SafeWriteActionHandler
{
    public function propose(User $user, array $action, array $input): array
    {
        return [
            'status' => $action['activation_state'] === 'forbidden' ? 'refused' : 'not_activated',
            'execution_enabled' => false,
            'message' => $action['activation_message'],
            'proposal' => [
                'input_received' => [
                    'message' => $input['message'] ?? null,
                    'payload' => $input['payload'] ?? null,
                ],
            ],
        ];
    }

    public function preview(User $user, array $action, array $proposal): array
    {
        return [
            'status' => 'preview_blocked',
            'execution_enabled' => false,
            'message' => $action['activation_message'],
            'proposal' => $proposal,
        ];
    }

    public function confirm(User $user, array $action, array $input): array
    {
        return [
            'status' => $action['activation_state'] === 'forbidden' ? 'refused' : 'execution_disabled',
            'execution_enabled' => false,
            'message' => $action['activation_message'],
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
            'message' => 'Request rejected. No write action was executed.',
        ];
    }
}
