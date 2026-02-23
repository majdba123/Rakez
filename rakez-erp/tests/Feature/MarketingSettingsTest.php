<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MarketingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_marketing_user_can_list_settings()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);
        
        MarketingSetting::create([
            'key' => 'conversion_rate',
            'value' => '2.5',
            'description' => 'Default conversion rate'
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/marketing/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['key', 'value', 'description']
                ]
            ]);
    }

    public function test_marketing_user_can_update_setting()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/marketing/settings/conversion_rate', [
                'value' => '3.0',
                'description' => 'Updated conversion rate'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => "Setting 'conversion_rate' updated successfully"
            ]);

        $this->assertDatabaseHas('marketing_settings', [
            'key' => 'conversion_rate',
            'value' => '3.0'
        ]);
    }

    public function test_setting_value_is_required()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/marketing/settings/conversion_rate', [
                'description' => 'Missing value'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_conversion_rate_update_validation()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/marketing/settings/conversion-rate', [
                'value' => 150 // Invalid: exceeds 100
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }
}
