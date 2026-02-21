<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AISectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_are_filtered_by_capabilities(): void
    {
        $user = User::factory()->create(['type' => 'developer']);
        Permission::firstOrCreate(['name' => 'use-ai-assistant', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'contracts.view', 'guard_name' => 'web']);
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('contracts.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'General']);
        $response->assertJsonFragment(['label' => 'Contracts']);
        $response->assertJsonMissing(['label' => 'Dashboard']);
        $response->assertJsonMissing(['label' => 'Units']);
    }
}
