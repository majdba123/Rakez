<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReservationParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $team = $this->user->team ?? null;

        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'type' => $this->user->type,
                'team' => $team ? [
                    'id' => $team->id,
                    'name' => $team->name,
                ] : null,
            ],
            'did_bring' => (bool) $this->did_bring,
            'did_convince' => (bool) $this->did_convince,
            'did_close' => (bool) $this->did_close,
            'weight' => (float) $this->weight,
            'notes' => $this->notes,
        ];
    }
}
