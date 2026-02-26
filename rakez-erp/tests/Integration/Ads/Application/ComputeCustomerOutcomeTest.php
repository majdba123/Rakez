<?php

namespace Tests\Integration\Ads\Application;

use App\Application\Ads\ComputeCustomerOutcome;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Infrastructure\Ads\Persistence\EloquentOutcomeStore;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use App\Infrastructure\Ads\Services\EventIdGenerator;
use App\Infrastructure\Ads\Services\HashingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeCustomerOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private ComputeCustomerOutcome $useCase;

    private HashingService $hasher;

    private EventIdGenerator $idGen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new HashingService();
        $this->idGen = new EventIdGenerator();
        $this->useCase = new ComputeCustomerOutcome(
            new EloquentOutcomeStore(),
            $this->hasher,
            $this->idGen,
        );
    }

    public function test_full_flow_creates_outbox_rows_for_all_platforms(): void
    {
        $event = $this->useCase->execute([
            'customer_id' => 'cust_001',
            'email' => 'buyer@example.com',
            'phone' => '+971551234567',
            'outcome_type' => 'PURCHASE',
            'occurred_at' => '2026-01-15 10:00:00',
            'value' => 999.99,
            'currency' => 'SAR',
            'meta_fbc' => 'fb.1.123.abc',
            'tiktok_ttclid' => 'tt_click_789',
        ]);

        $this->assertDatabaseCount('ads_outcome_events', 3);
        $this->assertDatabaseHas('ads_outcome_events', ['platform' => 'meta', 'status' => 'pending']);
        $this->assertDatabaseHas('ads_outcome_events', ['platform' => 'snap', 'status' => 'pending']);
        $this->assertDatabaseHas('ads_outcome_events', ['platform' => 'tiktok', 'status' => 'pending']);

        $this->assertSame(OutcomeType::Purchase, $event->outcomeType);
    }

    public function test_hashed_email_matches_hashing_service(): void
    {
        $event = $this->useCase->execute([
            'customer_id' => 'cust_002',
            'email' => 'Test@Example.COM',
            'outcome_type' => 'LEAD_QUALIFIED',
            'occurred_at' => '2026-01-15',
        ]);

        $expectedHash = $this->hasher->hashEmail('Test@Example.COM');
        $emailId = collect($event->identifiers)->first(fn ($id) => $id->type === 'em');

        $this->assertNotNull($emailId);
        $this->assertSame($expectedHash, $emailId->hashedValue);
    }

    public function test_hashed_phone_matches_hashing_service(): void
    {
        $event = $this->useCase->execute([
            'customer_id' => 'cust_003',
            'phone' => '+1 (555) 123-4567',
            'outcome_type' => 'PURCHASE',
            'occurred_at' => '2026-01-15',
            'value' => 50,
        ]);

        $expectedHash = $this->hasher->hashPhone('+1 (555) 123-4567');
        $phoneId = collect($event->identifiers)->first(fn ($id) => $id->type === 'ph');

        $this->assertNotNull($phoneId);
        $this->assertSame($expectedHash, $phoneId->hashedValue);
    }

    public function test_generated_event_id_is_deterministic(): void
    {
        $data = [
            'customer_id' => 'cust_004',
            'outcome_type' => 'PURCHASE',
            'occurred_at' => '2026-01-15 10:00:00',
            'order_id' => 'order_123',
            'value' => 100,
        ];

        $event = $this->useCase->execute($data);

        $expectedId = $this->idGen->generate(
            \App\Domain\Ads\ValueObjects\Platform::Meta,
            'cust_004',
            OutcomeType::Purchase,
            \Carbon\CarbonImmutable::parse('2026-01-15 10:00:00'),
            'order_123',
        );

        $this->assertSame($expectedId, $event->eventId);
    }

    public function test_specific_platforms_only(): void
    {
        $event = $this->useCase->execute([
            'customer_id' => 'cust_005',
            'outcome_type' => 'PURCHASE',
            'occurred_at' => '2026-01-15',
            'value' => 50,
            'platforms' => ['meta', 'snap'],
        ]);

        $this->assertDatabaseCount('ads_outcome_events', 2);
        $this->assertDatabaseMissing('ads_outcome_events', ['platform' => 'tiktok']);
    }

    public function test_click_ids_are_stored(): void
    {
        $this->useCase->execute([
            'customer_id' => 'cust_006',
            'outcome_type' => 'PURCHASE',
            'occurred_at' => '2026-01-15',
            'value' => 100,
            'meta_fbc' => 'fb.1.123.abc',
            'snap_click_id' => 'sc_click_xyz',
            'tiktok_ttclid' => 'tt_click_abc',
        ]);

        $metaRow = AdsOutcomeEvent::where('platform', 'meta')->first();
        $this->assertSame('fb.1.123.abc', $metaRow->click_ids['meta_fbc']);
    }
}
