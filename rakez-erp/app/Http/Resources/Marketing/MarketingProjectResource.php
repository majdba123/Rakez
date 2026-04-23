<?php

namespace App\Http\Resources\Marketing;

use App\Services\Marketing\MarketingProjectMetricsResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingProjectResource extends JsonResource
{
    private MarketingProjectMetricsResolver $metricsResolver;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->metricsResolver = app(MarketingProjectMetricsResolver::class);
    }

    public function toArray(Request $request): array
    {
        $contract = $this->contract;
        $info = $contract->info;

        // Use canonical metrics resolver
        $metrics = $this->metricsResolver->resolveForList($contract);

        return [
            'id' => $this->id,
            'contract_id' => (int) $metrics['contract_id'],
            'project_name' => $metrics['project_name'],
            'developer_name' => $contract->developer_name ?? null,
            'status' => $metrics['status'],
            'team_leader' => $this->teamLeader->name ?? null,
            'units_count' => $metrics['units_count'],
            'avg_unit_price' => (float) $metrics['avg_unit_price'],
            'advertiser_number' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'advertiser_number_value' => $info?->agency_number,
            'advertiser_number_status' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'commission_percent' => (float) $metrics['commission_percent'],
            'total_available_value' => (float) $metrics['total_available_value'],
            'media_links' => $contract->projectMedia
                ->filter(function ($media) {
                    $isSupportedDepartment = in_array($media->department, ['montage', 'photography'], true);
                    $isSupportedType = in_array($media->type, ['image', 'video'], true);

                    return $isSupportedDepartment || $isSupportedType;
                })
                ->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
            'description' => $contract->notes ?? '',
            'created_at' => $contract->created_at,
        ];
    }
}
