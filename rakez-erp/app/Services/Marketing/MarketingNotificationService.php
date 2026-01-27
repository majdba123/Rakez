<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\AdminNotification;

class MarketingNotificationService
{
    public function notifyImageUpload($contractId, $mediaUrl)
    {
        // Notify marketing team
        $marketingUsers = User::where('type', 'marketing')->get();
        foreach ($marketingUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'content' => "New media uploaded for contract #{$contractId}",
                'type' => 'media_upload',
                'status' => 'pending'
            ]);
        }
    }

    public function notifyNewTask($userId, $taskId)
    {
        UserNotification::create([
            'user_id' => $userId,
            'content' => "You have been assigned a new marketing task #{$taskId}",
            'type' => 'new_task',
            'status' => 'pending'
        ]);
    }

    public function notifyTaskAssignment($userId, $taskId)
    {
        $this->notifyNewTask($userId, $taskId);
    }
}
