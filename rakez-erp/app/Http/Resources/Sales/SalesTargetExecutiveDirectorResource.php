<?php

namespace App\Http\Resources\Sales;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTargetExecutiveDirectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $st = $this->status;

        return [
            'id' => $this->id,
            'sales_target_id' => $this->sales_target_id,
            'type' => $this->line_type,
            'value' => $this->value !== null ? (float) $this->value : null,
            'status' => $st instanceof BackedEnum ? $st->value : (string) $st,
        ];
    }
}
