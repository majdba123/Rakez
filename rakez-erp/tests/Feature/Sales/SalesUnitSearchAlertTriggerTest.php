<?php

namespace Tests\Feature\Sales;

use App\Jobs\ProcessContractUnitsCsv;
use App\Jobs\Sales\MatchUnitSearchAlertsJob;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use App\Services\Contract\ContractUnitService;
use App\Services\Sales\DispatchUnitSearchAlertMatching;
use App\Services\Sales\SalesReservationService;
use App\Services\Sales\SalesUnitSearchAlertMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SalesUnitSearchAlertTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_unit_create_and_update_dispatch_matching_job(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        Auth::login($admin);

        $contract = $this->createEditableContract();

        $unit = app(ContractUnitService::class)->addUnit($contract->id, [
            'unit_type' => 'villa',
            'unit_number' => 'A-101',
            'status' => 'available',
            'price' => 500000,
        ]);

        Queue::assertPushed(MatchUnitSearchAlertsJob::class);

        app(ContractUnitService::class)->updateUnit($unit->id, [
            'price' => 550000,
            'status' => 'available',
        ]);

        Queue::assertPushed(MatchUnitSearchAlertsJob::class, 2);
    }

    public function test_queued_csv_import_dispatches_matching_job_for_created_units(): void
    {
        Queue::fake();
        Storage::fake('local');

        $contract = $this->createEditableContract();
        $path = 'imports/units.csv';
        Storage::disk('local')->put($path, "unit_type,unit_number,status,price\nvilla,A-101,available,500000\n");

        (new ProcessContractUnitsCsv($contract->id, $path))->handle();

        Queue::assertPushed(MatchUnitSearchAlertsJob::class);
    }

    public function test_queued_csv_import_remains_successful_when_alert_dispatch_fails_after_commit(): void
    {
        Storage::fake('local');
        Log::spy();

        $matching = Mockery::mock(SalesUnitSearchAlertMatchingService::class);
        $matching->shouldReceive('dispatchForUnit')
            ->once()
            ->andThrow(new \RuntimeException('queue unavailable'));
        $this->instance(SalesUnitSearchAlertMatchingService::class, $matching);

        $contract = $this->createEditableContract();
        $path = 'imports/units.csv';
        Storage::disk('local')->put($path, "unit_type,unit_number,status,price\nvilla,A-101,available,500000\n");

        (new ProcessContractUnitsCsv($contract->id, $path))->handle();

        $this->assertDatabaseHas('contract_units', [
            'contract_id' => $contract->id,
            'unit_number' => 'A-101',
        ]);

        Log::shouldHaveReceived('warning')
            ->with('Unit search alert matching dispatch failed', Mockery::on(function (array $context) use ($contract) {
                return ($context['source'] ?? null) === 'process_contract_units_csv'
                    && ($context['contract_id'] ?? null) === $contract->id
                    && ($context['error'] ?? null) === 'queue unavailable'
                    && ! empty($context['contract_unit_id']);
            }))
            ->once();
    }

    public function test_queued_csv_import_remains_successful_when_alert_dispatch_helper_fails_after_commit(): void
    {
        Storage::fake('local');
        Log::spy();

        $dispatcher = Mockery::mock(DispatchUnitSearchAlertMatching::class);
        $dispatcher->shouldReceive('dispatchManySafely')
            ->once()
            ->andThrow(new \RuntimeException('helper unavailable'));
        $this->instance(DispatchUnitSearchAlertMatching::class, $dispatcher);

        $contract = $this->createEditableContract();
        $path = 'imports/units.csv';
        Storage::disk('local')->put($path, "unit_type,unit_number,status,price\nvilla,A-102,available,510000\n");

        (new ProcessContractUnitsCsv($contract->id, $path))->handle();

        $this->assertDatabaseHas('contract_units', [
            'contract_id' => $contract->id,
            'unit_number' => 'A-102',
        ]);

        Log::shouldHaveReceived('warning')
            ->with('Unit search alert matching dispatch helper failed', Mockery::on(function (array $context) use ($contract) {
                return ($context['source'] ?? null) === 'process_contract_units_csv'
                    && ($context['contract_id'] ?? null) === $contract->id
                    && ($context['unit_count'] ?? null) === 1
                    && ($context['error'] ?? null) === 'helper unavailable';
            }))
            ->once();
    }

    public function test_direct_csv_upload_remains_successful_when_alert_dispatch_fails_after_commit(): void
    {
        Log::spy();

        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        Auth::login($admin);

        $matching = Mockery::mock(SalesUnitSearchAlertMatchingService::class);
        $matching->shouldReceive('dispatchForUnit')
            ->once()
            ->andThrow(new \RuntimeException('dispatch failed'));
        $this->instance(SalesUnitSearchAlertMatchingService::class, $matching);

        $contract = $this->createEditableContract();
        $file = $this->csvUpload("unit_type,unit_number,status,price\nvilla,A-202,available,650000\n");

        $result = app(ContractUnitService::class)->uploadCsvByContractId($contract->id, $file);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['units_created']);
        $this->assertDatabaseHas('contract_units', [
            'contract_id' => $contract->id,
            'unit_number' => 'A-202',
        ]);

        Log::shouldHaveReceived('warning')
            ->with('Unit search alert matching dispatch failed', Mockery::on(function (array $context) {
                return ($context['source'] ?? null) === 'contract_unit_service'
                    && ($context['error'] ?? null) === 'dispatch failed'
                    && ! empty($context['contract_unit_id']);
            }))
            ->once();
    }

    public function test_direct_csv_upload_remains_successful_when_alert_dispatch_helper_fails_after_commit(): void
    {
        Log::spy();

        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        Auth::login($admin);

        $dispatcher = Mockery::mock(DispatchUnitSearchAlertMatching::class);
        $dispatcher->shouldReceive('dispatchManySafely')
            ->once()
            ->andThrow(new \RuntimeException('helper failed'));
        $this->instance(DispatchUnitSearchAlertMatching::class, $dispatcher);

        $contract = $this->createEditableContract();
        $file = $this->csvUpload("unit_type,unit_number,status,price\nvilla,A-203,available,660000\n");

        $result = app(ContractUnitService::class)->uploadCsvByContractId($contract->id, $file);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['units_created']);
        $this->assertDatabaseHas('contract_units', [
            'contract_id' => $contract->id,
            'unit_number' => 'A-203',
        ]);

        Log::shouldHaveReceived('warning')
            ->with('Unit search alert matching dispatch helper failed', Mockery::on(function (array $context) {
                return ($context['source'] ?? null) === 'contract_unit_service'
                    && ($context['unit_count'] ?? null) === 1
                    && ($context['error'] ?? null) === 'helper failed';
            }))
            ->once();
    }

    public function test_failed_direct_csv_upload_does_not_dispatch_alert_matching(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        Auth::login($admin);

        $dispatcher = Mockery::mock(DispatchUnitSearchAlertMatching::class);
        $dispatcher->shouldNotReceive('dispatchManySafely');
        $this->instance(DispatchUnitSearchAlertMatching::class, $dispatcher);

        $contract = $this->createEditableContract();
        $file = $this->csvUpload('');

        $this->expectException(\Exception::class);

        app(ContractUnitService::class)->uploadCsvByContractId($contract->id, $file);
    }

    public function test_safe_dispatch_deduplicates_unit_ids_and_continues_after_per_unit_failure(): void
    {
        Log::spy();

        $matching = Mockery::mock(SalesUnitSearchAlertMatchingService::class);
        $matching->shouldReceive('dispatchForUnit')->once()->with(123)->andThrow(new \RuntimeException('bad unit'));
        $matching->shouldReceive('dispatchForUnit')->once()->with(456);
        $this->instance(SalesUnitSearchAlertMatchingService::class, $matching);

        app(DispatchUnitSearchAlertMatching::class)->dispatchManySafely([123, 123, 456, 0, null], [
            'source' => 'test',
        ]);

        Log::shouldHaveReceived('warning')
            ->with('Unit search alert matching dispatch failed', Mockery::on(function (array $context) {
                return ($context['source'] ?? null) === 'test'
                    && ($context['contract_unit_id'] ?? null) === 123
                    && ($context['error'] ?? null) === 'bad unit';
            }))
            ->once();

        $this->addToAssertionCount(1);
    }

    public function test_reservation_cancellation_dispatches_matching_when_unit_returns_available(): void
    {
        Queue::fake();

        $salesUser = User::factory()->create(['type' => 'sales']);
        $salesUser->assignRole('sales');

        $contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'reserved',
        ]);

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $salesUser->id,
            'status' => 'confirmed',
        ]);

        app(SalesReservationService::class)->cancelReservation($reservation->id, null, $salesUser);

        Queue::assertPushed(MatchUnitSearchAlertsJob::class);
        $this->assertSame('available', $unit->fresh()->status);
    }

    public function test_reservation_cancellation_remains_successful_when_alert_dispatch_helper_fails_after_commit(): void
    {
        Log::spy();

        $salesUser = User::factory()->create(['type' => 'sales']);
        $salesUser->assignRole('sales');

        $contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'reserved',
        ]);

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $salesUser->id,
            'status' => 'confirmed',
        ]);

        $dispatcher = Mockery::mock(DispatchUnitSearchAlertMatching::class);
        $dispatcher->shouldReceive('dispatchManySafely')
            ->once()
            ->andThrow(new \RuntimeException('helper failed'));
        $this->instance(DispatchUnitSearchAlertMatching::class, $dispatcher);

        $cancelled = app(SalesReservationService::class)->cancelReservation($reservation->id, null, $salesUser);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('available', $unit->fresh()->status);

        Log::shouldHaveReceived('warning')
            ->with('Unit search alert matching dispatch helper failed', Mockery::on(function (array $context) use ($reservation, $unit) {
                return ($context['source'] ?? null) === 'sales_reservation_cancellation'
                    && ($context['reservation_id'] ?? null) === $reservation->id
                    && ($context['contract_unit_id'] ?? null) === $unit->id
                    && ($context['error'] ?? null) === 'helper failed';
            }))
            ->once();
    }

    private function createEditableContract(): Contract
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        return $contract;
    }

    private function csvUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'units-csv-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'units.csv', 'text/csv', null, true);
    }
}
