<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SalesReservationParticipant;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesReservationParticipantsStage1Test extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;
    protected User $otherSalesUser;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        $this->otherSalesUser = User::factory()->create(['type' => 'sales']);

        $this->contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);

        $this->unit = ContractUnit::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'available',
            'price' => 500000,
        ]);
    }

    protected function reservationPayload(array $extras = []): array
    {
        return array_merge([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'contract_date' => '2025-01-25',
            'reservation_type' => 'confirmed_reservation',
            'client_name' => 'Ahmed Ali',
            'client_mobile' => '0501234567',
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA0000000000000000000000',
            'payment_method' => 'bank_transfer',
            'down_payment_amount' => 100000,
            'down_payment_status' => 'non_refundable',
            'purchase_mechanism' => 'supported_bank',
            'evacuation_date' => '2026-12-31',
            'receipt_voucher' => UploadedFile::fake()->image('stub.jpg'),
        ], $extras);
    }

    public function test_reservation_creation_without_participants_still_stores_creator_as_participant(): void
    {
        $payload = $this->reservationPayload();

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertNotNull($reservation);

        $this->assertSame(1, SalesReservationParticipant::where('sales_reservation_id', $reservation->id)->count());

        $p = SalesReservationParticipant::first();
        $this->assertSame((int) $this->salesUser->id, (int) $p->user_id);
        $this->assertFalse($p->did_bring);
        $this->assertFalse($p->did_convince);
        $this->assertFalse($p->did_close);
        $this->assertSame(1.0, (float) $p->weight);
        $this->assertSame((int) $this->salesUser->id, (int) $p->created_by);
    }

    public function test_create_reservation_with_additional_participants(): void
    {
        $payload = $this->reservationPayload([
            'participants' => [
                [
                    'user_id' => $this->otherSalesUser->id,
                    'did_bring' => true,
                    'did_convince' => false,
                    'did_close' => true,
                    'weight' => 2,
                    'notes' => 'helper',
                ],
            ],
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertSame(
            2,
            SalesReservationParticipant::where('sales_reservation_id', $reservation->id)->count()
        );

        $other = SalesReservationParticipant::where('user_id', $this->otherSalesUser->id)->first();
        $this->assertNotNull($other);
        $this->assertTrue($other->did_bring);
        $this->assertTrue($other->did_close);
        $this->assertFalse($other->did_convince);
        $this->assertSame(2.0, (float) $other->weight);

        $creator = SalesReservationParticipant::where('user_id', $this->salesUser->id)->first();
        $this->assertNotNull($creator);
    }

    public function test_creator_explicit_in_payload_is_not_duplicated_and_preserves_flags(): void
    {
        $payload = $this->reservationPayload([
            'participants' => [
                [
                    'user_id' => $this->salesUser->id,
                    'did_bring' => true,
                    'did_close' => true,
                    'weight' => 3,
                ],
            ],
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->post('/api/sales/reservations', $payload);

        $response->assertStatus(201);

        $reservation = SalesReservation::first();
        $this->assertSame(1, SalesReservationParticipant::where('sales_reservation_id', $reservation->id)->count());

        $row = SalesReservationParticipant::first();
        $this->assertTrue($row->did_bring);
        $this->assertTrue($row->did_close);
        $this->assertSame(3.0, (float) $row->weight);
    }

    public function test_duplicate_participant_user_id_in_payload_fails(): void
    {
        $payload = $this->reservationPayload([
            'participants' => [
                ['user_id' => $this->otherSalesUser->id],
                ['user_id' => $this->otherSalesUser->id],
            ],
        ]);
        unset($payload['receipt_voucher']);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/sales/reservations', $payload);

        $response->assertStatus(422);
    }

    public function test_eligible_participants_returns_sales_users_and_always_includes_auth_user(): void
    {
        $leader = User::factory()->create(['type' => 'sales_leader']);
        $leader->assignRole('sales_leader');

        foreach ([$this->salesUser->id, $this->otherSalesUser->id] as $wid) {
            $this->assertDatabaseHas('users', ['id' => $wid, 'type' => 'sales']);
        }

        $response = $this->actingAs($leader, 'sanctum')
            ->getJson('/api/sales/reservations/eligible-participants');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'email', 'type', 'team'],
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($leader->id, $ids);
        $this->assertContains($this->salesUser->id, $ids);
        $this->assertContains($this->otherSalesUser->id, $ids);
    }

    public function test_get_reservation_participants(): void
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
        ]);

        SalesReservationParticipant::create([
            'sales_reservation_id' => $reservation->id,
            'user_id' => $this->otherSalesUser->id,
            'did_bring' => true,
            'did_convince' => false,
            'did_close' => false,
            'weight' => 1,
            'notes' => null,
            'created_by' => $this->salesUser->id,
        ]);

        SalesReservationParticipant::create([
            'sales_reservation_id' => $reservation->id,
            'user_id' => $this->salesUser->id,
            'did_bring' => false,
            'did_convince' => false,
            'did_close' => false,
            'weight' => 1,
            'notes' => null,
            'created_by' => $this->salesUser->id,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/reservations/{$reservation->id}/participants");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_put_reservation_participants_sync(): void
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->putJson("/api/sales/reservations/{$reservation->id}/participants", [
                'participants' => [
                    [
                        'user_id' => $this->otherSalesUser->id,
                        'did_convince' => true,
                        'weight' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_reservation_participants', [
            'sales_reservation_id' => $reservation->id,
            'user_id' => $this->otherSalesUser->id,
            'did_convince' => true,
        ]);

        $this->assertDatabaseHas('sales_reservation_participants', [
            'sales_reservation_id' => $reservation->id,
            'user_id' => $this->salesUser->id,
        ]);

        $this->assertSame(2, SalesReservationParticipant::where('sales_reservation_id', $reservation->id)->count());
    }
}
