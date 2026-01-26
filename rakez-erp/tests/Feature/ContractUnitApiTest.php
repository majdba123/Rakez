<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContractUnitApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_can_list_units_by_contract()
    {
        $user = User::factory()->create(['type' => 'project_management']);
        $user->assignRole('project_management');
        // Permissions are assigned via role

        $contract = Contract::factory()->create(['user_id' => $user->id]);
        $secondParty = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->count(5)->create(['second_party_data_id' => $secondParty->id]);

        $response = $this->actingAs($user)->getJson("/api/contracts/units/show/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_can_add_unit_to_contract()
    {
        $user = User::factory()->create(['type' => 'project_management']);
        $user->assignRole('project_management');

        $contract = Contract::factory()->create(['user_id' => $user->id]);
        
        // Create ContractInfo
        \App\Models\ContractInfo::factory()->create(['contract_id' => $contract->id]);
        
        // Create SecondPartyData with processed_by = user->id
        SecondPartyData::factory()->create([
            'contract_id' => $contract->id,
            'processed_by' => $user->id
        ]);

        $data = [
            'unit_type' => 'Apartment',
            'unit_number' => '101',
            'price' => 500000,
            'area' => '150',
            'status' => 'available',
            'description' => 'Luxury apartment'
        ];

        $response = $this->actingAs($user)->postJson("/api/contracts/units/store/{$contract->id}", $data);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJsonPath('data.unit_number', '101');
    }

    public function test_can_upload_csv_units()
    {
        $user = User::factory()->create(['type' => 'project_management']);
        $user->assignRole('project_management');

        $contract = Contract::factory()->create(['user_id' => $user->id]);
        
        // Create ContractInfo
        \App\Models\ContractInfo::factory()->create(['contract_id' => $contract->id]);
        
        // Create SecondPartyData with processed_by = user->id
        // Note: uploadCsvByContract updates processed_by, so it might not require it initially?
        // Let's check service logic.
        // It checks if secondPartyData exists. Then updates processed_by.
        // So we just need it to exist.
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);

        // Create a mock CSV file
        $header = "unit_type,unit_number,price,area,status,description";
        $row1 = "Villa,V1,1000000,300,available,Big Villa";
        $row2 = "Apartment,A1,500000,150,sold,Nice Apt";
        $content = implode("\n", [$header, $row1, $row2]);

        $file = UploadedFile::fake()->createWithContent('units.csv', $content);
        
        // Force mime type to text/csv to pass validation
        // In real upload, browser sends mime type, or Laravel detects it.
        // For testing, createWithContent might default to application/octet-stream.
        
        $response = $this->actingAs($user)->postJson("/api/contracts/units/upload-csv/{$contract->id}", [
            'csv_file' => $file
        ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJsonPath('data.units_created', 2);
            
        $this->assertDatabaseHas('contract_units', ['unit_number' => 'V1']);
        $this->assertDatabaseHas('contract_units', ['unit_number' => 'A1']);
    }

    public function test_cannot_modify_units_without_permission()
    {
        $user = User::factory()->create();
        // No units.edit permission

        $contract = Contract::factory()->create(['user_id' => $user->id]);
        $secondParty = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondParty->id]);

        $response = $this->actingAs($user)->deleteJson("/api/contracts/units/delete/{$unit->id}");

        $response->assertStatus(403);
    }
}
