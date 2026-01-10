<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecondPartyDataResource extends JsonResource
{
    /**
     * Transform the resource into an array (second party data details)
     * بيانات الطرف الثاني
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            // URLs - روابط
            'real_estate_papers_url' => $this->real_estate_papers_url,           // رابط اوراق العقار
            'plans_equipment_docs_url' => $this->plans_equipment_docs_url,       // رابط مستندات المخططات والتجهيزات
            'project_logo_url' => $this->project_logo_url,                       // رابط شعار المشروع
            'prices_units_url' => $this->prices_units_url,                       // رابط الاسعار والوحدات
            'marketing_license_url' => $this->marketing_license_url,             // رخصة التسويق
            'advertiser_section_url' => $this->advertiser_section_url,           // قسم معلن
            // Contract Units Summary - ملخص وحدات العقد
            'contract_units_count_csv' => $this->whenLoaded('contractUnits', fn() => $this->contractUnits->count(), 0),
            'total_units_price_csv' => $this->whenLoaded('contractUnits', fn() => (float) $this->contractUnits->sum('price'), 0),
            // Processed By - معالج بواسطة
            'processed_by' => $this->when($this->processedByUser, [
                'id' => $this->processedByUser?->id,
                'name' => $this->processedByUser?->name,
                'type' => $this->processedByUser?->type,
            ]),
            'processed_at' => $this->processed_at?->toIso8601String(),
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

