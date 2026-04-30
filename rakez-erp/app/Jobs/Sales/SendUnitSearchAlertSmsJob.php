<?php

namespace App\Jobs\Sales;

use App\Models\SalesUnitSearchAlert;
use App\Models\SalesUnitSearchAlertDelivery;
use App\Services\Notifications\TwilioSmsService;
use App\Services\Sales\UnitSearchQueryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendUnitSearchAlertSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $deliveryId,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(TwilioSmsService $smsService, UnitSearchQueryBuilder $unitSearch): void
    {
        $delivery = SalesUnitSearchAlertDelivery::query()
            ->where('delivery_channel', SalesUnitSearchAlertDelivery::CHANNEL_SMS)
            ->find($this->deliveryId);

        if (! $delivery || $delivery->status !== SalesUnitSearchAlertDelivery::STATUS_PENDING) {
            return;
        }

        $alert = SalesUnitSearchAlert::withTrashed()->find($delivery->sales_unit_search_alert_id);
        $unit = $delivery->contractUnit()->with('contract')->first();

        if (! $alert || $alert->trashed()) {
            $this->skip($delivery, null, 'alert_deleted');

            return;
        }

        if ($alert->status === SalesUnitSearchAlert::STATUS_CANCELLED) {
            $this->skip($delivery, $alert, 'alert_cancelled');

            return;
        }

        if ($alert->isExpired()) {
            $this->skip($delivery, $alert, 'alert_expired');

            return;
        }

        if (! $unit || ! $unitSearch->matchesUnit($unit, ['status' => 'available'])) {
            $this->skip($delivery, $alert, 'unit_not_available');

            return;
        }

        $message = $this->messageFor($alert, $unit);

        $skipReason = $this->smsSkipReason($alert, $delivery, $message);
        $alert->forceFill(['last_sms_attempted_at' => now()])->save();

        if ($skipReason !== null) {
            $this->skip($delivery, $alert, $skipReason);

            return;
        }

        try {
            $result = $smsService->send($delivery->client_mobile, $message);

            $delivery->update([
                'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
                'twilio_sid' => $result->sid,
                'skip_reason' => null,
                'error_message' => null,
                'sent_at' => now(),
            ]);

            $alert->update([
                'last_twilio_sid' => $result->sid,
                'last_sms_sent_at' => now(),
                'last_notified_at' => now(),
                'last_sms_error' => null,
                'last_delivery_error' => null,
            ]);
        } catch (Throwable $e) {
            $message = $this->safeError($e);

            $delivery->update([
                'status' => SalesUnitSearchAlertDelivery::STATUS_FAILED,
                'error_message' => $message,
                'skip_reason' => null,
            ]);

            $alert->update([
                'last_sms_error' => $message,
            ]);

            Log::error('Unit search alert SMS delivery failed', [
                'alert_id' => $alert->id,
                'delivery_id' => $delivery->id,
                'contract_unit_id' => $delivery->contract_unit_id,
                'error' => $message,
            ]);
        }
    }

    private function skip(SalesUnitSearchAlertDelivery $delivery, ?SalesUnitSearchAlert $alert, string $reason): void
    {
        $delivery->update([
            'status' => SalesUnitSearchAlertDelivery::STATUS_SKIPPED,
            'skip_reason' => $reason,
            'error_message' => null,
        ]);

        $alert?->update([
            'last_sms_attempted_at' => now(),
            'last_sms_error' => null,
        ]);

        Log::info('Unit search alert SMS delivery skipped', [
            'alert_id' => $alert?->id,
            'delivery_id' => $delivery->id,
            'contract_unit_id' => $delivery->contract_unit_id,
            'reason' => $reason,
        ]);
    }

    private function smsSkipReason(
        SalesUnitSearchAlert $alert,
        SalesUnitSearchAlertDelivery $delivery,
        string $message
    ): ?string {
        if (! config('sales.unit_search_alerts.enabled', true)) {
            return 'alerts_disabled';
        }

        if (! config('sales.unit_search_alerts.sms_enabled', false)) {
            return 'sms_disabled';
        }

        $from = $this->smsFromNumber();

        if (! config('ai_calling.twilio.sid') || ! config('ai_calling.twilio.token') || ! $from) {
            return 'twilio_not_configured';
        }

        if (config('sales.unit_search_alerts.saudi_policy.require_sms_opt_in', true) && ! $alert->client_sms_opt_in) {
            return 'sms_opt_in_missing';
        }

        if (! preg_match('/^\+?[0-9]{8,15}$/', $delivery->client_mobile)) {
            return 'invalid_phone';
        }

        $hasRegisteredSenderId = preg_match('/^[A-Za-z0-9 ]{1,11}$/', $from) === 1;
        $hasValidSenderNumber = preg_match('/^\+?[0-9]{8,15}$/', $from) === 1;

        if (
            config('sales.unit_search_alerts.saudi_policy.require_registered_sender_id', true)
            && ! $hasRegisteredSenderId
            && ! $hasValidSenderNumber
        ) {
            return 'registered_sender_id_required';
        }

        if (config('sales.unit_search_alerts.saudi_policy.block_urls', true) && preg_match('/https?:\/\//i', $message)) {
            return 'sms_body_url_blocked';
        }

        if (
            config('sales.unit_search_alerts.saudi_policy.block_phone_numbers_in_body', true)
            && preg_match('/\+?\d[\d\s\-]{7,}\d/', $message)
        ) {
            return 'sms_body_phone_number_blocked';
        }

        if (config('sales.unit_search_alerts.saudi_policy.sending_window_enabled', false)) {
            $hour = now()->hour;
            $start = (int) config('sales.unit_search_alerts.saudi_policy.sending_window_start_hour', 9);
            $end = (int) config('sales.unit_search_alerts.saudi_policy.sending_window_end_hour', 21);

            if ($hour < $start || $hour >= $end) {
                return 'outside_sms_sending_window';
            }
        }

        $throttleMinutes = (int) config('sales.unit_search_alerts.throttle_minutes_per_alert', 0);
        if ($throttleMinutes > 0) {
            $recentSmsSent = SalesUnitSearchAlertDelivery::query()
                ->where('sales_unit_search_alert_id', $alert->id)
                ->where('id', '!=', $delivery->id)
                ->where('delivery_channel', SalesUnitSearchAlertDelivery::CHANNEL_SMS)
                ->where('status', SalesUnitSearchAlertDelivery::STATUS_SENT)
                ->where('sent_at', '>', now()->subMinutes($throttleMinutes))
                ->exists();

            if ($recentSmsSent) {
                return 'sms_throttled';
            }
        }

        return null;
    }

    private function smsFromNumber(): string
    {
        return (string) (config('sales.unit_search_alerts.from_number') ?: config('ai_calling.twilio.from_number', ''));
    }

    private function messageFor(SalesUnitSearchAlert $alert, $unit): string
    {
        $project = $unit->contract?->project_name ?: 'a project';
        $unitNumber = $unit->unit_number ?: $unit->id;
        $type = $unit->unit_type ?: 'unit';

        return "A matching {$type} is now available in {$project}. Unit {$unitNumber}. Please contact your sales consultant.";
    }

    private function safeError(Throwable $e): string
    {
        return mb_substr($e->getMessage(), 0, 1000);
    }
}
