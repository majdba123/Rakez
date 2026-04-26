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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
