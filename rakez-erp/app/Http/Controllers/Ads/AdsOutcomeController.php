<?php

namespace App\Http\Controllers\Ads;

use App\Application\Ads\ComputeCustomerOutcome;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdsOutcomeController
{
    public function __construct(
        private readonly ComputeCustomerOutcome $computeOutcome,
    ) {}

    /**
     * POST /api/ads/outcomes
     * Accept a CRM/Order/Retention signal and enqueue it for all platforms.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'outcome_type' => ['required', 'string', Rule::enum(OutcomeType::class)],
            'occurred_at' => 'required|date',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'value' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'crm_stage' => 'nullable|string',
            'score' => 'nullable|integer|min:0|max:100',
            'lead_id' => 'nullable|string',
            'order_id' => 'nullable|string',
            'meta_fbc' => 'nullable|string',
            'meta_fbp' => 'nullable|string',
            'snap_click_id' => 'nullable|string',
            'snap_cookie1' => 'nullable|string',
            'tiktok_ttclid' => 'nullable|string',
            'tiktok_ttp' => 'nullable|string',
            'client_ip' => 'nullable|ip',
            'client_user_agent' => 'nullable|string',
            'event_source_url' => 'nullable|url',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string|in:meta,snap,tiktok',
        ]);

        $event = $this->computeOutcome->execute($validated);

        return response()->json([
            'event_id' => $event->eventId,
            'outcome_type' => $event->outcomeType->value,
            'platforms' => array_map(fn ($p) => $p->value, $event->targetPlatforms),
            'status' => 'queued',
        ], 201);
    }

    /**
     * GET /api/ads/outcomes/status
     * Show recent outcome events status.
     */
    public function status(Request $request): JsonResponse
    {
        $query = AdsOutcomeEvent::query()
            ->orderByDesc('created_at')
            ->limit($request->input('limit', 50));

        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $events = $query->get();

        $summary = AdsOutcomeEvent::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'summary' => $summary,
            'events' => $events,
        ]);
    }
}
