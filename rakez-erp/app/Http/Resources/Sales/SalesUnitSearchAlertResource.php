<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesUnitSearchAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_staff_id' => $this->sales_staff_id,
            'client' => [
                'name' => $this->client_name,
                'mobile' => $this->client_mobile,
                'email' => $this->client_email,
                'sms_opt_in' => (bool) $this->client_sms_opt_in,
                'sms_opted_in_at' => $this->client_sms_opted_in_at?->toISOString(),
                'sms_locale' => $this->client_sms_locale,
            ],
            'criteria' => [
                'city_id' => $this->city_id,
                'district_id' => $this->district_id,
                'project_id' => $this->project_id,
                'unit_type' => $this->unit_type,
                'floor' => $this->floor,
                'min_price' => $this->min_price !== null ? (float) $this->min_price : null,
                'max_price' => $this->max_price !== null ? (float) $this->max_price : null,
                'min_area' => $this->min_area !== null ? (float) $this->min_area : null,
                'max_area' => $this->max_area !== null ? (float) $this->max_area : null,
                'min_bedrooms' => $this->min_bedrooms,
                'max_bedrooms' => $this->max_bedrooms,
                'query_text' => $this->query_text,
            ],
            'status' => $this->status,
            'last_notification' => [
                'last_notified_at' => $this->last_notified_at?->toISOString(),
                'last_system_notified_at' => $this->last_system_notified_at?->toISOString(),
                'last_sms_attempted_at' => $this->last_sms_attempted_at?->toISOString(),
                'last_sms_sent_at' => $this->last_sms_sent_at?->toISOString(),
                'last_sms_error' => $this->last_sms_error,
                'last_twilio_sid' => $this->last_twilio_sid,
                'last_delivery_error' => $this->last_delivery_error,
            ],
            'last_matched_unit' => $this->whenLoaded('lastMatchedUnit', function () {
                $unit = $this->lastMatchedUnit;

                return [
                    'id' => $unit?->id,
                    'unit_number' => $unit?->unit_number,
                    'unit_type' => $unit?->unit_type,
                    'price' => $unit?->price !== null ? (float) $unit->price : null,
                ];
            }),
            'expires_at' => $this->expires_at?->toISOString(),
            'deliveries' => SalesUnitSearchAlertDeliveryResource::collection($this->whenLoaded('deliveries')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
