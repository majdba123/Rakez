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
                'value_target' => isset($team->pivot?->value_target) ? (float) $team->pivot->value_target : null,
                'team_status' => $team->pivot?->team_status,
                'completed_at' => $team->pivot?->completed_at,
            ])->values()->all(), []),
            'team_ids' => $this->whenLoaded('teams', fn () => $this->teams->pluck('id')->values()->all(), []),
            'team_groups' => $this->whenLoaded('teamGroups', fn () => $this->teamGroups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'description' => $g->description,
                'team_id' => $g->team_id,
                'value_target' => isset($g->pivot?->value_target) ? (float) $g->pivot->value_target : null,
                'group_status' => $g->pivot?->group_status,
                'completed_at' => $g->pivot?->completed_at,
            ])->values()->all(), []),
            'team_group_ids' => $this->whenLoaded('teamGroups', fn () => $this->teamGroups->pluck('id')->values()->all(), []),
            'member_users' => $this->whenLoaded('memberUsers', fn () => $this->memberUsers->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'type' => $u->type,
                'value_target' => isset($u->pivot?->value_target) ? (float) $u->pivot->value_target : null,
                'line_type_flag' => $u->pivot?->line_type_flag,
            ])->values()->all(), []),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
