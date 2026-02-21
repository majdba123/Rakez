<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AdminNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    // Private channel - only admins can listen
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}

