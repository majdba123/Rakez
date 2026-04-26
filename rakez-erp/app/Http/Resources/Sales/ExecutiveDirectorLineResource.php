<?php

namespace App\Http\Resources\Sales;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutiveDirectorLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $st = $this->status;

        return [
            'id' => $this->id,
            'line_type' => $this->line_type,
            'value' => $this->value !== null ? (float) $this->value : null,
            'status' => $st instanceof BackedEnum ? $st->value : (string) $st,
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'code' => $team->code,
            ])->values()->all(), []),
            'team_ids' => $this->whenLoaded('teams', fn () => $this->teams->pluck('id')->values()->all(), []),
            'team_groups' => $this->whenLoaded('teamGroups', fn () => $this->teamGroups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'description' => $g->description,
                'team_id' => $g->team_id,
            ])->values()->all(), []),
            'team_group_ids' => $this->whenLoaded('teamGroups', fn () => $this->teamGroups->pluck('id')->values()->all(), []),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
