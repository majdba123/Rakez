<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Shared\UserResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'sender_id' => $this->sender_id,
            'type' => $this->type ?? 'text',
            'message' => $this->message,
            'voice_url' => $this->when($this->resource->isVoice(), $this->voice_url),
            'voice_duration_seconds' => $this->when($this->resource->isVoice(), $this->voice_duration_seconds),
            'attachment_url' => $this->when($this->resource->hasAttachment(), $this->attachment_url),
            'attachment_original_name' => $this->when(
                $this->resource->hasAttachment(),
                $this->attachment_original_name
            ),
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

