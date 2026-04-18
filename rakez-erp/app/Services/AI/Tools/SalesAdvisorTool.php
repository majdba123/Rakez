<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\SalesErpSnapshotService;
use Illuminate\Support\Carbon;

/**
 * Read-only ERP snapshots for sales. No coaching scripts or external benchmarks.
 */
class SalesAdvisorTool implements ToolContract
{
    public const TOPIC_RESERVATION_MOMENTUM = 'reservation_momentum';

    public const TOPIC_PROJECT_INVENTORY_SNAPSHOT = 'project_inventory_snapshot';

    public const TOPIC_PROJECT_PRICING_SNAPSHOT = 'project_pricing_snapshot';

    public const TOPIC_PROJECT_READINESS_FACTS = 'project_readiness_facts';

    public const TOPIC_RESERVATION_ACTIVITY_SUMMARY = 'reservation_activity_summary';

    public function __construct(
        private readonly SalesErpSnapshotService $snapshots,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('use-ai-assistant')) {
            return ToolResponse::denied('use-ai-assistant');
        }

        $topic = $args['topic'] ?? null;
        if (! is_string($topic) || $topic === '') {
            return ToolResponse::invalidArguments('topic is required and must be a non-empty string.');
        }

        return match ($topic) {
            self::TOPIC_RESERVATION_MOMENTUM => $this->reservationMomentum($user, $args),
            self::TOPIC_PROJECT_INVENTORY_SNAPSHOT => $this->projectInventory($user, $args),
            self::TOPIC_PROJECT_PRICING_SNAPSHOT => $this->projectPricing($user, $args),
            self::TOPIC_PROJECT_READINESS_FACTS => $this->projectReadiness($user, $args),
            self::TOPIC_RESERVATION_ACTIVITY_SUMMARY => $this->reservationActivity($user, $args),
            default => ToolResponse::unsupportedOperation(
                'Unsupported topic. Allowed: '.implode(', ', [
                    self::TOPIC_RESERVATION_MOMENTUM,
                    self::TOPIC_PROJECT_INVENTORY_SNAPSHOT,
                    self::TOPIC_PROJECT_PRICING_SNAPSHOT,
                    self::TOPIC_PROJECT_READINESS_FACTS,
                    self::TOPIC_RESERVATION_ACTIVITY_SUMMARY,
                ])
            ),
        };
    }

    private function reservationMomentum(User $user, array $args): array
    {
        if (! $user->can('sales.dashboard.view')) {
            return ToolResponse::denied('sales.dashboard.view');
        }

        try {
            $dateFrom = isset($args['date_from']) ? Carbon::parse($args['date_from']) : null;
            $dateTo = isset($args['date_to']) ? Carbon::parse($args['date_to']) : now();
        } catch (\Exception) {
            return ToolResponse::invalidArguments('date_from or date_to is not a valid date.');
        }
        $contractId = isset($args['contract_id']) ? (int) $args['contract_id'] : null;

        if ($contractId) {
            $contract = Contract::find($contractId);
            if (! $contract) {
                return ToolResponse::invalidArguments("contract_id {$contractId} not found.");
            }
            if (! $this->userCanViewContract($user, $contract)) {
                return ToolResponse::denied('contracts.view_all');
            }
        }

        $payload = $this->snapshots->reservationMomentum($dateFrom, $dateTo, $contractId);

        return ToolResponse::success('tool_sales_advisor', [
            'topic' => self::TOPIC_RESERVATION_MOMENTUM,
            'date_from' => $args['date_from'] ?? null,
            'date_to' => $args['date_to'] ?? null,
            'contract_id' => $contractId,
        ], array_merge($payload, [
            'topic' => self::TOPIC_RESERVATION_MOMENTUM,
            'summary' => 'Reservation counts derived from sales_reservations for the selected window.',
            'warnings' => $payload['total_reservations'] === 0
                ? ['No reservations matched the filters; rates may be null.']
                : [],
        ]), [
            ['type' => 'tool', 'title' => 'Sales ERP: reservation momentum', 'ref' => 'sales:reservation_momentum'],
        ]);
    }

    private function projectInventory(User $user, array $args): array
    {
        if (! $user->can('contracts.view')) {
            return ToolResponse::denied('contracts.view');
        }

        $contractId = isset($args['contract_id']) ? (int) $args['contract_id'] : null;
        if (! $contractId) {
            return ToolResponse::invalidArguments('contract_id is required for this topic.');
        }

        $contract = Contract::find($contractId);
        if (! $contract) {
            return ToolResponse::invalidArguments("contract_id {$contractId} not found.");
        }
        if (! $this->userCanViewContract($user, $contract)) {
            return ToolResponse::denied('contracts.view_all');
        }

        $payload = $this->snapshots->projectInventorySnapshot($contractId);

        $warnings = [];
        if ($payload['units_total'] === 0) {
            $warnings[] = 'No contract_units rows for this project.';
        }

        return ToolResponse::success('tool_sales_advisor', [
            'topic' => self::TOPIC_PROJECT_INVENTORY_SNAPSHOT,
            'contract_id' => $contractId,
        ], array_merge($payload, [
            'topic' => self::TOPIC_PROJECT_INVENTORY_SNAPSHOT,
            'summary' => 'Unit counts grouped by contract_units.status (internal inventory labels).',
            'warnings' => $warnings,
        ]), [
            ['type' => 'record', 'title' => "Inventory: {$contract->project_name}", 'ref' => "project:{$contractId}"],
        ]);
    }

    private function projectPricing(User $user, array $args): array
    {
        if (! $user->can('contracts.view')) {
            return ToolResponse::denied('contracts.view');
        }

        $contractId = isset($args['contract_id']) ? (int) $args['contract_id'] : null;
        if (! $contractId) {
            return ToolResponse::invalidArguments('contract_id is required for this topic.');
        }

        $contract = Contract::find($contractId);
        if (! $contract) {
            return ToolResponse::invalidArguments("contract_id {$contractId} not found.");
        }
        if (! $this->userCanViewContract($user, $contract)) {
            return ToolResponse::denied('contracts.view_all');
        }

        $payload = $this->snapshots->projectPricingSnapshot($contractId);

        return ToolResponse::success('tool_sales_advisor', [
            'topic' => self::TOPIC_PROJECT_PRICING_SNAPSHOT,
            'contract_id' => $contractId,
        ], array_merge($payload, [
            'topic' => self::TOPIC_PROJECT_PRICING_SNAPSHOT,
            'summary' => 'Price statistics from contract_units.price only (listed ERP prices).',
            'warnings' => $payload['priced_units'] === 0
                ? ['No priced units found; min/max/avg are null.']
                : [],
        ]), [
            ['type' => 'record', 'title' => "Pricing: {$contract->project_name}", 'ref' => "project:{$contractId}"],
        ]);
    }

    private function projectReadiness(User $user, array $args): array
    {
        if (! $user->can('contracts.view')) {
            return ToolResponse::denied('contracts.view');
        }

        $contractId = isset($args['contract_id']) ? (int) $args['contract_id'] : null;
        if (! $contractId) {
            return ToolResponse::invalidArguments('contract_id is required for this topic.');
        }

        $contract = Contract::find($contractId);
        if (! $contract) {
            return ToolResponse::invalidArguments("contract_id {$contractId} not found.");
        }
        if (! $this->userCanViewContract($user, $contract)) {
            return ToolResponse::denied('contracts.view_all');
        }

        $payload = $this->snapshots->projectReadinessFacts($contract);

        return ToolResponse::success('tool_sales_advisor', [
            'topic' => self::TOPIC_PROJECT_READINESS_FACTS,
            'contract_id' => $contractId,
        ], array_merge($payload, [
            'topic' => self::TOPIC_PROJECT_READINESS_FACTS,
            'summary' => 'Factual contract flags only; no readiness score.',
            'assumptions' => ['Readiness is not computed; only stored contract fields are returned.'],
        ]), [
            ['type' => 'record', 'title' => "Project facts: {$contract->project_name}", 'ref' => "project:{$contractId}"],
        ]);
    }

    private function reservationActivity(User $user, array $args): array
    {
        if (! $user->can('sales.reservations.view')) {
            return ToolResponse::denied('sales.reservations.view');
        }

        $contractId = isset($args['contract_id']) ? (int) $args['contract_id'] : null;
        $reservationId = isset($args['sales_reservation_id']) ? (int) $args['sales_reservation_id'] : null;

        if ($contractId && $reservationId) {
            return ToolResponse::invalidArguments('Provide only one of contract_id or sales_reservation_id.');
        }

        if (! $contractId && ! $reservationId) {
            return ToolResponse::invalidArguments('contract_id or sales_reservation_id is required.');
        }

        if ($reservationId) {
            $reservation = SalesReservation::with('contract')->find($reservationId);
            if (! $reservation || ! $reservation->contract) {
                return ToolResponse::invalidArguments("sales_reservation_id {$reservationId} not found.");
            }
            if (! $this->userCanViewContract($user, $reservation->contract)) {
                return ToolResponse::denied('contracts.view_all');
            }

            $payload = $this->snapshots->reservationActivityForReservation($reservationId);

            return ToolResponse::success('tool_sales_advisor', [
                'topic' => self::TOPIC_RESERVATION_ACTIVITY_SUMMARY,
                'sales_reservation_id' => $reservationId,
            ], array_merge($payload, [
                'topic' => self::TOPIC_RESERVATION_ACTIVITY_SUMMARY,
                'summary' => 'Counts of sales_reservation_actions rows for this reservation.',
            ]), [
                ['type' => 'record', 'title' => "Reservation actions #{$reservationId}", 'ref' => "sales_reservation:{$reservationId}"],
            ]);
        }

        if (! $user->can('contracts.view')) {
            return ToolResponse::denied('contracts.view');
        }

        $contract = Contract::find($contractId);
        if (! $contract) {
            return ToolResponse::invalidArguments("contract_id {$contractId} not found.");
        }
        if (! $this->userCanViewContract($user, $contract)) {
            return ToolResponse::denied('contracts.view_all');
        }

        $payload = $this->snapshots->reservationActivityForContract($contractId);

        return ToolResponse::success('tool_sales_advisor', [
            'topic' => self::TOPIC_RESERVATION_ACTIVITY_SUMMARY,
            'contract_id' => $contractId,
        ], array_merge($payload, [
            'topic' => self::TOPIC_RESERVATION_ACTIVITY_SUMMARY,
            'summary' => 'Aggregated sales_reservation_actions for reservations under this contract.',
        ]), [
            ['type' => 'record', 'title' => "Contract activity: {$contract->project_name}", 'ref' => "project:{$contractId}"],
        ]);
    }

    private function userCanViewContract(User $user, Contract $contract): bool
    {
        if ($user->can('contracts.view_all')) {
            return true;
        }

        return (int) $contract->user_id === (int) $user->id;
    }
}
