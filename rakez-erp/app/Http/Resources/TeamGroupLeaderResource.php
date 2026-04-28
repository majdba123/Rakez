<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamGroupLeaderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_group_id' => $this->team_group_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'type' => $this->user->type,
                ];
            }),
            'team_group' => $this->whenLoaded('teamGroup', function () {
                return [
                    'id' => $this->teamGroup->id,
                    'name' => $this->teamGroup->name,
                    'description' => $this->teamGroup->description,
                    'team_id' => $this->teamGroup->team_id,
                    'team' => $this->when(
                        $this->teamGroup->relationLoaded('team') && $this->teamGroup->team,
                        fn () => [
                            'id' => $this->teamGroup->team->id,
                            'name' => $this->teamGroup->team->name,
                            'code' => $this->teamGroup->team->code,
                        ]
                    ),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
