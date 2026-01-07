<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int $userId;
    public string $message;

    public function __construct(int $userId, string $message)
    {
        $this->userId = $userId;
        $this->message = $message;
    }

    // Private channel - only specific user can listen
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user-notifications.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}

