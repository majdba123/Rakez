<?php

namespace App\Http\Resources\AI;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->session_id,
            'section' => $this->section,
            'last_message' => $this->message,
            'last_message_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
