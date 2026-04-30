<?php

namespace Tests\Feature\Sales;

use App\Jobs\Sales\SendUnitSearchAlertSmsJob;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SalesUnitSearchAlert;
use App\Models\SalesUnitSearchAlertDelivery;
use App\Models\SecondPartyData;
use App\Models\User;
use App\Services\Notifications\SmsSendResult;
use App\Services\Notifications\TwilioSmsService;
use App\Services\Sales\SalesUnitSearchAlertMatchingService;
use App\Services\Sales\UnitSearchQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SalesUnitSearchAlertMatchingTest extends TestCase
{
    use RefreshDatabase;

    private User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');
        Config::set('sales.unit_search_alerts.enabled', true);
        Config::set('sales.unit_search_alerts.close_after_first_match', true);
        Config::set('sales.unit_search_alerts.sms_enabled', false);
        Config::set('sales.unit_search_alerts.from_number', null);
        Config::set('sales.unit_search_alerts.saudi_policy.require_registered_sender_id', true);
        Config::set('ai_calling.twilio.from_number', null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_system_notification_delivery_is_sent_before_sms_job_is_dispatched(): void
    {
        Queue::fake([SendUnitSearchAlertSmsJob::class]);

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert(['project_id' => $contract->id, 'unit_type' => 'villa']);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SYSTEM_NOTIFICATION,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_PENDING,
        ]);

        Queue::assertPushed(SendUnitSearchAlertSmsJob::class, 1);
    }

    public function test_system_notification_is_sent_and_sms_skipped_when_sms_disabled(): void
    {
        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldNotReceive('send');
        $this->instance(TwilioSmsService::class, $twilio);

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert(['project_id' => $contract->id]);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->salesUser->id,
            'event_type' => 'unit_search_alert_matched',
        ]);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'contract_unit_id' => $unit->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SYSTEM_NOTIFICATION,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'contract_unit_id' => $unit->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SKIPPED,
            'skip_reason' => 'sms_disabled',
        ]);

        $alert->refresh();
        $this->assertSame(SalesUnitSearchAlert::STATUS_MATCHED, $alert->status);
        $this->assertSame($unit->id, $alert->last_matched_unit_id);
        $this->assertNotNull($alert->last_system_notified_at);
    }

    public function test_system_notification_is_sent_when_twilio_credentials_are_missing(): void
    {
        Config::set('sales.unit_search_alerts.sms_enabled', true);
        Config::set('ai_calling.twilio.sid', null);
        Config::set('ai_calling.twilio.token', null);
        Config::set('ai_calling.twilio.from_number', '+14785550123');

        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldNotReceive('send');
        $this->instance(TwilioSmsService::class, $twilio);

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert([
            'project_id' => $contract->id,
            'unit_type' => 'villa',
            'client_sms_opt_in' => true,
        ]);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SYSTEM_NOTIFICATION,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SKIPPED,
            'skip_reason' => 'twilio_not_configured',
        ]);
    }

    public function test_system_notification_survives_twilio_failure_and_sms_is_failed(): void
    {
        $this->enableSms();

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert([
            'project_id' => $contract->id,
            'unit_type' => 'villa',
            'client_sms_opt_in' => true,
        ]);

        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldReceive('send')->once()->andThrow(new \RuntimeException('Twilio down'));
        $this->instance(TwilioSmsService::class, $twilio);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SYSTEM_NOTIFICATION,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_FAILED,
        ]);

        $this->assertSame(SalesUnitSearchAlert::STATUS_MATCHED, $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->last_sms_error);
        $this->assertNull($alert->fresh()->last_delivery_error);
    }

    public function test_opt_in_false_skips_sms_without_calling_twilio(): void
    {
        $this->enableSms();

        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldNotReceive('send');
        $this->instance(TwilioSmsService::class, $twilio);

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert([
            'project_id' => $contract->id,
            'unit_type' => 'villa',
            'client_sms_opt_in' => false,
        ]);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SKIPPED,
            'skip_reason' => 'sms_opt_in_missing',
        ]);
    }

    public function test_sms_job_can_send_for_matched_alert_with_pending_sms_delivery(): void
    {
        $this->enableSms();

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert([
            'project_id' => $contract->id,
            'unit_type' => 'villa',
            'client_sms_opt_in' => true,
        ]);

        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldReceive('send')->once()->andReturn(new SmsSendResult('SM123'));
        $this->instance(TwilioSmsService::class, $twilio);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertSame(SalesUnitSearchAlert::STATUS_MATCHED, $alert->fresh()->status);
        $this->assertDatabaseHas('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_SENT,
            'twilio_sid' => 'SM123',
        ]);

        $alert->refresh();
        $this->assertNotNull($alert->last_sms_sent_at);
        $this->assertSame('SM123', $alert->last_twilio_sid);
        $this->assertNull($alert->last_sms_error);
    }

    public function test_remarching_same_alert_and_unit_does_not_send_duplicate_sms(): void
    {
        Config::set('sales.unit_search_alerts.close_after_first_match', false);

        $this->enableSms();

        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldReceive('send')->once()->andReturn(new SmsSendResult('SM_ONCE'));
        $this->instance(TwilioSmsService::class, $twilio);

        [$contract, $unit] = $this->createAvailableUnit();
        $this->createAlert([
            'project_id' => $contract->id,
            'unit_type' => 'villa',
            'client_sms_opt_in' => true,
        ]);

        $service = app(SalesUnitSearchAlertMatchingService::class);
        $service->matchUnit($unit);
        $service->matchUnit($unit);

        $this->assertSame(1, SalesUnitSearchAlertDelivery::query()
            ->where('delivery_channel', SalesUnitSearchAlertDelivery::CHANNEL_SMS)
            ->where('status', SalesUnitSearchAlertDelivery::STATUS_SENT)
            ->count());
    }

    public function test_unit_not_available_when_sms_job_runs_marks_delivery_skipped(): void
    {
        Queue::fake([SendUnitSearchAlertSmsJob::class]);
        $this->enableSms();

        $twilio = Mockery::mock(TwilioSmsService::class);
        $twilio->shouldNotReceive('send');
        $this->instance(TwilioSmsService::class, $twilio);

        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert([
            'project_id' => $contract->id,
            'unit_type' => 'villa',
            'client_sms_opt_in' => true,
        ]);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $sms = SalesUnitSearchAlertDelivery::query()
            ->where('sales_unit_search_alert_id', $alert->id)
            ->where('delivery_channel', SalesUnitSearchAlertDelivery::CHANNEL_SMS)
            ->first();

        $this->assertNotNull($sms);
        Queue::assertPushed(SendUnitSearchAlertSmsJob::class, 1);

        $unit->update(['status' => 'reserved']);

        (new SendUnitSearchAlertSmsJob($sms->id))->handle(
            app(TwilioSmsService::class),
            app(UnitSearchQueryBuilder::class)
        );

        $sms->refresh();
        $this->assertSame(SalesUnitSearchAlertDelivery::STATUS_SKIPPED, $sms->status);
        $this->assertSame('unit_not_available', $sms->skip_reason);
    }

    public function test_available_unit_with_active_reservation_does_not_match(): void
    {
        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert(['project_id' => $contract->id]);

        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $matched = app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertSame(0, $matched);
        $this->assertDatabaseMissing('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
        ]);
    }

    public function test_available_unit_on_non_completed_contract_does_not_match(): void
    {
        $contract = Contract::factory()->create(['status' => 'draft']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
        ]);
        $alert = $this->createAlert(['project_id' => $contract->id]);

        $matched = app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertSame(0, $matched);
        $this->assertDatabaseMissing('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
        ]);
    }

    public function test_duplicate_prevention_is_independent_per_channel(): void
    {
        [$contract, $unit] = $this->createAvailableUnit();
        $alert = $this->createAlert(['project_id' => $contract->id]);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);
        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($unit);

        $this->assertSame(1, SalesUnitSearchAlertDelivery::query()
            ->where('sales_unit_search_alert_id', $alert->id)
            ->where('contract_unit_id', $unit->id)
            ->where('delivery_channel', SalesUnitSearchAlertDelivery::CHANNEL_SYSTEM_NOTIFICATION)
            ->count());

        $this->assertSame(1, SalesUnitSearchAlertDelivery::query()
            ->where('sales_unit_search_alert_id', $alert->id)
            ->where('contract_unit_id', $unit->id)
            ->where('delivery_channel', SalesUnitSearchAlertDelivery::CHANNEL_SMS)
            ->count());
    }

    public function test_close_after_first_match_blocks_second_unit_delivery(): void
    {
        $alert = $this->createAlert(['unit_type' => 'villa']);
        [, $firstUnit] = $this->createAvailableUnit(['unit_type' => 'villa']);
        [, $secondUnit] = $this->createAvailableUnit(['unit_type' => 'villa']);

        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($firstUnit);
        app(SalesUnitSearchAlertMatchingService::class)->matchUnit($secondUnit);

        $this->assertSame(SalesUnitSearchAlert::STATUS_MATCHED, $alert->fresh()->status);
        $this->assertSame(2, SalesUnitSearchAlertDelivery::where('sales_unit_search_alert_id', $alert->id)->count());
        $this->assertDatabaseMissing('sales_unit_search_alert_deliveries', [
            'sales_unit_search_alert_id' => $alert->id,
            'contract_unit_id' => $secondUnit->id,
        ]);
    }

    private function enableSms(): void
    {
        Config::set('sales.unit_search_alerts.sms_enabled', true);
        Config::set('ai_calling.twilio.sid', 'AC_TEST');
        Config::set('ai_calling.twilio.token', 'secret');
        Config::set('ai_calling.twilio.from_number', '+14785550123');
    }

    private function createAlert(array $attributes = []): SalesUnitSearchAlert
    {
        return SalesUnitSearchAlert::factory()->create(array_merge([
            'sales_staff_id' => $this->salesUser->id,
            'client_mobile' => '+966501234567',
            'status' => SalesUnitSearchAlert::STATUS_ACTIVE,
        ], $attributes));
    }

    private function createAvailableUnit(array $unitAttributes = []): array
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(array_merge([
            'contract_id' => $contract->id,
            'unit_type' => 'villa',
            'unit_number' => 'A-101',
            'status' => 'available',
            'price' => 500000,
            'area' => '120',
            'private_area_m2' => 10,
            'bedrooms' => 3,
            'floor' => '2',
        ], $unitAttributes));

        return [$contract, $unit];
    }
}
