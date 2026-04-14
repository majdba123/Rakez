<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Broadcast on private channel for the conversation.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing(['sender' => static fn ($q) => $q->select('id', 'name')]);

        $payload = [
            'id' => $this->message->id,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ],
            'type' => $this->message->type ?? 'text',
            'message' => $this->message->message,
            'is_read' => $this->message->is_read,
            'created_at' => $this->message->created_at->toISOString(),
        ];

        if ($this->message->read_at !== null) {
            $payload['read_at'] = $this->message->read_at->toISOString();
        }

        if ($this->message->isVoice()) {
            $payload['voice_url'] = $this->message->voice_url;
            if ($this->message->voice_duration_seconds !== null) {
                $payload['voice_duration_seconds'] = $this->message->voice_duration_seconds;
            }
        }

        if ($this->message->hasAttachment()) {
            $payload['attachment_url'] = $this->message->attachment_url;
            $payload['attachment_original_name'] = $this->message->attachment_original_name;
        }

        return $payload;
    }
}

