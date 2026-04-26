<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'name' => $this->name,
            'description' => $this->description,
            'team' => $this->whenLoaded('team', function () {
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                    'code' => $this->team->code,
                ];
            }),
            'leader' => $this->when(
                $this->relationLoaded('teamGroupLeader'),
                function () {
                    $l = $this->teamGroupLeader;
                    if (! $l) {
                        return null;
                    }
                    if ($l->relationLoaded('user') && $l->user) {
                        return [
                            'id' => $l->id,
                            'user_id' => $l->user_id,
                            'user' => [
                                'id' => $l->user->id,
                                'name' => $l->user->name,
                                'email' => $l->user->email,
                            ],
                        ];
                    }

                    return [
                        'id' => $l->id,
                        'user_id' => $l->user_id,
                    ];
                }
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
