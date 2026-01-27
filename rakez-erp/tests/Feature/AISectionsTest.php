<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AISectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_are_filtered_by_capabilities(): void
    {
        $user = User::factory()->create(['type' => 'developer']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'General']);
        $response->assertJsonFragment(['label' => 'Contracts']);
        $response->assertJsonMissing(['label' => 'Dashboard']);
        $response->assertJsonMissing(['label' => 'Units']);
    }
}
