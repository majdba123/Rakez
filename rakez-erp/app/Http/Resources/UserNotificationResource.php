<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'message' => $this->message,
            'status' => $this->status,
            'is_public' => $this->user_id === null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
