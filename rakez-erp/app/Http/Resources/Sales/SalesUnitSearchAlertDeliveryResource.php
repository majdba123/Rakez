<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesUnitSearchAlertDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_unit_id' => $this->contract_unit_id,
            'user_notification_id' => $this->user_notification_id,
            'client_mobile' => $this->client_mobile,
            'delivery_channel' => $this->delivery_channel,
            'status' => $this->status,
            'twilio_sid' => $this->twilio_sid,
            'skip_reason' => $this->skip_reason,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
