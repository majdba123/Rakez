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
                'message' => "New media uploaded for contract #{$contractId}",
                'status' => 'pending'
            ]);
        }
    }

    public function notifyNewTask($userId, $taskId)
    {
        UserNotification::create([
            'user_id' => $userId,
            'message' => "You have been assigned a new marketing task #{$taskId}",
            'status' => 'pending'
        ]);
    }

    public function notifyTaskAssignment($userId, $taskId)
    {
        $this->notifyNewTask($userId, $taskId);
    }
}
