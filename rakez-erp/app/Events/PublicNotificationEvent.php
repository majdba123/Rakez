<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PublicNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    // Public channel - everyone can listen (no auth)
    public function broadcastOn(): array
    {
        return [
            new Channel('public-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'public.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}

