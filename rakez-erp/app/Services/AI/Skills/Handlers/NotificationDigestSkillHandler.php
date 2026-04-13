<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use App\Services\Notification\NotificationAdminService;

class NotificationDigestSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly NotificationAdminService $notificationService,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $perPage = isset($input['per_page']) ? (int) $input['per_page'] : 10;

        $private = $this->notificationService->getPrivateNotificationsForAiSkill($user, $perPage);
        $public = $this->notificationService->getPublicNotificationsForAiSkill($perPage);

        return [
            'status' => 'ok',
            'data' => [
                'private_notifications' => $private,
                'public_notifications' => $public,
            ],
            'sources' => [[
                'type' => 'tool',
                'title' => 'Notifications Digest',
                'ref' => 'notifications:digest',
            ]],
            'confidence' => 'high',
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => '',
            ],
        ];
    }
}
