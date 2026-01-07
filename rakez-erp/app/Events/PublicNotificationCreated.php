<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PublicNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public $notificationId;
    public $title;
    public $message;
    public $data;

    public function __construct(UserNotification $notification)
    {
        $this->notificationId = $notification->id;
        $this->title = $notification->title;
        $this->message = $notification->message;
        $this->data = $notification->data;
    }

    // PUBLIC channel - no authentication required
    public function broadcastOn(): array
    {
        return [
            new Channel('public-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notificationId,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}

