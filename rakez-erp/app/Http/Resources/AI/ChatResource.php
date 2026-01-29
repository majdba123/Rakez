<?php

namespace App\Http\Resources\AI;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource['message'] ?? null,
            'session_id' => $this->resource['session_id'] ?? null,
            'conversation_id' => $this->resource['conversation_id'] ?? null,
            'suggestions' => $this->resource['suggestions'] ?? [],
            'error_code' => $this->resource['error_code'] ?? null,
            'steps' => $this->resource['steps'] ?? [],
            'links' => [],
            'access_summary' => $this->resource['access_summary'] ?? null,
            'meta' => $this->resource['meta'] ?? [
                'session_id' => $this->resource['session_id'] ?? null,
                'section' => $this->resource['section'] ?? null,
                'tokens' => $this->resource['total_tokens'] ?? null,
                'latency_ms' => $this->resource['latency_ms'] ?? null,
            ],
        ];
    }
}
