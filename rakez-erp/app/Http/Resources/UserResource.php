<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{


    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'is_manager' => $this->is_manager ?? false,
            // `team` is now the teams.id
            'team' => $this->team_id,
            'team_name' => $this->team?->name,
            'cv_path' => $this->cv_path,
            'contract_path' => $this->contract_path,
            'identity_number' => $this->identity_number,
            'birthday' => $this->birthday?->toDateString(),
            'date_of_works' => $this->date_of_works?->toDateString(),
            'contract_type' => $this->contract_type,
            'iban' => $this->iban,
            'salary' => $this->salary,
            'marital_status' => $this->marital_status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }





}
