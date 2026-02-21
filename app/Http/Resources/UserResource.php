<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * This resource is for admin-only views with full employee details.
     * For embedded user data in other resources, use App\Http\Resources\Shared\UserResource
     */
    public function toArray($request): array
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole('admin');

        $cvUrl = $this->cv_path ? url('/api/storage/' . ltrim($this->cv_path, '/')) : null;
        $contractUrl = $this->contract_path ? url('/api/storage/' . ltrim($this->contract_path, '/')) : null;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'is_manager' => $this->is_manager ?? false,
            'team' => $this->team_id,
            'team_name' => $this->team?->name,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        // Only include sensitive fields for admin users
        if ($isAdmin) {
            $data['cv_url'] = $cvUrl;
            $data['contract_url'] = $contractUrl;
            $data['identity_number'] = $this->identity_number;
            $data['birthday'] = $this->birthday?->toDateString();
            $data['date_of_works'] = $this->date_of_works?->toDateString();
            $data['contract_type'] = $this->contract_type;
            $data['iban'] = $this->iban;
            $data['salary'] = $this->salary;
            $data['marital_status'] = $this->marital_status;
        }

        return $data;
    }
}
