<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Shared\UserResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        // Load relationships if not already loaded
        if (!$this->relationLoaded('userOne') || !$this->relationLoaded('userTwo')) {
            $this->load('userOne', 'userTwo');
        }

        $otherUser = $this->resource->getOtherUser($currentUser->id);

        return [
            'id' => $this->id,
            'other_user' => $otherUser ? new UserResource($otherUser) : null,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => $this->resource->getUnreadCount($currentUser->id),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

