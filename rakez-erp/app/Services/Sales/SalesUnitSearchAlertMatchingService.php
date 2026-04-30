<?php

namespace App\Services\Sales;

use App\Events\UserNotificationEvent;
use App\Jobs\Sales\MatchUnitSearchAlertsJob;
use App\Jobs\Sales\SendUnitSearchAlertSmsJob;
use App\Models\ContractUnit;
use App\Models\SalesUnitSearchAlert;
use App\Models\SalesUnitSearchAlertDelivery;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesUnitSearchAlertMatchingService
{
    public function __construct(
        private UnitSearchCriteria $criteria,
        private UnitSearchQueryBuilder $unitSearch,
    ) {}

    public function dispatchForUnit(int $contractUnitId): void
    {
        if (! config('sales.unit_search_alerts.enabled', true)) {
            return;
        }

        MatchUnitSearchAlertsJob::dispatch($contractUnitId)
            ->onQueue(config('sales.unit_search_alerts.queue', 'default'))
            ->afterCommit();
    }

    public function matchUnit(ContractUnit|int $unit): int
    {
        if (! config('sales.unit_search_alerts.enabled', true)) {
            return 0;
        }

        $unit = $unit instanceof ContractUnit
            ? $unit->fresh(['contract'])
            : ContractUnit::with('contract')->find($unit);

        if (! $unit || ! $this->unitSearch->matchesUnit($unit, ['status' => 'available'])) {
            return 0;
        }

        $alerts = SalesUnitSearchAlert::active()->get();
        $matchedCount = 0;

        foreach ($alerts as $alert) {
            if (! $this->unitSearch->matchesUnit($unit, $this->criteria->fromAlert($alert))) {
                continue;
            }

            if ($this->recordSystemNotificationAndQueueSms($alert, $unit)) {
                $matchedCount++;
            }
        }

        return $matchedCount;
    }

    private function recordSystemNotificationAndQueueSms(SalesUnitSearchAlert $alert, ContractUnit $unit): bool
    {
        $smsDeliveryId = null;
        $didSendSystemNotification = false;

        DB::transaction(function () use ($alert, $unit, &$smsDeliveryId, &$didSendSystemNotification) {
            $lockedAlert = SalesUnitSearchAlert::query()
                ->whereKey($alert->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedAlert || $lockedAlert->status !== SalesUnitSearchAlert::STATUS_ACTIVE || $lockedAlert->isExpired()) {
                return;
            }

            $systemDelivery = SalesUnitSearchAlertDelivery::firstOrCreate([
                'sales_unit_search_alert_id' => $lockedAlert->id,
                'contract_unit_id' => $unit->id,
                'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SYSTEM_NOTIFICATION,
            ], [
                'client_mobile' => $lockedAlert->client_mobile,
                'status' => SalesUnitSearchAlertDelivery::STATUS_PENDING,
            ]);

            if ($systemDelivery->status !== SalesUnitSearchAlertDelivery::STATUS_SENT) {
                $notification = $this->createSystemNotification($lockedAlert, $unit);

                $systemDelivery->update([
                    'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
                    'user_notification_id' => $notification->id,
                    'error_message' => null,
                    'skip_reason' => null,
                    'sent_at' => now(),
                ]);

                $didSendSystemNotification = true;
            }

            $lockedAlert->update([
                'status' => config('sales.unit_search_alerts.close_after_first_match', true)
                    ? SalesUnitSearchAlert::STATUS_MATCHED
                    : SalesUnitSearchAlert::STATUS_ACTIVE,
                'last_matched_unit_id' => $unit->id,
                'last_system_notified_at' => now(),
                'last_notified_at' => now(),
                'last_delivery_error' => null,
            ]);

            $smsDelivery = SalesUnitSearchAlertDelivery::firstOrCreate([
                'sales_unit_search_alert_id' => $lockedAlert->id,
                'contract_unit_id' => $unit->id,
                'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            ], [
                'client_mobile' => $lockedAlert->client_mobile,
                'status' => SalesUnitSearchAlertDelivery::STATUS_PENDING,
            ]);

            $smsDeliveryId = $smsDelivery->id;
        });

        if ($smsDeliveryId !== null) {
            $sms = SalesUnitSearchAlertDelivery::query()->find($smsDeliveryId);
            if ($sms && $sms->status === SalesUnitSearchAlertDelivery::STATUS_PENDING) {
                SendUnitSearchAlertSmsJob::dispatch($smsDeliveryId)
                    ->onQueue(config('sales.unit_search_alerts.queue', 'default'))
                    ->afterCommit();
            }
        }

        return $didSendSystemNotification;
    }

    private function createSystemNotification(SalesUnitSearchAlert $alert, ContractUnit $unit): UserNotification
    {
        $message = sprintf(
            'Matching unit found for %s: %s / %s',
            $alert->client_name ?: $alert->client_mobile,
            $unit->contract?->project_name ?: 'Project',
            $unit->unit_number ?: $unit->id
        );

        $notification = UserNotification::create([
            'user_id' => $alert->sales_staff_id,
            'message' => $message,
            'event_type' => 'unit_search_alert_matched',
            'context' => [
                'alert_id' => $alert->id,
                'contract_unit_id' => $unit->id,
                'contract_id' => $unit->contract_id,
                'client_name' => $alert->client_name,
                'client_mobile' => $alert->client_mobile,
            ],
            'status' => 'pending',
        ]);

        try {
            event(new UserNotificationEvent($alert->sales_staff_id, $message));
        } catch (\Throwable $e) {
            Log::warning('Unit search alert broadcast notification failed', [
                'alert_id' => $alert->id,
                'contract_unit_id' => $unit->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }
}
